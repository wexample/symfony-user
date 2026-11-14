<?php

namespace Wexample\SymfonyUser\Helper;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RequestHelper;

/**
 * Sending a request elsewhere, as whoever sent it can follow: a browser by a
 * redirect, a script by the redirect action of a form's answer — a script
 * would otherwise follow the redirect itself, and read a page as its data.
 */
class RedirectResponseHelper
{
    /**
     * @param int $jsonStatus What the script is told of why it is sent away:
     *                        refused (403) or no longer signed in (401).
     */
    public static function redirect(
        Request $request,
        string $url,
        int $jsonStatus
    ): Response {
        if (! RequestHelper::isJsonRequest($request)) {
            return new RedirectResponse($url);
        }

        return new JsonResponse(
            ['ok' => false, 'action' => ['type' => AbstractFormProcessor::ACTION_REDIRECT, 'url' => $url]],
            $jsonStatus
        );
    }
}
