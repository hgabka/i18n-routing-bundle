<?php

namespace JMS\I18nRoutingBundle\Router;

use JMS\I18nRoutingBundle\Exception\NotAcceptableLanguageException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * I18n Router implementation.
 *
 * Wraps the default Symfony router instead of extending it, since
 * Symfony\Bundle\FrameworkBundle\Routing\Router is final as of Symfony 8.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class I18nRouter implements RouterInterface, RequestMatcherInterface, WarmableInterface
{
    private ?array $hostMap = [];
    private ?string $i18nLoaderId = null;
    private bool $redirectToHost = true;
    private ?LocaleResolverInterface $localeResolver = null;
    private ?string $defaultLocale = null;
    private RequestContext $context;
    private ?UrlMatcher $matcher = null;
    private ?UrlGenerator $generator = null;

    public function __construct(
        private readonly RouterInterface $innerRouter,
        private readonly ContainerInterface $container,
    ) {
        $this->context = $innerRouter->getContext();
    }

    public function setLocaleResolver(LocaleResolverInterface $resolver): void
    {
        $this->localeResolver = $resolver;
    }

    /**
     * Whether the user should be redirected to a different host if the
     * matching route is not belonging to the current domain.
     *
     * @param bool $bool
     */
    public function setRedirectToHost($bool): void
    {
        $this->redirectToHost = (bool) $bool;
    }

    /**
     * Sets the host map to use.
     *
     * @param array $hostMap a map of locales to hosts
     */
    public function setHostMap(array $hostMap): void
    {
        $this->hostMap = $hostMap;
    }

    public function setI18nLoaderId(?string $id): void
    {
        $this->i18nLoaderId = $id;
    }

    public function setDefaultLocale(?string $locale): void
    {
        $this->defaultLocale = $locale;
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
        $this->innerRouter->setContext($context);
        // the context object changed, so the cached matcher/generator must be rebuilt against it
        $this->matcher = null;
        $this->generator = null;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }

    private function getMatcher(): UrlMatcher
    {
        return $this->matcher ??= new UrlMatcher($this->getRouteCollection(), $this->context);
    }

    private function getGenerator(): UrlGenerator
    {
        return $this->generator ??= new UrlGenerator($this->getRouteCollection(), $this->context);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->innerRouter instanceof WarmableInterface) {
            return $this->innerRouter->warmUp($cacheDir, $buildDir);
        }

        return [];
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $context = $this->getContext();

        // determine the most suitable locale to use for route generation
        $currentLocale = $context->getParameter('_locale');
        if (isset($parameters['_locale'])) {
            $locale = $parameters['_locale'];
        } elseif ($currentLocale) {
            $locale = $currentLocale;
        } else {
            $locale = $this->defaultLocale;
        }

        // if the locale is changed, and we have a host map, then we need to
        // generate an absolute URL
        if ($currentLocale && $currentLocale !== $locale && $this->hostMap) {
            $referenceType = self::NETWORK_PATH === $referenceType ? self::NETWORK_PATH : self::ABSOLUTE_URL;
        }
        $needsHost = self::NETWORK_PATH === $referenceType || self::ABSOLUTE_URL === $referenceType;

        // if an absolute or network URL is requested, we set the correct host
        if ($needsHost && $this->hostMap) {
            $currentHost = $context->getHost();
            $context->setHost($this->hostMap[$locale]);
        }

        try {
            $url = $this->getGenerator()->generate($locale . I18nLoader::ROUTING_PREFIX . $name, $parameters, $referenceType);

            if ($needsHost && $this->hostMap) {
                $context->setHost($currentHost);
            }

            return $url;
        } catch (RouteNotFoundException $ex) {
            if ($needsHost && $this->hostMap) {
                $context->setHost($currentHost);
            }

            // fallback to default behavior
        }

        // use the default behavior if no localized route exists
        return $this->getGenerator()->generate($name, $parameters, $referenceType);
    }

    public function match(string $pathinfo): array
    {
        return $this->matchI18n($this->getMatcher()->match($pathinfo), $pathinfo);
    }

    public function getRouteCollection(): RouteCollection
    {
        $collection = $this->innerRouter->getRouteCollection();

        return $this->container->get($this->i18nLoaderId)->load($collection);
    }

    public function getOriginalRouteCollection(): RouteCollection
    {
        return $this->innerRouter->getRouteCollection();
    }

    public function matchRequest(Request $request): array
    {
        return $this->matchI18n($this->getMatcher()->matchRequest($request), $request->getPathInfo());
    }

    private function matchI18n(array $params, $url)
    {
        if (false === $params) {
            return false;
        }

        $request = $this->getRequest();

        if (isset($params['_locales'])) {
            if (false !== $pos = strpos($params['_route'], I18nLoader::ROUTING_PREFIX)) {
                $params['_route'] = substr($params['_route'], $pos + strlen(I18nLoader::ROUTING_PREFIX));
            }

            if (!($currentLocale = $this->getContext()->getParameter('_locale'))
                    && null !== $request) {
                $currentLocale = $this->localeResolver->resolveLocale(
                    $request,
                    $params['_locales']
                );

                // If the locale resolver was not able to determine a locale, then all efforts to
                // make an informed decision have failed. Just display something as a last resort.
                if (!$currentLocale) {
                    $currentLocale = reset($params['_locales']);
                }
            }

            if (!in_array($currentLocale, $params['_locales'], true)) {
                // TODO: We might want to allow the user to be redirected to the route for the given locale if
                //       it exists regardless of whether it would be on another domain, or the same domain.
                //       Below we assume that we do not want to redirect always.

                // if the available locales are on a different host, throw a ResourceNotFoundException
                if ($this->hostMap) {
                    // generate host maps
                    $hostMap = $this->hostMap;
                    $availableHosts = array_map(function ($locale) use ($hostMap) {
                        return $hostMap[$locale];
                    }, $params['_locales']);

                    $differentHost = true;
                    foreach ($availableHosts as $host) {
                        if ($this->hostMap[$currentLocale] === $host) {
                            $differentHost = false;

                            break;
                        }
                    }

                    if ($differentHost) {
                        throw new ResourceNotFoundException(sprintf('The route "%s" is not available on the current host "%s", but only on these hosts "%s".', $params['_route'], $this->hostMap[$currentLocale], implode(', ', $availableHosts)));
                    }
                }

                // no host map, or same host means that the given locale is not supported for this route
                throw new NotAcceptableLanguageException($currentLocale, $params['_locales']);
            }

            unset($params['_locales']);
            $params['_locale'] = $currentLocale;
        } elseif (isset($params['_locale']) && 0 < $pos = strpos($params['_route'], I18nLoader::ROUTING_PREFIX)) {
            $params['_route'] = substr($params['_route'], $pos + strlen(I18nLoader::ROUTING_PREFIX));
        }

        // check if the matched route belongs to a different locale on another host
        if (isset($params['_locale'])
                && isset($this->hostMap[$params['_locale']])
                && $this->getContext()->getHost() !== $host = $this->hostMap[$params['_locale']]) {
            if (!$this->redirectToHost) {
                throw new ResourceNotFoundException(sprintf('Resource corresponding to pattern "%s" not found for locale "%s".', $url, $this->getContext()->getParameter('_locale')));
            }

            return [
                '_controller' => 'JMS\I18nRoutingBundle\Controller\RedirectController::redirectAction',
                'path'        => $url,
                'host'        => $host,
                'permanent'   => true,
                'scheme'      => $this->getContext()->getScheme(),
                'httpPort'    => $this->getContext()->getHttpPort(),
                'httpsPort'   => $this->getContext()->getHttpsPort(),
                '_route'      => $params['_route'],
            ];
        }

        // if we have no locale set on the route, we try to set one according to the localeResolver
        // if we don't do this all _internal routes will have the default locale on first request
        if (!isset($params['_locale'])
                && null !== $request
                && $locale = $this->localeResolver->resolveLocale(
                    $request,
                    $this->container->getParameter('jms_i18n_routing.locales')
                )) {
            $params['_locale'] = $locale;
        }

        return $params;
    }

    /**
     * @return null|Request
     */
    private function getRequest(): ?Request
    {
        $request = null;
        if ($this->container->has('request_stack')) {
            $request = $this->container->get('request_stack')->getCurrentRequest();
        }

        return $request;
    }
}
