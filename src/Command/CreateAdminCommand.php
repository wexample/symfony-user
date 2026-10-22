<?php

namespace Wexample\SymfonyUser\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Service\AccountDirectoryService;

/**
 * Opens the application's own administrator account, or makes an existing one
 * an administrator: no page offers it, so that an administrator is never a
 * visitor's doing. The password is asked, never passed as an argument, where
 * the shell history and the process list would keep it.
 */
#[AsCommand(name: 'user:create-admin', description: 'Creates an administrator account, or makes an existing one an administrator.')]
class CreateAdminCommand extends Command
{
    private const string ARGUMENT_EMAIL = 'email';
    private const string OPTION_NO_MAIL_CODE = 'no-mail-code';
    // Held to in production only: a machine of one's own works with throwaway
    // passwords.
    private const int PASSWORD_MIN_LENGTH = 12;

    public function __construct(
        private readonly AccountDirectoryService $directory,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_EMAIL, InputArgument::REQUIRED, 'The address the administrator signs in with')
            ->addOption(
                self::OPTION_NO_MAIL_CODE,
                null,
                InputOption::VALUE_NONE,
                'No code sent by mail at sign-in, for an application that sends none yet; an authenticator app can be set up afterwards'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument(self::ARGUMENT_EMAIL);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('"%s" is no email address.', $email));

            return Command::INVALID;
        }

        $password = (string) $io->askHidden('Password');
        if ('prod' === $this->environment && mb_strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $io->error(sprintf('A password of %d characters at least.', self::PASSWORD_MIN_LENGTH));

            return Command::INVALID;
        }

        if ($password !== $io->askHidden('Password, again')) {
            $io->error('The two passwords differ.');

            return Command::INVALID;
        }

        $account = $this->directory->findByIdentifier($email);
        $created = null === $account;

        if ($created) {
            $class = $this->directory->getUserClass();
            $account = (new $class())->setEmail($email);
            $this->entityManager->persist($account);
        }

        $account
            ->setRoles(array_values(array_unique([...$account->getRoles(), RoleHelper::ROLE_ADMIN])))
            ->setEnabled(true)
            ->setLocked(false)
            ->setPassword($this->passwordHasher->hashPassword($account, $password));

        if ($input->getOption(self::OPTION_NO_MAIL_CODE)) {
            $account->setEmailTwoFactorEnabled(false);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%s is %s administrator.', $email, $created ? 'a new' : 'now an'));

        return Command::SUCCESS;
    }
}
