<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use JMS\I18nRoutingBundle\Router\DefaultPatternGenerationStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class DefaultPatternGenerationStrategyTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/hg_i18n_' . bin2hex(random_bytes(4));
        mkdir($this->cacheDir . '/translations', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . '/translations/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->cacheDir . '/translations');
        @rmdir($this->cacheDir);
    }

    private function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['contact' => '/kontakt'], 'de', 'routes');
        $translator->addResource('array', ['contact' => '/kapcsolat'], 'hu', 'routes');

        return $translator;
    }

    private function strategy(string $strategy, array $locales = ['en', 'de', 'hu'], string $default = 'en'): DefaultPatternGenerationStrategy
    {
        return new DefaultPatternGenerationStrategy($strategy, $this->translator(), $locales, $this->cacheDir, 'routes', $default);
    }

    public function testCustomStrategyOnlyUsesTranslatedPatterns(): void
    {
        $patterns = $this->strategy('custom')->generateI18nPatterns('contact', new Route('/contact'));

        $this->assertSame(['/contact' => ['en'], '/kontakt' => ['de'], '/kapcsolat' => ['hu']], $patterns);
    }

    public function testUntranslatedLocalesShareTheOriginalPattern(): void
    {
        $patterns = $this->strategy('custom', ['en', 'fr', 'de'])->generateI18nPatterns('contact', new Route('/contact'));

        $this->assertSame(['/contact' => ['en', 'fr'], '/kontakt' => ['de']], $patterns);
    }

    public function testPrefixStrategyPrefixesEveryLocale(): void
    {
        $patterns = $this->strategy('prefix')->generateI18nPatterns('contact', new Route('/contact'));

        $this->assertSame(['/en/contact' => ['en'], '/de/kontakt' => ['de'], '/hu/kapcsolat' => ['hu']], $patterns);
    }

    public function testPrefixExceptDefaultSkipsTheDefaultLocale(): void
    {
        $patterns = $this->strategy('prefix_except_default')->generateI18nPatterns('contact', new Route('/contact'));

        $this->assertSame(['/contact' => ['en'], '/de/kontakt' => ['de'], '/hu/kapcsolat' => ['hu']], $patterns);
    }

    public function testRouteCanRestrictLocalesAndAddAPrefix(): void
    {
        $route = new Route('/contact', [], [], ['i18n_locales' => ['de'], 'i18n_prefix' => '/api']);

        $patterns = $this->strategy('prefix')->generateI18nPatterns('contact', $route);

        $this->assertSame(['/api/de/kontakt' => ['de']], $patterns);
    }

    public function testResourcesOfCachedCataloguesAreAdded(): void
    {
        $resource = new \Symfony\Component\Config\Resource\FileResource(__FILE__);
        file_put_contents($this->cacheDir . '/translations/catalogue.de.php.meta', serialize([$resource]));

        $collection = new RouteCollection();
        $this->strategy('custom')->addResources($collection);

        $this->assertCount(1, $collection->getResources());
        $this->assertSame(__FILE__, (string) $collection->getResources()[0]);
    }

    public function testAddResourcesWithoutCacheIsANoOp(): void
    {
        $collection = new RouteCollection();
        $this->strategy('custom')->addResources($collection);

        $this->assertSame([], $collection->getResources());
    }
}
