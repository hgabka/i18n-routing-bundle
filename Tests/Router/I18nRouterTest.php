<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use JMS\I18nRoutingBundle\Exception\NotAcceptableLanguageException;
use JMS\I18nRoutingBundle\Router\DefaultPatternGenerationStrategy;
use JMS\I18nRoutingBundle\Router\DefaultRouteExclusionStrategy;
use JMS\I18nRoutingBundle\Router\I18nLoader;
use JMS\I18nRoutingBundle\Router\I18nRouter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\YamlFileLoader as TranslationLoader;
use Symfony\Component\Translation\Translator;

/**
 * @coversNothing
 */
class I18nRouterTest extends TestCase
{
    public function testGenerate()
    {
        $router = $this->getRouter();
        self::assertSame('/welcome-on-our-website', $router->generate('welcome'));

        $context = new RequestContext();
        $context->setParameter('_locale', 'en');
        $router->setContext($context);

        self::assertSame('/welcome-on-our-website', $router->generate('welcome'));
        self::assertSame('/willkommen-auf-unserer-webseite', $router->generate('welcome', ['_locale' => 'de']));
        self::assertSame('/welcome-on-our-website', $router->generate('welcome', ['_locale' => 'fr']));

        // test homepage
        self::assertSame('/', $router->generate('homepage', ['_locale' => 'en']));
        self::assertSame('/', $router->generate('homepage', ['_locale' => 'de']));
    }

