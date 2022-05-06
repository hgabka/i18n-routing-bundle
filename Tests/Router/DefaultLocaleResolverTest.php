<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use JMS\I18nRoutingBundle\Router\DefaultLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversNothing
 */
class DefaultLocaleResolverTest extends TestCase
{
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DefaultLocaleResolver('hl', [
            'foo' => 'en',
            'bar' => 'de',
        ]);
    }

    /**
     * @dataProvider getResolutionTests
     *
     * @param mixed $expected
     * @param mixed $message
     */
    public function testResolveLocale(Request $request, array $locales, $expected, $message)
    {
        self::assertSame($expected, $this->resolver->resolveLocale($request, $locales), $message);
    }

    public function getResolutionTests()
    {
        $tests = [];

        $tests[] = [Request::create('http://foo/?hl=de'), ['foo'], 'en', 'Host has precedence before query parameter'];
        $tests[] = [Request::create('/?hl=de'), ['foo'], 'de', 'Query parameter is selected'];
        $tests[] = [Request::create('/?hl=de', 'GET', [], ['hl' => 'en']), ['foo'], 'de', 'Query parameter has precedence before cookie'];

        $session = $this->createMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->expects($this->any())
            ->method('has')
            ->with('_locale')
            ->willReturn(true);
        $session->expects($this->any())
            ->method('get')
            ->with('_locale')
            ->willReturn('fr');
        $session->expects($this->any())
            ->method('getName')
            ->willReturn('SESS');

        $tests[] = [$request = Request::create('/?hl=de', 'GET', [], ['SESS' => 'foo']), ['foo'], 'de', 'Query parameter has precedence before session'];
        $request->setSession($session);

        $tests[] = [$request = Request::create('/', 'GET', [], ['SESS' => 'foo']), ['foo'], 'fr', 'Session is used'];
        $request->setSession($session);

        $tests[] = [$request = Request::create('/', 'GET', [], ['hl' => 'es', 'SESS' => 'foo']), ['foo'], 'fr', 'Session has precedence before cookie.'];
        $request->setSession($session);

        $tests[] = [Request::create('/', 'GET', [], ['hl' => 'es']), ['foo'], 'es', 'Cookie is used'];
        $tests[] = [Request::create('/', 'GET', [], ['hl' => 'es'], [], ['HTTP_ACCEPT_LANGUAGE' => 'dk;q=0.5']), ['dk'], 'es', 'Cookie has precedence before Accept-Language header.'];
        $tests[] = [Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'dk;q=0.5']), ['es', 'dk'], 'dk', 'Accept-Language header is used.'];
        $tests[] = [Request::create('/'), ['foo'], null, 'When Accept-Language header is used, and no locale matches, null is returned'];
        $tests[] = [Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => '']), ['foo'], null, 'Returns null if no method could be used'];

        return $tests;
    }
}
