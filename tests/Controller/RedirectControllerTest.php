<?php

namespace JMS\I18nRoutingBundle\Tests\Controller;

use JMS\I18nRoutingBundle\Controller\RedirectController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class RedirectControllerTest extends TestCase
{
    public function testEmptyPathIsGone(): void
    {
        $response = (new RedirectController())->redirectAction(Request::create('/'), '');

        $this->assertSame(410, $response->getStatusCode());
    }

    public function testRedirectsToTheTargetHostKeepingQueryString(): void
    {
        $request = Request::create('http://example.com/kontakt?a=1&b=2');

        $response = (new RedirectController())->redirectAction($request, '/kontakt', 'example.de', true);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://example.de/kontakt?a=1&b=2', $response->getTargetUrl());
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testTemporaryRedirectOnTheSameHostWithExplicitScheme(): void
    {
        $response = (new RedirectController())->redirectAction(Request::create('http://example.com/x'), '/y', null, false, 'https');

        $this->assertSame('https://example.com/y', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testNonDefaultPortsAreKept(): void
    {
        $controller = new RedirectController();
        $request = Request::create('http://example.com/x');

        $this->assertSame('http://example.de:8080/x', $controller->redirectAction($request, '/x', 'example.de', false, 'http', 8080)->getTargetUrl());
        $this->assertSame('https://example.de:8443/x', $controller->redirectAction($request, '/x', 'example.de', false, 'https', 80, 8443)->getTargetUrl());
    }
}