    public function testGenerateWithHostMap()
    {
        $router = $this->getRouter();
        $router->setHostMap([
            'de' => 'de.host',
            'en' => 'en.host',
            'fr' => 'fr.host',
        ]);

        self::assertSame('/welcome-on-our-website', $router->generate('welcome'));
        self::assertSame('http://en.host/welcome-on-our-website', $router->generate('welcome', [], UrlGeneratorInterface::ABSOLUTE_URL));

        $context = new RequestContext();
        $context->setParameter('_locale', 'en');
        $router->setContext($context);

        self::assertSame('/welcome-on-our-website', $router->generate('welcome'));
        self::assertSame('http://en.host/welcome-on-our-website', $router->generate('welcome', [], UrlGeneratorInterface::ABSOLUTE_URL));
        self::assertSame('http://de.host/willkommen-auf-unserer-webseite', $router->generate('welcome', ['_locale' => 'de']));
        self::assertSame('http://de.host/willkommen-auf-unserer-webseite', $router->generate('welcome', ['_locale' => 'de'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testGenerateDoesUseCorrectHostWhenSchemeChanges()
    {
        $router = $this->getRouter();

        $router->setHostMap([
            'en' => 'en.test',
            'de' => 'de.test',
        ]);

        $context = new RequestContext();
        $context->setHost('en.test');
        $context->setScheme('http');
        $context->setParameter('_locale', 'en');
        $router->setContext($context);

        self::assertSame('https://en.test/login', $router->generate('login'));
        self::assertSame('https://de.test/einloggen', $router->generate('login', ['_locale' => 'de']));
    }

    public function testGenerateDoesNotI18nInternalRoutes()
    {
        $router = $this->getRouter();

        self::assertSame('/internal?_locale=de', $router->generate('_internal', ['_locale' => 'de']));
    }

    public function testGenerateWithNonI18nRoute()
    {
        $router = $this->getRouter('routing.yml', new IdentityTranslator());
        self::assertSame('/this-is-used-for-checking-login', $router->generate('login_check'));
    }

    public function testMatch()
    {
        $router = $this->getRouter();
        $router->setHostMap([
            'en' => 'en.test',
            'de' => 'de.test',
            'fr' => 'fr.test',
        ]);

        $context = new RequestContext('', 'GET', 'en.test');
        $context->setParameter('_locale', 'en');
        $router->setContext($context);

        self::assertSame(['_controller' => 'foo', '_locale' => 'en', '_route' => 'welcome'], $router->match('/welcome-on-our-website'));

        self::assertSame([
            '_controller' => 'JMS\I18nRoutingBundle\Controller\RedirectController::redirectAction',
            'path'        => '/willkommen-auf-unserer-webseite',
            'host'        => 'de.test',
            'permanent'   => true,
            'scheme'      => 'http',
            'httpPort'    => 80,
            'httpsPort'   => 443,
            '_route'      => 'welcome',
        ], $router->match('/willkommen-auf-unserer-webseite'));
    }

    public function testRouteNotFoundForActiveLocale()
    {
        $router = $this->getNonRedirectingHostMapRouter();
        $context = new RequestContext();
        $context->setParameter('_locale', 'en_US');
        $context->setHost('us.test');
        $router->setContext($context);

        // The route should be available for both en_UK and en_US
        self::assertSame(['_route' => 'news_overview', '_locale' => 'en_US'], $router->match('/news'));

        $context->setParameter('_locale', 'en_UK');
        $context->setHost('uk.test');
        $router->setContext($context);

        // The route should be available for both en_UK and en_US
        self::assertSame(['_route' => 'news_overview', '_locale' => 'en_UK'], $router->match('/news'));

        // Tests whether generating a route to a different locale works
        self::assertSame('http://nl.test/nieuws', $router->generate('news_overview', ['_locale' => 'nl_NL']));

        self::assertSame(['_route' => 'english_only', '_locale' => 'en_UK'], $router->match('/english-only'));
    }

    /**
     * Tests whether sublocales are properly translated (en_UK and en_US can use different patterns)
     */
    public function testSubLocaleTranslation()
    {
        // Note that the default is set to en_UK by getDoubleLocaleRouter()
        $router = $this->getNonRedirectingHostMapRouter();
        $context = new RequestContext();
        $context->setParameter('_locale', 'en_US');
        $context->setHost('us.test');
        $router->setContext($context);

        // Test overwrite
        self::assertSame(['_route' => 'sub_locale', '_locale' => 'en_US'], $router->match('/american'));

        $context->setParameter('_locale', 'en_UK');
        $context->setHost('uk.test');
        $router->setContext($context);
        self::assertSame(['_route' => 'enUK_only', '_locale' => 'en_UK'], $router->match('/enUK-only'));
    }

    /**
     * @dataProvider getMatchThrowsExceptionFixtures
     *
     * @param mixed $locale
     * @param mixed $host
     * @param mixed $pattern
     */
    public function testMatchThrowsException($locale, $host, $pattern)
    {
        $this->expectException(ResourceNotFoundException::class);

        $router = $this->getNonRedirectingHostMapRouter();
        $context = new RequestContext();
        $context->setParameter('_locale', $locale);
        $context->setHost($host);
        $router->setContext($context);

        $router->match($pattern);
    }

    public function getMatchThrowsExceptionFixtures()
    {
        return [
            ['en_UK', 'uk.tests', '/nieuws'],
            ['en_UK', 'uk.tests', '/dutch_only'],
            ['en_US', 'us.tests', '/enUK-only'],
            ['en_US', 'us.tests', '/english'],
        ];
    }

    /**
     * @dataProvider getGenerateThrowsExceptionFixtures
     *
     * @param mixed $locale
     * @param mixed $host
     * @param mixed $route
     */
    public function testGenerateThrowsException($locale, $host, $route)
    {
        $this->expectException(RouteNotFoundException::class);

        $router = $this->getNonRedirectingHostMapRouter();
        $context = new RequestContext();
        $context->setParameter('_locale', $locale);
        $context->setHost($host);
        $router->setContext($context);

        $router->generate($route);
    }

    public function getGenerateThrowsExceptionFixtures()
    {
        return [
            ['en_UK', 'uk.tests', 'dutch_only'],
            ['en_US', 'us.tests', 'enUK_only'],
        ];
    }

    public function testMatchThrowsResourceNotFoundWhenRouteIsUsedByMultipleLocalesOnDifferentHost()
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('The route "sub_locale" is not available on the current host "us.test", but only on these hosts "uk.test, nl.test, be.test".');

        $router = $this->getNonRedirectingHostMapRouter();

        $context = $router->getContext();
        $context->setParameter('_locale', 'en_US');

        $router->match('/english');
    }

    public function testMatchThrowsNotAcceptableLanguageWhenRouteIsUsedByMultipleOtherLocalesOnSameHost()
    {
        $this->expectException(NotAcceptableLanguageException::class);
        $this->expectExceptionMessage('The requested language "en_US" was not available. Available languages: "en_UK, nl_NL, nl_BE"');

        $router = $this->getNonRedirectingHostMapRouter();
        $router->setHostMap([
            'en_US' => 'foo.com',
            'en_UK' => 'foo.com',
            'nl_NL' => 'nl.test',
            'nl_BE' => 'be.test',
        ]);

        $context = $router->getContext();
        $context->setParameter('_locale', 'en_US');

        $router->match('/english');
    }

    public function testMatchCallsLocaleResolverIfRouteSupportsMultipleLocalesAndContextHasNoLocale()
    {
        $localeResolver = $this->createMock('JMS\I18nRoutingBundle\Router\LocaleResolverInterface');

        $router = $this->getRouter('routing.yml', null, $localeResolver);
        $context = $router->getContext();
        $context->setParameter('_locale', null);

        $ref = new \ReflectionProperty($router, 'container');
        $ref->setAccessible(true);
        $container = $ref->getValue($router);
        $request = Request::create('/');

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $requestStack->push($request);
        $container->set('request_stack', $requestStack);

        $localeResolver->expects($this->once())
            ->method('resolveLocale')
            ->with($request, ['en', 'de', 'fr'])
            ->willReturn('de');

        $params = $router->match('/');
        self::assertSame('de', $params['_locale']);
    }

    private function getRouter($config = 'routing.yml', $translator = null, $localeResolver = null)
    {
        $container = new Container();
        $container->set('routing.loader', new YamlFileLoader(new FileLocator(__DIR__ . '/Fixture')));

        if (null === $translator) {
            $translator = new Translator('en');
            $translator->setFallbackLocales(['en']);
            $translator->addLoader('yml', new TranslationLoader());
            $translator->addResource('yml', __DIR__ . '/Fixture/routes.de.yml', 'de', 'routes');
            $translator->addResource('yml', __DIR__ . '/Fixture/routes.en.yml', 'en', 'routes');
        }

        $container->set('i18n_loader', new I18nLoader(new DefaultRouteExclusionStrategy(), new DefaultPatternGenerationStrategy('custom', $translator, ['en', 'de', 'fr'], sys_get_temp_dir())));

        $router = new I18nRouter($container, $config);
        $router->setI18nLoaderId('i18n_loader');
        $router->setDefaultLocale('en');

        if (null !== $localeResolver) {
            $router->setLocaleResolver($localeResolver);
        }

        return $router;
    }

    /**
     * Gets the translator required for checking the DoubleLocale tests (en_UK etc)
     *
     * @param mixed $config
     */
    private function getNonRedirectingHostMapRouter($config = 'routing.yml')
    {
        $container = new Container();
        $container->set('routing.loader', new YamlFileLoader(new FileLocator(__DIR__ . '/Fixture')));

        $translator = new Translator('en_UK');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('yml', new TranslationLoader());
        $translator->addResource('yml', __DIR__ . '/Fixture/routes.en_UK.yml', 'en_UK', 'routes');
        $translator->addResource('yml', __DIR__ . '/Fixture/routes.en_US.yml', 'en_US', 'routes');
        $translator->addResource('yml', __DIR__ . '/Fixture/routes.nl.yml', 'nl', 'routes');
        $translator->addResource('yml', __DIR__ . '/Fixture/routes.en.yml', 'en', 'routes');

        $container->set('i18n_loader', new I18nLoader(new DefaultRouteExclusionStrategy(), new DefaultPatternGenerationStrategy('custom', $translator, ['en_UK', 'en_US', 'nl_NL', 'nl_BE'], sys_get_temp_dir(), 'routes', 'en_UK')));

        $router = new I18nRouter($container, $config);
        $router->setRedirectToHost(false);
        $router->setI18nLoaderId('i18n_loader');
        $router->setDefaultLocale('en_UK');
        $router->setHostMap([
            'en_UK' => 'uk.test',
            'en_US' => 'us.test',
            'nl_NL' => 'nl.test',
            'nl_BE' => 'be.test',
        ]);

        return $router;
    }
}
