<?php

declare(strict_types=1);

namespace DigitalCraftsman\DeserializingConnection\ORM;

use DigitalCraftsman\DeserializingConnection\Test\Entity\Project;
use DigitalCraftsman\DeserializingConnection\Test\EntityManagerTestCase;
use DigitalCraftsman\DeserializingConnection\Test\Exception\UserNotFound;
use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ResultSetMappingConnection::class)]
#[CoversClass(Exception\AliasWildcardMissingInSQL::class)]
#[CoversClass(Exception\EntityNotFound::class)]
#[CoversClass(Exception\IndexMustBeString::class)]
final class ResultSetMappingConnectionTest extends EntityManagerTestCase
{
    private const string IRON_MAN_SUIT_ID = '3a3fe751-b621-4baa-a0f1-41b660ca877b';
    private const string BATMOBILE_ID = '8d6d4c4b-5a5b-4d55-9d3b-3e1c4d5e6f70';

    private ResultSetMappingConnection $resultSetMappingConnection;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->resultSetMappingConnection = new ResultSetMappingConnection($this->entityManager);
    }

    #[Test]
    public function find_array_works(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findArray(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                ORDER BY project.name
                SQL,
            entityClass: Project::class,
            alias: 'project',
        );

        // -- Assert
        self::assertSame(
            [
                'Arc reactor',
                'Batmobile',
                'Iron Man suit',
            ],
            array_map(
                static fn (Project $project): string => $project->name,
                $projects,
            ),
        );
    }

    #[Test]
    public function find_array_returns_managed_entities(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findArray(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                SQL,
            entityClass: Project::class,
            alias: 'project',
        );

        // -- Assert
        foreach ($projects as $project) {
            self::assertTrue($this->entityManager->contains($project));
        }
    }

    #[Test]
    public function find_array_works_with_indexed_by(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findArray(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE project.project_id IN (:projectIds)
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'projectIds' => [
                    self::IRON_MAN_SUIT_ID,
                    self::BATMOBILE_ID,
                ],
            ],
            parameterTypes: [
                'projectIds' => ArrayParameterType::STRING,
            ],
            indexedBy: static fn (Project $project): string => $project->projectId,
        );

        // -- Assert
        self::assertCount(2, $projects);
        self::assertSame('Iron Man suit', $projects[self::IRON_MAN_SUIT_ID]->name);
        self::assertSame('Batmobile', $projects[self::BATMOBILE_ID]->name);
    }

    #[Test]
    public function find_array_works_without_results(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findArray(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE project.name = :name
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'name' => 'Death Star',
            ],
        );

        // -- Assert
        self::assertSame([], $projects);
    }

    #[Test]
    public function find_array_fails_when_indexed_by_does_not_return_a_string(): void
    {
        // -- Assert
        $this->expectException(Exception\IndexMustBeString::class);

        // -- Act
        /**
         * @psalm-suppress InvalidArgument The non-string index is the point of the test.
         */
        (void) $this->resultSetMappingConnection->findArray(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                SQL,
            entityClass: Project::class,
            alias: 'project',
            indexedBy: static fn (Project $project): int => 1,
        );
    }

    #[Test]
    public function find_one_works(): void
    {
        // -- Act
        $project = $this->resultSetMappingConnection->findOne(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE project.project_id = :projectId
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'projectId' => self::BATMOBILE_ID,
            ],
        );

        // -- Assert
        self::assertNotNull($project);
        self::assertSame('Batmobile', $project->name);
    }

    #[Test]
    public function find_one_works_without_result(): void
    {
        // -- Act
        $project = $this->resultSetMappingConnection->findOne(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE project.name = :name
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'name' => 'Death Star',
            ],
        );

        // -- Assert
        self::assertNull($project);
    }

    #[Test]
    public function get_one_works(): void
    {
        // -- Act
        $project = $this->resultSetMappingConnection->getOne(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE project.project_id = :projectId
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'projectId' => self::IRON_MAN_SUIT_ID,
            ],
        );

        // -- Assert
        self::assertSame('Iron Man suit', $project->name);
    }

    #[Test]
    public function get_one_fails_without_result(): void
    {
        // -- Assert
        $this->expectException(Exception\EntityNotFound::class);

        // -- Act
        (void) $this->resultSetMappingConnection->getOne(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE project.name = :name
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'name' => 'Death Star',
            ],
        );
    }

    #[Test]
    public function get_one_fails_without_result_with_otherwise_throw(): void
    {
        // -- Assert
        $this->expectException(UserNotFound::class);

        // -- Act
        (void) $this->resultSetMappingConnection->getOne(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE project.name = :name
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'name' => 'Death Star',
            ],
            otherwiseThrow: static fn () => new UserNotFound(),
        );
    }

    #[Test]
    public function find_generator_works(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findGenerator(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE NOT project.project_id = :projectId
                ORDER BY project.name
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'projectId' => self::BATMOBILE_ID,
            ],
        );

        // -- Assert
        self::assertSame(
            [
                'Arc reactor',
                'Iron Man suit',
            ],
            array_map(
                static fn (Project $project): string => $project->name,
                iterator_to_array($projects, false),
            ),
        );
    }

    #[Test]
    public function find_generator_returns_managed_entities(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findGenerator(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                SQL,
            entityClass: Project::class,
            alias: 'project',
        );

        // -- Assert
        $numberOfProjects = 0;
        foreach ($projects as $project) {
            self::assertTrue($this->entityManager->contains($project));
            ++$numberOfProjects;
        }
        self::assertSame(3, $numberOfProjects);
    }

    #[Test]
    public function find_generator_works_with_parameter_types(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findGenerator(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE project.project_id IN (:projectIds)
                ORDER BY project.name
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'projectIds' => [
                    self::IRON_MAN_SUIT_ID,
                    self::BATMOBILE_ID,
                ],
            ],
            parameterTypes: [
                'projectIds' => ArrayParameterType::STRING,
            ],
        );

        // -- Assert
        self::assertSame(
            [
                'Batmobile',
                'Iron Man suit',
            ],
            array_map(
                static fn (Project $project): string => $project->name,
                iterator_to_array($projects, false),
            ),
        );
    }

    #[Test]
    public function find_generator_works_without_results(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findGenerator(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                WHERE false
                SQL,
            entityClass: Project::class,
            alias: 'project',
        );

        // -- Assert
        self::assertSame([], iterator_to_array($projects, false));
    }

    #[Test]
    public function find_generator_fails_when_alias_wildcard_is_missing(): void
    {
        // -- Assert
        $this->expectException(Exception\AliasWildcardMissingInSQL::class);

        // -- Act
        iterator_to_array($this->resultSetMappingConnection->findGenerator(
            sql: <<<'SQL'
                SELECT project.project_id
                FROM project
                SQL,
            entityClass: Project::class,
            alias: 'project',
        ));
    }

    #[Test]
    public function quoted_alias_is_accepted(): void
    {
        // -- Act
        $project = $this->resultSetMappingConnection->getOne(
            sql: <<<'SQL'
                SELECT "project".*
                FROM project AS "project"
                WHERE "project".project_id = :projectId
                SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'projectId' => self::BATMOBILE_ID,
            ],
        );

        // -- Assert
        self::assertSame('Batmobile', $project->name);
    }

    #[Test]
    public function find_array_fails_when_alias_wildcard_is_missing(): void
    {
        // -- Assert
        $this->expectException(Exception\AliasWildcardMissingInSQL::class);
        $this->expectExceptionMessage('The alias wildcard "project.*" must be part of the SQL query.');

        // -- Act
        (void) $this->resultSetMappingConnection->findArray(
            sql: <<<'SQL'
                SELECT project.project_id
                FROM project
                SQL,
            entityClass: Project::class,
            alias: 'project',
        );
    }

    #[Test]
    public function find_one_fails_when_alias_wildcard_is_only_part_of_another_name(): void
    {
        // -- Assert
        $this->expectException(Exception\AliasWildcardMissingInSQL::class);

        // -- Act
        (void) $this->resultSetMappingConnection->findOne(
            sql: <<<'SQL'
                SELECT ject.*
                FROM project AS ject
                SQL,
            entityClass: Project::class,
            alias: 'project',
        );
    }

    #[Test]
    public function get_one_fails_when_alias_is_only_the_end_of_another_name(): void
    {
        // -- Assert
        $this->expectException(Exception\AliasWildcardMissingInSQL::class);

        // -- Act
        (void) $this->resultSetMappingConnection->getOne(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                SQL,
            entityClass: Project::class,
            alias: 'ject',
        );
    }

    #[Test]
    public function alias_with_special_characters_of_regular_expressions_is_escaped(): void
    {
        // -- Assert
        $this->expectException(Exception\AliasWildcardMissingInSQL::class);

        // -- Act
        (void) $this->resultSetMappingConnection->findArray(
            sql: <<<'SQL'
                SELECT project.*
                FROM project
                SQL,
            entityClass: Project::class,
            alias: 'pr.ject',
        );
    }
}
