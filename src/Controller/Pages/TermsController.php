<?php

namespace Wexample\SymfonyUser\Controller\Pages;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyUser\Form\TermsAcceptForm;
use Wexample\SymfonyUser\Service\FormProcessor\TermsAcceptFormProcessor;
use Wexample\SymfonyUser\Service\TermsService;
use Wexample\SymfonyUser\Traits\SymfonyUserBundleClassTrait;

/**
 * The terms gate: their text, accept, or leave. No third way.
 */
#[Route(path: '/account/terms', name: 'user_terms_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TermsController extends AbstractPagesController
{
    use SymfonyUserBundleClassTrait;

    public const string ROUTE_INDEX = 'user_terms_index';

    #[Route(path: '', name: 'index')]
    public function index(
        TermsService $termsService,
        TermsAcceptFormProcessor $formProcessor
    ): Response {
        if (! $version = $termsService->getVersion()) {
            throw $this->createNotFoundException();
        }

        return $this->renderPage('index', [
            'version' => $version,
            'text_route' => $termsService->getTextRoute(),
            'text_template' => $termsService->getTextTemplate(),
            'terms_accept_form' => $formProcessor
                ->createForm([TermsAcceptForm::FIELD_VERSION => $version])
                ->createView(),
        ]);
    }
}
