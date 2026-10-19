<?php

namespace JMS\I18nRoutingBundle\Tests\EventListener;

use JMS\I18nRoutingBundle\EventListener\CookieSettingListener;
use JMS\I18nRoutingBundle\EventListener\LocaleChoosingListener;
use JMS\I18nRoutingBundle\Router\DefaultLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class ListenersTest extends TestCase
{
    private function responseEvent(Request $request, Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $type, $response);
    }

    private function cookieListener(): CookieSettingListener
    {
        return new CookieSettingListener('hl', 3600, '/', 'example.com', true, true);
    }

    public function testCookieIsSetWhenMissing(): void
    {
        $request = Request::create('/');
        $request->setLocale('de');
        $response = new Response();

        $this->cookieListener()->onKernelResponse($this->responseEvent($request, $response));

        $cookie = $response->headers->getCookies()[0];
        $this->assertSame(['hl', 'de', '/', 'example.com', true, true], [$cookie->getName(), $cookie->getValue(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly()]);
        $this->assertGreaterThan(time(), $cookie->getExpiresTime());
    }

    public function testCookieIsRefreshedOnlyWhenTheLocaleChanged(): void
    {
        $request = Request::create('/', 'GET', [], ['hl' => 'de']);
        $request->setLocale('de');
        $response = new Response();
        $this->cookieListener()->onKernelResponse($this->responseEvent($request, $response));
        $this->assertSame([], $response->headers->getCookies());

        $request->setLocale('en');
        $this->cookieListener()->onKernelResponse($this->responseEvent($request, $response));
        $this->assertSame('en', $response->headers->getCookies()[0]->getValue());
    }

    public function testCookieIsNotSetOnErrorsOrSubRequests(): void
    {
        $listener = $this->cookieListener();
        $request = Request::create('/');

        $error = new Response('', 500);
        $listener->onKernelResponse($this->responseEvent($request, $error));
        $this->assertSame([], $error->headers->getCookies());

        $sub = new Response();
        $listener->onKernelResponse($this->responseEvent($request, $sub, HttpKernelInterface::SUB_REQUEST));
        $this->assertSame([], $sub->headers->getCookies());

        $redirect = new RedirectResponse('/x');
        $listener->onKernelResponse($this->responseEvent($request, $redirect));
        $this->assertCount(1, $redirect->headers->getCookies());
    }

    private function exceptionEvent(Request $request, \Throwable $exception, int $type = HttpKernelInterface::MAIN_REQUEST): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, $type, $exception);
    }

    private function homepageNotFound(): NotFoundHttpException
    {
        return new NotFoundHttpException('nope', new ResourceNotFoundException());
    }

    public function testRootRequestIsRedirectedToTheResolvedLocale(): void
    {
        $listener = new LocaleChoosingListener('en', ['en', 'de'], new DefaultLocaleResolver('hl'));
        $request = Request::create('/', 'GET', ['hl' => 'de', 'page' => '2']);
        $event = $this->exceptionEvent($request, $this->homepageNotFound());

        $listener->onKernelException($event);

        $this->assertSame('/de/?page=2', $event->getResponse()->headers->get('Location'));
        $this->assertSame('de', $request->getLocale());
    }

    public function testDefaultLocaleIsUsedWhenNothingIsResolved(): void
    {
        $listener = new LocaleChoosingListener('en', ['en', 'de'], new DefaultLocaleResolver('hl'));
        $event = $this->exceptionEvent(Request::create('/'), $this->homepageNotFound());

        $listener->onKernelException($event);

        $this->assertSame('/en/', $event->getResponse()->headers->get('Location'));
    }

    public function testOtherExceptionsAndPathsAreLeftAlone(): void
    {
        $listener = new LocaleChoosingListener('en', ['en', 'de'], new DefaultLocaleResolver('hl'));

        $events = [
            $this->exceptionEvent(Request::create('/contact'), $this->homepageNotFound()),
            $this->exceptionEvent(Request::create('/'), new NotFoundHttpException('plain')),
            $this->exceptionEvent(Request::create('/'), new \RuntimeException('boom')),
            $this->exceptionEvent(Request::create('/'), $this->homepageNotFound(), HttpKernelInterface::SUB_REQUEST),
        ];
        foreach ($events as $event) {
            $listener->onKernelException($event);
            $this->assertNull($event->getResponse());
        }
    }
}
