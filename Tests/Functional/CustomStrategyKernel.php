<?php

namespace JMS\I18nRoutingBundle\Tests\Functional;

use Symfony\Component\Config\Loader\LoaderInterface;

class CustomStrategyKernel extends AbstractKernel
{
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/config/strategy_custom_with_hosts.yml');
    }
}
