<?php

namespace JMS\I18nRoutingBundle\Tests\Functional;

/**
 * @coversNothing
 */
class CustomStrategyTest extends BaseTestCase
{
    protected static $class = CustomStrategyKernel::class;

    public function testDefaultLocaleIsSetCorrectly()
    {
        $client = self::createClient([], [
            'HTTP_HOST' => 'de.host',
        ]);
        $client->insulate();

        $crawler = $client->request('GET', '/');

        self::assertSame(1, count($locale = $crawler->filter('#locale')), substr($client->getResponse(), 0, 2000));
        self::assertSame('de', $locale->text());
    }
}
