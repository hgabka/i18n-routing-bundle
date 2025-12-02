<?php

namespace JMS\I18nRoutingBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect Controller.
 *
 * Used by the I18nRouter to redirect between different hosts.
 *
 * @license Portions of this code were received from the Symfony2 project under
 *          the MIT license. All other code is subject to the Apache2 license.
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RedirectController
{
    public function redirectAction(Request $request, $path, $host = null, $permanent = false, $scheme = null, $httpPort = 80, $httpsPort = 443)
    {
        if (!$path) {
            return new Response(null, 410);
        }

        if (null === $scheme) {
            $scheme = $request->getScheme();
        }

        $qs = $request->getQueryString();
        if ($qs) {
            $qs = '?' . $qs;
        }

        $port = '';
        if ('http' === $scheme && 80 !== $httpPort) {
            $port = ':' . $httpPort;
        } elseif ('https' === $scheme && 443 !== $httpsPort) {
            $port = ':' . $httpsPort;
        }

        $url = $scheme . '://' . ($host ?: $request->getHost()) . $port . $request->getBaseUrl() . $path . $qs;

        return new RedirectResponse($url, $permanent ? 301 : 302);
    }
}
