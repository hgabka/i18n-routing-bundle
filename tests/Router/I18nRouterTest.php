<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use JMS\I18nRoutingBundle\Exception\NotAcceptableLanguageException;
use JMS\I18nRoutingBundle\Router\DefaultLocaleResolver;
use JMS\I18nRoutingBundle\Router\DefaultPatternGenerationStrategy;
use JMS\I18nRoutingBundle\Router\DefaultRouteExclusionStrategy;
use JMS\I18nRoutingBundle\Router\I18nLoader;
use JMS\I18nRoutingBundle\Router\I18nRouter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class I18nRouterTest extends TestCase
{
    private function router(string $strategy = 'custom', array $hostMap = [], ?Request $request = null, bool $redirectToHost = true, ?RequestContext $context = null): I18nRouter
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['contact' => '/kontakt'], 'de', 'routes');

        $collection = new RouteCollection();
        $collection->add('contact', new Route('/contact', ['_controller' => 'contact']));
        $collection->add('about', new Route('/about', ['_controller' => 'about']));
        $collection->add('_internal', new Route('/_internal', ['_controller' => 'internal']));

        $context ??= new RequestContext();
        $inner = $this->createStub(RouterInterface::class);
        $inner->method('getContext')->willReturn($context);
        $inner->method('getRouteCollection')->willReturn($collection);

        $locales = ['en', 'de'];
        $loader = new I18nLoader(
            new DefaultRouteExclusionStrategy(),
            new DefaultPatternGenerationStrategy($strategy, $translator, $locales, sys_get_temp_dir() . '/none', 'routes', 'en')
        );

        $requests = new RequestStack();
        if ($request) {
            $requests->push($request);
        }
        $container = new Container(new ParameterBag(['jms_i18n_routing.locales' => $locales]));
        $container->set('jms_i18n_routing.loader', $loader);
        $container->set('request_stack', $requests);

        $router = new I18nRouter($inner, $container);
        $router->setI18nLoaderId('jms_i18n_routing.loader');
        $router->setLocaleResolver(new DefaultLocaleResolver('hl', array_flip($hostMap)));
        $router->setDefaultLocale('en');
        $router->setHostMap($hostMap);
        $router->setRedirectToHost($redirectToHost);
        $router->setContext($context);

        return $router;
    }

    public function testRouteCollectionIsTheI18nExpandedOne(): void
    {
        $router = $this->router();

        $this->assertSame(
            ['en__RG__contact', 'de__RG__contact', 'en_de__RG__about', 'en__RG__about', 'de__RG__about', '_internal'],
            array_keys($router->getRouteCollection()->all())
        );
        $this->assertSame(['contact', 'about', '_internal'], array_keys($router->getOriginalRouteCollection()->all()));
    }

    public function testGenerateUsesTheLocaleParameter(): void
    {
        $router = $this->router();

        $this->assertSame('/kontakt', $router->generate('contact', ['_locale' => 'de']));
        $this->assertSame('/contact', $router->generate('contact', ['_locale' => 'en']));
    }

    public function testGenerateFallsBackToContextThenDefaultLocale(): void
    {
        $context = new RequestContext();
        $router = $this->router('custom', [], null, true, $context);
        $this->assertSame('/contact', $router->generate('contact'));

        $context->setParameter('_locale', 'de');
        $this->assertSame('/kontakt', $router->generate('contact'));
    }

    public function testGenerateWithPrefixStrategy(): void
    {
        $router = $this->router('prefix');

        $this->assertSame('/de/kontakt', $router->generate('contact', ['_locale' => 'de']));
        $this->assertSame('/en/about', $router->generate('about', ['_locale' => 'en']));
    }

    public function testGenerateForUntranslatedRouteUsesTheOriginalName(): void
    {
        $this->assertSame('/_internal', $this->router()->generate('_internal'));
    }

    public function testGenerateWithHostMapSwitchesHostForOtherLocalesAndRestoresIt(): void
    {
        $context = new RequestContext('', 'GET', 'example.com');
        $context->setParameter('_locale', 'en');
        $router = $this->router('custom', ['en' => 'example.com', 'de' => 'example.de'], null, true, $context);

        $this->assertSame('http://example.de/kontakt', $router->generate('contact', ['_locale' => 'de']));
        $this->assertSame('example.com', $context->getHost());
    }

    public function testMatchSetsLocaleAndStripsTheInternalPrefixFromTheRouteName(): void
    {
        $router = $this->router();

        $match = $router->match('/kontakt');

        $this->assertSame('contact', $match['_route']);
        $this->assertSame('de', $match['_locale']);
        $this->assertSame('contact', $match['_controller']);
    }

    public function testMatchRequest(): void
    {
        $match = $this->router()->matchRequest(Request::create('/kontakt'));

        $this->assertSame(['contact', 'de'], [$match['_route'], $match['_locale']]);
    }

    public function testMatchOfUnknownPathFails(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->router()->match('/nope');
    }

    public function testRouteSharedByLocalesUsesTheContextLocale(): void
    {
        $context = new RequestContext();
        $context->setParameter('_locale', 'de');
        $router = $this->router('custom', [], null, true, $context);

        $match = $router->match('/about');

        $this->assertSame(['about', 'de'], [$match['_route'], $match['_locale']]);
        $this->assertArrayNotHasKey('_locales', $match);
    }

    public function testRouteSharedByLocalesResolvesTheLocaleFromTheRequest(): void
    {
        $request = Request::create('/about');
        $request->headers->set('Accept-Language', 'de');

        $match = $this->router('custom', [], $request)->match('/about');

        $this->assertSame('de', $match['_locale']);
    }

    public function testRouteSharedByLocalesFallsBackToTheFirstLocale(): void
    {
        $match = $this->router('custom', [], Request::create('/about'))->match('/about');

        $this->assertSame('en', $match['_locale']);
    }

    public function testContextLocaleNotSupportedByTheRouteIsNotAcceptable(): void
    {
        $context = new RequestContext();
        $context->setParameter('_locale', 'fr');
        $router = $this->router('custom', [], null, true, $context);

        $this->expectException(NotAcceptableLanguageException::class);
        $router->match('/about');
    }

    public function testRouteOfAnotherHostIsRedirected(): void
    {
        $context = new RequestContext('', 'GET', 'example.com');
        $router = $this->router('custom', ['en' => 'example.com', 'de' => 'example.de'], null, true, $context);

        $match = $router->match('/kontakt');

        $this->assertSame('JMS\I18nRoutingBundle\Controller\RedirectController::redirectAction', $match['_controller']);
        $this->assertSame('example.de', $match['host']);
        $this->assertSame('/kontakt', $match['path']);
        $this->assertTrue($match['permanent']);
        $this->assertSame('contact', $match['_route']);
    }

    public function testRouteOfAnotherHostIsNotFoundWhenRedirectIsDisabled(): void
    {
        $context = new RequestContext('', 'GET', 'example.com');
        $router = $this->router('custom', ['en' => 'example.com', 'de' => 'example.de'], null, false, $context);

        $this->expectException(ResourceNotFoundException::class);
        $router->match('/kontakt');
    }

    public function testRouteOnTheRightHostIsMatchedNormally(): void
    {
        $context = new RequestContext('', 'GET', 'example.de');
        $router = $this->router('custom', ['en' => 'example.com', 'de' => 'example.de'], null, true, $context);

        $this->assertSame('contact', $router->match('/kontakt')['_route']);
    }

    public function testContextIsSharedWithTheInnerRouter(): void
    {
        $inner = $this->createMock(RouterInterface::class);
        $inner->method('getContext')->willReturn(new RequestContext());
        $inner->expects($this->once())->method('setContext');

        $router = new I18nRouter($inner, new Container());
        $router->setContext(new RequestContext('', 'POST'));

        $this->assertSame('POST', $router->getContext()->getMethod());
    }

    public function testWarmUpIsDelegatedOnlyToWarmableInnerRouters(): void
    {
        $this->assertSame([], $this->router()->warmUp(sys_get_temp_dir()));

        $inner = new class() implements RouterInterface, WarmableInterface {
            public function warmUp(string $cacheDir, ?string $buildDir = null): array
            {
                return ['warmed:' . $cacheDir];
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            public function getRouteCollection(): RouteCollection
            {
                return new RouteCollection();
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '';
            }

            public function match(string $pathinfo): array
            {
                return [];
            }
        };

        $this->assertSame(['warmed:/cache'], (new I18nRouter($inner, new Container()))->warmUp('/cache'));
    }
}
