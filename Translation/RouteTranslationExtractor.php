<?php


namespace JMS\I18nRoutingBundle\Translation;

use JMS\I18nRoutingBundle\Router\I18nRouter;
use JMS\I18nRoutingBundle\Router\RouteExclusionStrategyInterface;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\ExtractorInterface;
use Symfony\Component\Routing\RouterInterface;

class RouteTranslationExtractor implements ExtractorInterface
{
    private $router;
    private $routeExclusionStrategy;
    private $domain = 'routes';

    public function __construct(RouterInterface $router, RouteExclusionStrategyInterface $routeExclusionStrategy)
    {
        $this->router = $router;
        $this->routeExclusionStrategy = $routeExclusionStrategy;
    }

    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    public function extract()
    {
        $catalogue = new MessageCatalogue();

        $collection = $this->router instanceof I18nRouter ? $this->router->getOriginalRouteCollection()
            : $this->router->getRouteCollection();

        foreach ($collection->all() as $name => $route) {
            if ($this->routeExclusionStrategy->shouldExcludeRoute($name, $route)) {
                continue;
            }

            $message = new Message($name, $this->domain);
            $message->setDesc($route->getPath());
            $catalogue->add($message);
        }

        return $catalogue;
    }
}
