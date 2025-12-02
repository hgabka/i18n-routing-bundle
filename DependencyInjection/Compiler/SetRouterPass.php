<?php

namespace JMS\I18nRoutingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Changes the Router implementation.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SetRouterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setAlias('router', 'jms_i18n_routing.router')->setPublic(true);

        $translatorDef = $container->findDefinition('translator');
        if ('%translator.identity.class%' === $translatorDef->getClass()) {
            throw new \RuntimeException('The JMSI18nRoutingBundle requires Symfony2\'s translator to be enabled. Please make sure to un-comment the respective section in the framework config.');
        }
    }
}
