<?php


namespace JMS\I18nRoutingBundle\Tests\Functional\TestBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class DefaultController
{
    /**
     * @Route("/", name = "homepage")
     * @Template
     */
    public function indexAction(Request $request)
    {
        $locale = method_exists($request, 'getLocale') ? $request->getLocale()
            : $request->getSession()->getLocale();

        return ['locale' => $locale];
    }
}
