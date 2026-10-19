<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use JMS\I18nRoutingBundle\Router\DefaultRouteExclusionStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

class DefaultRouteExclusionStrategyTest extends TestCase
{
    #[DataProvider('routes')]
    public function testExclusion(string $name, array $options, bool $expected): void
    {
        $this->assertSame($expected, (new DefaultRouteExclusionStrategy())->shouldExcludeRoute($name, new Route('/x', [], [], $options)));
    }

    public static function routes(): array
    {
        return [
            'plain route is translated' => ['contact', [], false],
            'underscore prefix' => ['_profiler', [], true],
            'i18n false' => ['contact', ['i18n' => false], true],
            'i18n "false" string' => ['contact', ['i18n' => 'false'], true],
            'i18n true' => ['contact', ['i18n' => true], false],
        ];
    }
}
