<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use JMS\I18nRoutingBundle\Router\DefaultRouteExclusionStrategy;
use JMS\I18nRoutingBundle\Router\I18nLoader;
use JMS\I18nRoutingBundle\Router\PatternGenerationStrategyInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class I18nLoaderTest extends TestCase
{
    private function loader(array $patternsByRoute): I18nLoader
    {
        $strategy = new class($patternsByRoute) implements PatternGenerationStrategyInterface {
            public function __construct(private array $patterns)
            {
            }

            public function generateI18nPatterns($routeName, Route $route)
            {
                return $this->patterns[$routeName] ?? [];
            }

            public function addResources(RouteCollection $i18nCollection)
            {
                $i18nCollection->addResource(new FileResource(__FILE__));
            }
        };

        return new I18nLoader(new DefaultRouteExclusionStrategy(), $strategy);
    }

    public function testTranslatedRoutesAreExpandedPerLocale(): void
    {
        $collection = new RouteCollection();
        $collection->add('contact', new Route('/contact', ['_controller' => 'c']));

        $result = $this->loader(['contact' => ['/contact' => ['en'], '/kontakt' => ['de']]])->load($collection);

        $this->assertSame(['en__RG__contact', 'de__RG__contact'], array_keys($result->all()));
        $this->assertSame('/kontakt', $result->get('de__RG__contact')->getPath());
        $this->assertSame('de', $result->get('de__RG__contact')->getDefault('_locale'));
        $this->assertSame('c', $result->get('de__RG__contact')->getDefault('_controller'));
        $this->assertSame('/contact', $collection->get('contact')->getPath(), 'the original route must not be modified');
    }

    public function testSharedPatternAddsACatchAllRouteWithLocales(): void
    {
        $collection = new RouteCollection();
        $collection->add('about', new Route('/about'));

        $result = $this->loader(['about' => ['/about' => ['en', 'de']]])->load($collection);

        $this->assertSame(['en_de__RG__about', 'en__RG__about', 'de__RG__about'], array_keys($result->all()));
        $this->assertSame(['en', 'de'], $result->get('en_de__RG__about')->getDefault('_locales'));
        $this->assertNull($result->get('en__RG__about')->getDefault('_locales'));
    }

    public function testExcludedRoutesAreKeptAsIs(): void
    {
        $collection = new RouteCollection();
        $route = new Route('/_wdt');
        $collection->add('_wdt', $route);

        $result = $this->loader([])->load($collection);

        $this->assertSame(['_wdt'], array_keys($result->all()));
        $this->assertSame($route, $result->get('_wdt'));
    }

    public function testResourcesAreCarriedOver(): void
    {
        $collection = new RouteCollection();
        $collection->addResource(new FileResource(__DIR__ . '/DefaultLocaleResolverTest.php'));

        $result = $this->loader([])->load($collection);

        $this->assertCount(2, $result->getResources());
    }
}
