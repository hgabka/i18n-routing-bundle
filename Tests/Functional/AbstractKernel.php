<?php


namespace JMS\I18nRoutingBundle\Tests\Functional;

require_once __DIR__ . '/../bootstrap.php';

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

abstract class AbstractKernel extends Kernel
{
    private $config;

    public function registerBundles()
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new \JMS\I18nRoutingBundle\Tests\Functional\TestBundle\TestBundle(),
            new \JMS\I18nRoutingBundle\JMSI18nRoutingBundle(),
        ];
    }

    abstract public function registerContainerConfiguration(LoaderInterface $loader);

    public function getProjectDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir() . '/JMSI18nRoutingBundle/cache';
    }

    public function getLogDir()
    {
        return sys_get_temp_dir() . '/JMSI18nRoutingBundle/logs';
    }

    public function serialize()
    {
        return serialize([$this->config]);
    }

    public function unserialize($str)
    {
        call_user_func_array([$this, '__construct'], unserialize($str));
    }
}
