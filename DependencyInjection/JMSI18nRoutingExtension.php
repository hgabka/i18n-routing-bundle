<?php

namespace JMS\I18nRoutingBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * DI Extension.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class JMSI18nRoutingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator([__DIR__ . '/../Resources/config']));
        $loader->load('services.xml');

        $container->setParameter('jms_i18n_routing.default_locale', $config['default_locale']);
        $container->setParameter('jms_i18n_routing.locales', $config['locales']);
        $container->setParameter('jms_i18n_routing.catalogue', $config['catalogue']);
        $container->setParameter('jms_i18n_routing.strategy', $config['strategy']);
        $container->setParameter('jms_i18n_routing.redirect_to_host', $config['redirect_to_host']);
        $container->setParameter('jms_i18n_routing.cookie.name', $config['cookie']['name']);

        if ('prefix' === $config['strategy']) {
            $container
                ->getDefinition('jms_i18n_routing.locale_choosing_listener')
                ->setPublic(true)
                ->addTag('kernel.event_listener', ['event' => 'kernel.exception', 'priority' => 128])
            ;
        }

        if ($config['hosts']) {
            $container->setParameter('jms_i18n_routing.hostmap', $config['hosts']);
            $container
                ->getDefinition('jms_i18n_routing.router')
                ->addMethodCall('setHostMap', ['%jms_i18n_routing.hostmap%'])
            ;

            $container
                ->getDefinition('jms_i18n_routing.locale_resolver.default')
                ->addArgument(array_flip($config['hosts']))
            ;
        } elseif ($config['cookie']['enabled']) {
            $container
                ->getDefinition('jms_i18n_routing.cookie_setting_listener')
                ->addArgument($config['cookie']['name'])
                ->addArgument($config['cookie']['lifetime'])
                ->addArgument($config['cookie']['path'])
                ->addArgument($config['cookie']['domain'])
                ->addArgument($config['cookie']['secure'])
                ->addArgument($config['cookie']['httponly'])
                ->setPublic(true)
                ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'priority' => 256])
            ;
        }

        // remove route extractor if JMSTranslationBundle is not enabled to avoid any problems
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['JMSTranslationBundle'])) {
            $container->removeDefinition('jms_i18n_routing.route_translation_extractor');
        }
    }

    public function getAlias(): string
    {
        return 'jms_i18n_routing';
    }
}
