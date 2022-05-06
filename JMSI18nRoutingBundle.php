<?php


namespace JMS\I18nRoutingBundle;

use JMS\I18nRoutingBundle\DependencyInjection\Compiler\SetRouterPass;
use JMS\I18nRoutingBundle\DependencyInjection\JMSI18nRoutingExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * JMSI18nRoutingBundle.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class JMSI18nRoutingBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new SetRouterPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new JMSI18nRoutingExtension();
    }
}
