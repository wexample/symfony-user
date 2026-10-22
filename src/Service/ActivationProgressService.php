<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where an account being activated stands: its password, the code of its
 * second factor, the terms. Three screens the security of the application
 * leads through on its own — the firewall, the second factor, the terms gate —
 * and which a tunnel could not hold; what they share is the steps shown above
 * each, so the way still reads as one.
 *
 * Opened by the activation link, closed by the last step.
 */
class ActivationProgressService
{
    public const string STEP_PASSWORD = 'password';
    public const string STEP_CODE = 'code';
    public const string STEP_TERMS = 'terms';

    private const string SESSION_KEY = 'wexample_user_activation';

    private const string TRANSLATION_DOMAIN = 'WexampleSymfonyUserDsBundle.pages.password.new::activation.steps.';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly TermsService $termsService,
    ) {
    }

    public function start(): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, true);
    }

    public function finish(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * The stepper's steps, the one at `$current` being done, or null outside
     * an activation: a password reset, a usual sign-in show none.
     *
     * @return array{steps: list<array{label: string}>, current: int}|null
     */
    public function steps(string $current): ?array
    {
        $session = $this->requestStack->getSession();

        if (! $session->get(self::SESSION_KEY)) {
            return null;
        }

        $names = [self::STEP_PASSWORD, self::STEP_CODE];
        if ($this->termsService->getVersion() !== null) {
            $names[] = self::STEP_TERMS;
        }

        // The last step, once shown, ends the activation: whatever comes after
        // is the application, not its threshold.
        if ($current === end($names)) {
            $this->finish();
        }

        return [
            'steps' => array_map(fn (string $name) => ['label' => $this->translator->trans(self::TRANSLATION_DOMAIN.$name)], $names),
            'current' => (int) array_search($current, $names, true),
        ];
    }
}
