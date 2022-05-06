<?php


namespace JMS\I18nRoutingBundle\EventListener;

use JMS\I18nRoutingBundle\Router\LocaleResolverInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Chooses the default locale.
 *
 * This listener chooses the default locale to use on the first request of a
 * user to the application.
 *
 * This listener is only active if the strategy is "prefix".
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class LocaleChoosingListener
{
    private $defaultLocale;
    private $locales;
    private $localeResolver;

    public function __construct($defaultLocale, array $locales, LocaleResolverInterface $localeResolver)
    {
        $this->defaultLocale = $defaultLocale;
        $this->locales = $locales;
        $this->localeResolver = $localeResolver;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $request = $event->getRequest();
        if ('' !== rtrim($request->getPathInfo(), '/')) {
            return;
        }

        $ex = $event->getThrowable();
        if (!$ex instanceof NotFoundHttpException || !$ex->getPrevious() instanceof ResourceNotFoundException) {
            return;
        }

        $locale = $this->localeResolver->resolveLocale($request, $this->locales) ?: $this->defaultLocale;
        $request->setLocale($locale);

        $params = $request->query->all();
        unset($params['hl']);

        $event->setResponse(new RedirectResponse($request->getBaseUrl() . '/' . $locale . '/' . ($params ? '?' . http_build_query($params) : '')));
    }
}
