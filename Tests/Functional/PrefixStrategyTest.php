<?php


namespace JMS\I18nRoutingBundle\Tests\Functional;

/**
 * @coversNothing
 */
class PrefixStrategyTest extends BaseTestCase
{
    protected static $class = PrefixStrategyKernel::class;

    /**
     * @dataProvider getLocaleChoosingTests
     *
     * @param mixed $acceptLanguages
     * @param mixed $expectedLocale
     */
    public function testLocaleIsChoosenWhenHomepageIsRequested($acceptLanguages, $expectedLocale)
    {
        $client = self::createClient([], [
            'HTTP_ACCEPT_LANGUAGE' => $acceptLanguages,
        ]);
        $client->insulate();

        $client->request('GET', '/?extra=params');

        self::assertTrue($client->getResponse()->isRedirect('/' . $expectedLocale . '/?extra=params'), (string) $client->getResponse());
    }

    public function getLocaleChoosingTests()
    {
        return [
            ['en-us,en;q=0.5', 'en'],
            ['de-de,de;q=0.8,en-us;q=0.5,en;q=0.3', 'de'],
            ['fr;q=0.5', 'en'],
        ];
    }

    public function testLanguageCookieIsSet()
    {
        $client = self::createClient([]);
        $client->insulate();

        $client->request('GET', '/?hl=de');

        $response = $client->getResponse();
        self::assertTrue($response->isRedirect('/de/'), (string) $response);

        $cookies = $response->headers->getCookies();
        self::assertSame(1, count($cookies));
        self::assertSame('de', $cookies[0]->getValue());
    }

    public function testNoCookieOnError()
    {
        $client = self::createClient([]);
        $client->insulate();

        $client->request('GET', '/nonexistent');

        $response = $client->getResponse();
        self::assertTrue($response->isClientError(), (string) $response);

        $cookies = $response->headers->getCookies();
        self::assertSame(0, count($cookies));
    }
}
