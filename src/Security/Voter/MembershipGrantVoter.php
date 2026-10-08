<?php

namespace Wexample\SymfonyUser\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Wexample\SymfonyUser\Interface\MemberInterface;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;
use Wexample\SymfonyUser\Service\GrantHierarchyService;
use Wexample\SymfonyUser\Service\MembershipService;

/**
 * `is_granted('contributor', $project)`: the signed-in member holds that grant
 * in the group, or one including it. Read from the memberships at each
 * request, so a grant changed applies at the next one, nobody signed out.
 */
class MembershipGrantVoter extends Voter
{
    public function __construct(
        private readonly GrantHierarchyService $grantHierarchy,
        private readonly MembershipService $membershipService,
    ) {
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, MembershipGroupInterface::class, true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof MembershipGroupInterface
            && $this->grantHierarchy->isGrant($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $member = $token->getUser();

        if ($member instanceof MemberInterface && $this->membershipService->holds($member, $attribute, $subject)) {
            return true;
        }

        $vote?->addReason(sprintf('grant %s not held in %s', $attribute, $subject->getMembershipLabel()));

        return false;
    }
}
