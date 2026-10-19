<?php

namespace JMS\I18nRoutingBundle\Tests\DependencyInjection;

use JMS\I18nRoutingBundle\DependencyInjection\Compiler\SetRouterPass;
use JMS\I18nRoutingBundle\DependencyInjection\Configuration;
use JMS\I18nRoutingBundle\DependencyInjection\JMSI18nRoutingExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ConfigurationAndExtensionTest extends TestCase
{
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    private function load(array $config, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        (new JMSI18nRoutingExtension())->load([$config], $container);

        return $container;
    }

    public function testDefaults(): void
    {
        $config = $this->process(['default_locale' => 'en', 'locales' => ['en', 'de']]);

        $this->assertSame('custom', $config['strategy']);
        $this->assertSame('routes', $config['catalogue']);
        $this->assertTrue($config['redirect_to_host']);
        $this->assertSame(['enabled' => true, 'name' => 'hl', 'lifetime' => 31536000], array_intersect_key($config['cookie'], array_flip(['enabled', 'name', 'lifetime'])));
    }

    public function testLocalesCanBeACommaSeparatedString(): void
    {
        $this->assertSame(['en', 'de', 'hu'], $this->process(['default_locale' => 'en', 'locales' => 'en, de ,hu'])['locales']);
    }

    public function testDeprecatedUseCookieIsMappedToCookieEnabled(): void
    {
        $config = $this->process(['default_locale' => 'en', 'locales' => ['en'], 'use_cookie' => false]);

        $this->assertFalse($config['cookie']['enabled']);
    }

    public function testDefaultLocaleMustBeAConfiguredLocale(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['default_locale' => 'fr', 'locales' => ['en', 'de']]);
    }

    public function testEveryLocaleNeedsAHostWhenHostsAreUsed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['default_locale' => 'en', 'locales' => ['en', 'de'], 'hosts' => ['en' => 'example.com']]);
    }

    public function testHostsMustBeUnique(): void
    {
        $this->expectException(\Exception::class);
        $this->process(['default_locale' => 'en', 'locales' => ['en', 'de'], 'hosts' => ['en' => 'example.com', 'de' => 'example.com']]);
    }

    public function testStrategyMustBeKnown(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['default_locale' => 'en', 'locales' => ['en'], 'strategy' => 'magic']);
    }

    public function testParametersAreSet(): void
    {
        $container = $this->load(['default_locale' => 'de', 'locales' => ['en', 'de'], 'strategy' => 'prefix_except_default', 'catalogue' => 'r']);

        $this->assertSame('de', $container->getParameter('jms_i18n_routing.default_locale'));
        $this->assertSame(['en', 'de'], $container->getParameter('jms_i18n_routing.locales'));
        $this->assertSame('prefix_except_default', $container->getParameter('jms_i18n_routing.strategy'));
        $this->assertSame('r', $container->getParameter('jms_i18n_routing.catalogue'));
        $this->assertSame('hl', $container->getParameter('jms_i18n_routing.cookie.name'));
    }

    public function testCookieListenerIsEnabledByDefaultButNotWithHosts(): void
    {
        $withCookie = $this->load(['default_locale' => 'en', 'locales' => ['en', 'de']]);
        $this->assertArrayHasKey('kernel.event_listener', $withCookie->getDefinition('jms_i18n_routing.cookie_setting_listener')->getTags());
        $this->assertCount(6, $withCookie->getDefinition('jms_i18n_routing.cookie_setting_listener')->getArguments());

        $withHosts = $this->load(['default_locale' => 'en', 'locales' => ['en', 'de'], 'hosts' => ['en' => 'example.com', 'de' => 'example.de']]);
        $this->assertSame([], $withHosts->getDefinition('jms_i18n_routing.cookie_setting_listener')->getTags());
        $this->assertSame(['example.com' => 'en', 'example.de' => 'de'], $withHosts->getDefinition('jms_i18n_routing.locale_resolver.default')->getArgument(1));
        $this->assertSame('setHostMap', $withHosts->getDefinition('jms_i18n_routing.router')->getMethodCalls()[array_key_last($withHosts->getDefinition('jms_i18n_routing.router')->getMethodCalls())][0]);
    }

    public function testLocaleChoosingListenerOnlyForPrefixStrategy(): void
    {
        $prefix = $this->load(['default_locale' => 'en', 'locales' => ['en', 'de'], 'strategy' => 'prefix']);
        $custom = $this->load(['default_locale' => 'en', 'locales' => ['en', 'de']]);

        $this->assertSame(128, $prefix->getDefinition('jms_i18n_routing.locale_choosing_listener')->getTag('kernel.event_listener')[0]['priority']);
        $this->assertSame([], $custom->getDefinition('jms_i18n_routing.locale_choosing_listener')->getTags());
    }

    public function testRouteExtractorIsRemovedWithoutJmsTranslationBundle(): void
    {
        $config = ['default_locale' => 'en', 'locales' => ['en']];

        $this->assertFalse($this->load($config)->hasDefinition('jms_i18n_routing.route_translation_extractor'));
        $this->assertTrue($this->load($config, ['JMSTranslationBundle' => 'x'])->hasDefinition('jms_i18n_routing.route_translation_extractor'));
    }

    public function testAlias(): void
    {
        $this->assertSame('jms_i18n_routing', (new JMSI18nRoutingExtension())->getAlias());
    }

    public function testSetRouterPassReplacesTheRouterAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('translator', new Definition('Symfony\\Component\\Translation\\Translator'));
        $container->setDefinition('jms_i18n_routing.router', new Definition('stdClass'));

        (new SetRouterPass())->process($container);

        $this->assertSame('jms_i18n_routing.router', (string) $container->getAlias('router'));
        $this->assertTrue($container->getAlias('router')->isPublic());
    }

    public function testSetRouterPassRequiresARealTranslator(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('translator', new Definition('%translator.identity.class%'));

        $this->expectException(\RuntimeException::class);
        (new SetRouterPass())->process($container);
    }
}
