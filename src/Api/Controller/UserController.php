<?php

namespace Wexample\SymfonyUser\Api\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyApi\Api\Attribute\ApiResponseData;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\PageQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SearchQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SortQueryOption;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonyUser\Api\Dto\UserDto;
use Wexample\SymfonyUser\Api\Normalizer\Entity\User\DefaultUserNormalizer;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Service\AccountDirectoryService;

/**
 * The accounts the administration list shows, searched, sorted and paged.
 * Absent where the application declared no `administration.page_role`,
 * refused to whoever lacks it — as the accounts pages are. Read by the
 * pages on the session: under `/_api/` (symfony-api `usage/route-prefixes`).
 */
#[Route(path: '_api/user/', name: 'api_user_')]
class UserController extends AbstractApiController
{
    final public const string ROUTE_LIST = 'list';

    #[Route(path: 'list', name: self::ROUTE_LIST, methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET, options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    #[PageQueryOption]
    #[LengthQueryOption]
    #[SearchQueryOption]
    #[SortQueryOption(allowed: ['email', 'username', 'dateLastLogin'], default: 'email', emptyLast: true)]
    #[ApiResponseData(UserDto::class, paginated: true)]
    public function list(
        Request $request,
        AccountAdministrationService $administration,
        AccountDirectoryService $directory,
        DefaultUserNormalizer $normalizer,
    ): ApiResponse {
        if (! $administration->getPageRole()) {
            throw new NotFoundHttpException();
        }

        if (! $administration->canAdminister()) {
            throw $this->createAccessDeniedException();
        }

        $builder = $directory->createQuery();
        $directory->applySearch($builder, (string) self::getQueryOptionValue($request, VariableHelper::SEARCH, ''));

        return self::apiResponseQueryPage(
            $request,
            $builder,
            static fn (array $users) => $normalizer->normalizeCollection($users),
        );
    }
}
