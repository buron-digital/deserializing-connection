<?php

declare(strict_types=1);

namespace DigitalCraftsman\DeserializingConnection\ORM;

use DigitalCraftsman\DeserializingConnection\Test\Entity\Project;
use DigitalCraftsman\DeserializingConnection\Test\EntityManagerTestCase;
use DigitalCraftsman\DeserializingConnection\Test\RecordingConnection;
use DigitalCraftsman\DeserializingConnection\Test\ValueObject\ProjectId;
use DigitalCraftsman\DeserializingConnection\Test\ValueObject\ProjectIdList;
use DigitalCraftsman\DeserializingConnection\Test\ValueObject\ProjectName;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Verifies which parameters and types reach the DBAL connection for parameters without an explicit type.
 */
#[CoversClass(ResultSetMappingConnection::class)]
final class ResultSetMappingConnectionParametersTest extends EntityManagerTestCase
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

    #[\Override]
    protected static function connectionWrapperClass(): string
    {
        return RecordingConnection::class;
    }

    #[Test]
    public function id_is_passed_to_the_connection_as_it_is(): void
    {
        // -- Arrange
        $projectId = ProjectId::fromString(self::BATMOBILE_ID);

        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id = :projectId',
            parameters: [
                'projectId' => $projectId,
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['projectId' => $projectId], $executedQuery['parameters']);
        self::assertSame(['projectId' => ParameterType::STRING], $executedQuery['types']);
    }

    #[Test]
    public function date_time_is_passed_to_the_connection_with_the_inferred_type(): void
    {
        // -- Arrange
        $createdBefore = new \DateTimeImmutable('2000-01-01 00:00:00');

        // -- Act
        $projects = $this->findProjects(
            condition: 'CAST(:createdBefore AS TIMESTAMP) < NOW()',
            parameters: [
                'createdBefore' => $createdBefore,
            ],
        );

        // -- Assert
        self::assertSame(['Arc reactor', 'Batmobile', 'Iron Man suit'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['createdBefore' => $createdBefore], $executedQuery['parameters']);
        self::assertSame(['createdBefore' => Types::DATETIME_IMMUTABLE], $executedQuery['types']);
    }

    #[Test]
    public function id_list_is_passed_to_the_connection_as_list_of_ids(): void
    {
        // -- Arrange
        $ironManSuitId = ProjectId::fromString(self::IRON_MAN_SUIT_ID);
        $batmobileId = ProjectId::fromString(self::BATMOBILE_ID);

        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id IN (:projectIds)',
            parameters: [
                'projectIds' => new ProjectIdList([
                    $ironManSuitId,
                    $batmobileId,
                ]),
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile', 'Iron Man suit'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['projectIds' => [$ironManSuitId, $batmobileId]], $executedQuery['parameters']);
        self::assertSame(['projectIds' => ArrayParameterType::STRING], $executedQuery['types']);
    }

    #[Test]
    public function empty_id_list_is_passed_to_the_connection_as_empty_list(): void
    {
        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id IN (:projectIds)',
            parameters: [
                'projectIds' => ProjectIdList::emptyList(),
            ],
        );

        // -- Assert
        self::assertSame([], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['projectIds' => []], $executedQuery['parameters']);
        self::assertSame(['projectIds' => ArrayParameterType::STRING], $executedQuery['types']);
    }

    #[Test]
    public function traversable_of_integers_is_passed_to_the_connection_as_list_of_integers(): void
    {
        // -- Act
        $projects = $this->findProjects(
            condition: 'LENGTH(project.name) IN (:lengths)',
            parameters: [
                'lengths' => new \ArrayIterator([
                    'batmobile' => 9,
                    'ironManSuit' => 13,
                ]),
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile', 'Iron Man suit'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['lengths' => [9, 13]], $executedQuery['parameters']);
        self::assertSame(['lengths' => ArrayParameterType::INTEGER], $executedQuery['types']);
    }

    #[Test]
    public function entity_is_resolved_to_its_identifier_by_the_orm(): void
    {
        // -- Arrange
        $project = $this->entityManager->find(Project::class, self::BATMOBILE_ID);

        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id = :project',
            parameters: [
                'project' => $project,
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['project' => self::BATMOBILE_ID], $executedQuery['parameters']);
        self::assertSame(['project' => ParameterType::STRING], $executedQuery['types']);
    }

    #[Test]
    public function entity_reference_is_resolved_to_its_identifier_by_the_orm(): void
    {
        // -- Arrange
        $project = $this->entityManager->getReference(Project::class, self::BATMOBILE_ID);

        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id = :project',
            parameters: [
                'project' => $project,
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['project' => self::BATMOBILE_ID], $executedQuery['parameters']);
    }

    #[Test]
    public function backed_enum_is_resolved_to_its_value_by_the_orm(): void
    {
        // -- Act
        $projects = $this->findProjects(
            condition: 'project.name = :name',
            parameters: [
                'name' => ProjectName::BATMOBILE,
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['name' => 'Batmobile'], $executedQuery['parameters']);
    }

    #[Test]
    public function traversable_of_entities_is_resolved_to_identifiers_by_the_orm(): void
    {
        // -- Arrange
        $ironManSuit = $this->entityManager->find(Project::class, self::IRON_MAN_SUIT_ID);
        $batmobile = $this->entityManager->find(Project::class, self::BATMOBILE_ID);

        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id IN (:projects)',
            parameters: [
                'projects' => new ArrayCollection([
                    $ironManSuit,
                    $batmobile,
                ]),
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile', 'Iron Man suit'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['projects' => [self::IRON_MAN_SUIT_ID, self::BATMOBILE_ID]], $executedQuery['parameters']);
        self::assertSame(['projects' => ArrayParameterType::STRING], $executedQuery['types']);
    }

    #[Test]
    public function traversable_with_entity_after_another_object_is_resolved_by_the_orm(): void
    {
        // -- Arrange
        $ironManSuitId = ProjectId::fromString(self::IRON_MAN_SUIT_ID);
        $batmobile = $this->entityManager->find(Project::class, self::BATMOBILE_ID);

        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id IN (:projects)',
            parameters: [
                'projects' => new \ArrayIterator([
                    $ironManSuitId,
                    $batmobile,
                ]),
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile', 'Iron Man suit'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['projects' => [$ironManSuitId, self::BATMOBILE_ID]], $executedQuery['parameters']);
    }

    #[Test]
    public function traversable_of_backed_enums_is_resolved_to_values_by_the_orm(): void
    {
        // -- Act
        $projects = $this->findProjects(
            condition: 'project.name IN (:names)',
            parameters: [
                'names' => new \ArrayIterator([
                    ProjectName::IRON_MAN_SUIT,
                    ProjectName::BATMOBILE,
                ]),
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile', 'Iron Man suit'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['names' => ['Iron Man suit', 'Batmobile']], $executedQuery['parameters']);
    }

    #[Test]
    public function traversable_of_arrays_is_resolved_by_the_orm(): void
    {
        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id IN (:projectIds)',
            parameters: [
                'projectIds' => new \ArrayIterator([
                    [self::IRON_MAN_SUIT_ID],
                    [self::BATMOBILE_ID],
                ]),
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile', 'Iron Man suit'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['projectIds' => [self::IRON_MAN_SUIT_ID, self::BATMOBILE_ID]], $executedQuery['parameters']);
    }

    #[Test]
    public function traversable_of_traversables_is_resolved_by_the_orm(): void
    {
        // -- Act
        $projects = $this->findProjects(
            condition: 'project.project_id IN (:projectIds)',
            parameters: [
                'projectIds' => new \ArrayIterator([
                    new \ArrayIterator([self::IRON_MAN_SUIT_ID]),
                    new \ArrayIterator([self::BATMOBILE_ID]),
                ]),
            ],
        );

        // -- Assert
        self::assertSame(['Batmobile', 'Iron Man suit'], $projects);
        $executedQuery = $this->lastExecutedQuery();
        self::assertSame(['projectIds' => [self::IRON_MAN_SUIT_ID, self::BATMOBILE_ID]], $executedQuery['parameters']);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<string>
     */
    private function findProjects(
        string $condition,
        array $parameters,
    ): array {
        $projects = $this->resultSetMappingConnection->findArray(
            sql: sprintf(
                <<<'SQL'
                    SELECT project.*
                    FROM project
                    WHERE %s
                    ORDER BY project.name
                    SQL,
                $condition,
            ),
            entityClass: Project::class,
            alias: 'project',
            parameters: $parameters,
        );

        return array_map(
            static fn (Project $project): string => $project->name,
            $projects,
        );
    }

    /**
     * @return array{
     *   parameters: list<mixed>|array<string, mixed>,
     *   types: array<int<0, max>|string, ArrayParameterType|ParameterType|Type|string>,
     * }
     */
    private function lastExecutedQuery(): array
    {
        /** @var RecordingConnection $connection */
        $connection = $this->connection;
        $executedQuery = end($connection->executedQueries);
        self::assertNotFalse($executedQuery);

        return $executedQuery;
    }
}
