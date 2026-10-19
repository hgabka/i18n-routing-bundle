<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use JMS\I18nRoutingBundle\Router\DefaultLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class DefaultLocaleResolverTest extends TestCase
{
    private function request(string $uri = '/', array $query = [], array $cookies = []): Request
    {
        $request = Request::create($uri, 'GET', $query, $cookies);
        $request->headers->remove('Accept-Language'); // Request::create() adds a default one

        return $request;
    }

    private function resolver(array $hostMap = []): DefaultLocaleResolver
    {
        return new DefaultLocaleResolver('hl', $hostMap);
    }

    public function testNothingToGoOnReturnsNull(): void
    {
        $this->assertNull($this->resolver()->resolveLocale($this->request(), ['en', 'de']));
    }

    public function testHostMapWinsOverEverything(): void
    {
        $request = Request::create('http://example.de/?hl=en');

        $this->assertSame('de', $this->resolver(['example.de' => 'de'])->resolveLocale($request, ['en', 'de']));
    }

    public function testUnknownHostIsIgnored(): void
    {
        $request = Request::create('http://other.test/?hl=en');

        $this->assertSame('en', $this->resolver(['example.de' => 'de'])->resolveLocale($request, ['en', 'de']));
    }

    public function testQueryParameterIsUsedWhenValid(): void
    {
        $this->assertSame('de', $this->resolver()->resolveLocale($this->request('/?hl=de'), ['en', 'de']));
        $this->assertSame('pt_BR', $this->resolver()->resolveLocale($this->request('/?hl=pt_BR'), ['en']));
        $this->assertNull($this->resolver()->resolveLocale($this->request('/?hl=../../etc'), ['en', 'de']));
    }

    public function testSessionLocaleIsUsedBeforeCookie(): void
    {
        $request = Request::create('/', 'GET', [], ['hl' => 'en', 'PHPSESSID' => 'x']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_locale', 'de');
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());

        $this->assertSame('de', $this->resolver()->resolveLocale($request, ['en', 'de']));
    }

    public function testCookieIsUsedWhenValid(): void
    {
        $this->assertSame('de', $this->resolver()->resolveLocale($this->request('/', [], ['hl' => 'de']), ['en', 'de']));
        $this->assertNull($this->resolver()->resolveLocale($this->request('/', [], ['hl' => '<script>']), ['en', 'de']));
    }

    public function testAcceptLanguageHeaderPicksFirstAvailableLocale(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'fr, de;q=0.8, en;q=0.5');

        $this->assertSame('de', $this->resolver()->resolveLocale($request, ['en', 'de']));
    }

    public function testAcceptLanguageWithoutAvailableLocaleReturnsNull(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'fr, es');

        $this->assertNull($this->resolver()->resolveLocale($request, ['en', 'de']));
    }
}
