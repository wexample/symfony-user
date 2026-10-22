<?php

namespace Wexample\SymfonyUser\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Throwable;
use Wexample\SymfonyUser\Interface\MembershipGroupInterface;
use Wexample\SymfonyUser\Interface\MembershipManagerInterface;

/**
 * The MembershipManagerInterface of the application, by the name a url
 * carries or by the group they manage.
 */
class MembershipManagerRegistry
{
    public function __construct(
        #[AutowireLocator(MembershipManagerInterface::TAG, defaultIndexMethod: 'getName')]
        private readonly ServiceLocator $managers,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $name): ?MembershipManagerInterface
    {
        return $this->managers->has($name) ? $this->managers->get($name) : null;
    }

    public function getForGroup(MembershipGroupInterface $group): ?MembershipManagerInterface
    {
        foreach (array_keys($this->managers->getProvidedServices()) as $name) {
            $manager = $this->managers->get($name);

            if (is_a($group, $manager->getGroupClass())) {
                return $manager;
            }
        }

        return null;
    }

    /**
     * The group of that kind under that id; null for an id that is no id.
     */
    public function findGroup(MembershipManagerInterface $manager, string $id): ?MembershipGroupInterface
    {
        try {
            return $this->entityManager->find($manager->getGroupClass(), $id);
        } catch (Throwable) {
            return null;
        }
    }
}
