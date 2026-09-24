<?php

namespace Wexample\SymfonyUser\Tests\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * The fixture kernel runs on an in-memory SQLite database, which is empty at
 * every boot: the schema has to be built before anything is persisted.
 */
trait DatabaseTestTrait
{
    protected function createDatabaseSchema(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema(
            $entityManager->getMetadataFactory()->getAllMetadata()
        );

        return $entityManager;
    }
}
