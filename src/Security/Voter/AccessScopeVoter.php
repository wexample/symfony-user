<?php

namespace Wexample\SymfonyUser\Security\Voter;

use LogicException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyHelpers\Attribute\WorkerSafe;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;
use Wexample\SymfonyUser\Attribute\AccessScope;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;

/**
 * Votes on the records carrying #[AccessScope], as it declares; abstains on
 * the others.
 */
#[WorkerSafe('The scopes memoized are read from the code, the same for every request.')]
class AccessScopeVoter extends Voter
{
    private const array ATTRIBUTES = [
        AbstractEntityVoter::VIEW,
        AbstractEntityVoter::EDIT,
        AbstractEntityVoter::DELETE,
    ];

    private readonly PropertyAccessorInterface $accessor;

    /**
     * @var array<class-string, AccessScope|false>
     */
    private array $scopes = [];

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        #[Autowire(param: 'wexample_symfony_user.memberships.access_scope.view_grant')]
        private readonly ?string $viewGrant = null,
        #[Autowire(param: 'wexample_symfony_user.memberships.access_scope.edit_grant')]
        private readonly ?string $editGrant = null,
        #[Autowire(param: 'wexample_symfony_user.memberships.oversee_role')]
        private readonly ?string $overseeRole = null,
    ) {
        $this->accessor = PropertyAccess::createPropertyAccessorBuilder()
            ->disableExceptionOnInvalidPropertyPath()
            ->getPropertyAccessor();
    }

    protected function supports(
        string $attribute,
        mixed $subject
    ): bool {
        return in_array($attribute, self::ATTRIBUTES, true)
            && is_object($subject)
            && null !== $this->findScope($subject::class);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        $user = $token->getUser();

        if (! $user instanceof UserInterface) {
            return false;
        }

        $scope = $this->findScope($subject::class);

        if (null !== $scope->owner
            && ($owner = $this->read($subject, $scope->owner)) instanceof UserInterface
            && $owner->getUserIdentifier() === $user->getUserIdentifier()) {
            return true;
        }

        if (null !== $scope->group && ($group = $this->read($subject, $scope->group)) instanceof MembershipGroupInterface) {
            $grant = AbstractEntityVoter::VIEW === $attribute ? $this->viewGrant : $this->editGrant;

            if (null === $grant) {
                throw new LogicException(sprintf('%s is scoped by its group: set wexample_symfony_user.memberships.access_scope.', $subject::class));
            }

            return $this->accessDecisionManager->decide($token, [$grant], $group);
        }

        foreach ($scope->parents as $path) {
            if (is_object($parent = $this->read($subject, $path))) {
                return $this->accessDecisionManager->decide($token, [$attribute], $parent);
            }
        }

        if ($scope->catalog && AbstractEntityVoter::VIEW === $attribute) {
            return true;
        }

        $vote?->addReason(sprintf('%s: nothing it hangs from lets the account %s it', $subject::class, $attribute));

        return null !== $this->overseeRole && $this->accessDecisionManager->decide($token, [$this->overseeRole]);
    }

    /**
     * The scope of the class, or of the nearest parent declaring one.
     *
     * @param class-string $class
     */
    private function findScope(string $class): ?AccessScope
    {
        if (! array_key_exists($class, $this->scopes)) {
            $this->scopes[$class] = false;

            for ($reflection = new ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
                if ($attributes = $reflection->getAttributes(AccessScope::class)) {
                    $this->scopes[$class] = $attributes[0]->newInstance();

                    break;
                }
            }
        }

        return $this->scopes[$class] ?: null;
    }

    private function read(
        object $subject,
        string $path
    ): mixed {
        return $this->accessor->getValue($subject, $path);
    }
}
