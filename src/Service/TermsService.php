<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Entity\TermsAcceptance;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Repository\TermsAcceptanceRepository;

/**
 * The terms of use in force, declared by the application
 * (`wexample_symfony_user.terms.version`), and their acceptances.
 */
class TermsService
{
    /**
     * The version this session already found accepted, to spare a query on
     * every request; a new version misses it and asks again.
     */
    private const string SESSION_ACCEPTED = 'wexample_user_terms_accepted';

    public function __construct(
        private readonly TermsAcceptanceRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly SecurityJournalService $journal,
        #[Autowire(param: 'wexample_symfony_user.terms.version')]
        private readonly ?string $version = null,
        #[Autowire(param: 'wexample_symfony_user.terms.text_route')]
        private readonly ?string $textRoute = null,
        #[Autowire(param: 'wexample_symfony_user.terms.text_template')]
        private readonly ?string $textTemplate = null,
    ) {
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getTextRoute(): ?string
    {
        return $this->textRoute;
    }

    public function getTextTemplate(): ?string
    {
        return $this->textTemplate;
    }

    public function mustAccept(AbstractUser $user): bool
    {
        if ($this->version === null) {
            return false;
        }

        $session = $this->requestStack->getSession();
        // The account's id, not its address: a session outliving its account —
        // deleted, then created again under the same address — would carry
        // an acceptance the new account never gave.
        $cacheKey = $user->getId() . '@' . $this->version;

        if ($session->get(self::SESSION_ACCEPTED) === $cacheKey) {
            return false;
        }

        if (! $this->repository->hasAccepted($user, $this->version)) {
            return true;
        }

        $session->set(self::SESSION_ACCEPTED, $cacheKey);

        return false;
    }

    /**
     * Only the version in force can be accepted: an outdated page, or a forged
     * submission, accepts nothing.
     */
    public function accept(AbstractUser $user, string $version): void
    {
        if ($this->version === null || $version !== $this->version) {
            throw new LogicException(sprintf('The terms in force are "%s", not "%s".', $this->version, $version));
        }

        if ($this->repository->hasAccepted($user, $version)) {
            return;
        }

        $request = $this->requestStack->getMainRequest();

        $this->entityManager->persist(new TermsAcceptance(
            $user,
            $version,
            $request?->getClientIp(),
            $request?->headers->get('User-Agent'),
        ));
        $this->entityManager->flush();

        $this->journal->record(SecurityEventType::TERMS_ACCEPTED, $user, extra: ['version' => $version]);
    }
}
