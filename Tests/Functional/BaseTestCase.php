<?php


namespace JMS\I18nRoutingBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @coversNothing
 */
class BaseTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $fs = new Filesystem();
        $fs->remove(sys_get_temp_dir() . '/JMSI18nRoutingBundle');
    }
}
