<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Every user belongs to organization 1 only; a refusal names the one asked.
 */
class OrganizationVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'ORGANIZATION_VIEW';
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($subject === '1') {
            return true;
        }

        $vote?->addReason('organization=' . $subject);

        return false;
    }
}
