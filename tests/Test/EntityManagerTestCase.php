<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

/**
 * The tables of the test entities are temporary, so they only exist for the connection of the test.
 */
abstract class EntityManagerTestCase extends ConnectionTestCase
{
    protected EntityManager $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $configuration = ORMSetup::createAttributeMetadataConfig(
            paths: [__DIR__.'/Entity'],
            isDevMode: true,
        );
        $configuration->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager($this->connection, $configuration);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TEMPORARY TABLE project (
                project_id VARCHAR(255) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL
            )
            SQL);
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO project (project_id, name)
            VALUES
                ('3a3fe751-b621-4baa-a0f1-41b660ca877b', 'Iron Man suit'),
                ('8d6d4c4b-5a5b-4d55-9d3b-3e1c4d5e6f70', 'Batmobile'),
                ('c9d8e7f6-a5b4-4c3d-8e2f-1a0b9c8d7e6f', 'Arc reactor')
            SQL);
    }
}
