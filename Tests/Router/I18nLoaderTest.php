<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use JMS\I18nRoutingBundle\Router\DefaultPatternGenerationStrategy;
use JMS\I18nRoutingBundle\Router\DefaultRouteExclusionStrategy;
use JMS\I18nRoutingBundle\Router\I18nLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * @coversNothing
 */
class I18nLoaderTest extends TestCase
{
    public function testLoad()
    {
        $col = new RouteCollection();
        $col->add('contact', new Route('/contact'));
        $i18nCol = $this->getLoader()->load($col);

        self::assertSame(2, count($i18nCol->all()));

        $de = $i18nCol->get('de__RG__contact');
        self::assertSame('/kontakt', $de->getPath());
        self::assertSame('de', $de->getDefault('_locale'));

        $en = $i18nCol->get('en__RG__contact');
        self::assertSame('/contact', $en->getPath());
        self::assertSame('en', $en->getDefault('_locale'));
    }

    public function testLoadDoesNotRemoveOriginalIfNotAllRoutesHaveTranslationsUnlessRedirectIsOff()
    {
        $col = new RouteCollection();
        $col->add('support', new Route('/support'));
        $i18nCol = $this->getLoader('custom')->load($col);

        self::assertSame(3, count($i18nCol->all()));

        $de = $i18nCol->get('de__RG__support');
        self::assertSame('/support', $de->getPath());

        $en = $i18nCol->get('en__RG__support');
        self::assertSame('/support', $en->getPath());
    }

    /**
     * @dataProvider getStrategies
     *
     * @param mixed $strategy
     */
    public function testLoadDoesNotAddI18nRoutesIfI18nIsFalse($strategy)
    {
        $col = new RouteCollection();
        $col->add('route', new Route('/no-i18n', [], [], ['i18n' => false]));
        $i18nCol = $this->getLoader($strategy)->load($col);

        self::assertSame(1, count($i18nCol->all()));
        self::assertNull($i18nCol->get('route')->getDefault('_locale'));
    }

    public function testLoadUsesOriginalTranslationIfNoTranslationExists()
    {
        $col = new RouteCollection();
        $col->add('untranslated_route', new Route('/not-translated'));
        $i18nCol = $this->getLoader()->load($col);

        self::assertSame(3, count($i18nCol->all()));
        self::assertSame('/not-translated', $i18nCol->get('de__RG__untranslated_route')->getPath());
        self::assertSame('/not-translated', $i18nCol->get('en__RG__untranslated_route')->getPath());
    }

    public function testLoadIfRouteIsNotTranslatedToAllLocales()
    {
        $col = new RouteCollection();
        $col->add('route', new Route('/not-available-everywhere', [], [], ['i18n_locales' => ['en']]));
        $i18nCol = $this->getLoader()->load($col);

        self::assertSame(['en__RG__route'], array_keys($i18nCol->all()));
    }

    public function testLoadIfStrategyIsPrefix()
    {
        $col = new RouteCollection();
        $col->add('contact', new Route('/contact'));
        $i18nCol = $this->getLoader('prefix')->load($col);

        self::assertSame(2, count($i18nCol->all()));

        $de = $i18nCol->get('de__RG__contact');
        self::assertSame('/de/kontakt', $de->getPath());

        $en = $i18nCol->get('en__RG__contact');
        self::assertSame('/en/contact', $en->getPath());
    }

    public function testLoadIfStrategyIsPrefixExceptDefault()
    {
        $col = new RouteCollection();
        $col->add('contact', new Route('/contact'));
        $i18nCol = $this->getLoader('prefix_except_default')->load($col);

        self::assertSame(2, count($i18nCol->all()));

        $de = $i18nCol->get('de__RG__contact');
        self::assertSame('/de/kontakt', $de->getPath());

        $en = $i18nCol->get('en__RG__contact');
        self::assertSame('/contact', $en->getPath());
    }

    public function testLoadAddsPrefix()
    {
        $col = new RouteCollection();
        $col->add('dashboard', new Route('/dashboard', [], [], ['i18n_prefix' => '/admin']));
        $i18nCol = $this->getLoader('prefix')->load($col);

        $en = $i18nCol->get('en__RG__dashboard');
        self::assertSame('/admin/en/dashboard', $en->getPath());
    }

    public function getStrategies()
    {
        return [['custom'], ['prefix'], ['prefix_except_default']];
    }

    private function getLoader($strategy = 'custom')
    {
        $translator = new Translator('en');
        $translator->addLoader('yml', new YamlFileLoader());
        $translator->addResource('yml', __DIR__ . '/Fixture/routes.de.yml', 'de', 'routes');
        $translator->addResource('yml', __DIR__ . '/Fixture/routes.en.yml', 'en', 'routes');

        return new I18nLoader(
            new DefaultRouteExclusionStrategy(),
            new DefaultPatternGenerationStrategy($strategy, $translator, ['en', 'de'], sys_get_temp_dir())
        );
    }
}
