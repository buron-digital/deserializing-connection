<?php

declare(strict_types=1);

namespace DigitalCraftsman\DeserializingConnection\ORM;

use DigitalCraftsman\DeserializingConnection\Profiler\ProfilingTrace;
use DigitalCraftsman\DeserializingConnection\Profiler\ProfilingTracer;
use DigitalCraftsman\DeserializingConnection\Profiler\RunnableQueryRenderer;
use DigitalCraftsman\DeserializingConnection\Test\Entity\Project;
use DigitalCraftsman\DeserializingConnection\Test\EntityManagerTestCase;
use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ResultSetMappingConnection::class)]
#[CoversClass(ProfilingTracer::class)]
#[CoversClass(Exception\AliasWildcardMissingInSQL::class)]
#[CoversClass(Exception\EntityNotFound::class)]
#[CoversClass(Exception\IndexMustBeString::class)]
final class ResultSetMappingConnectionTracingTest extends EntityManagerTestCase
{
    private const string IRON_MAN_SUIT_ID = '3a3fe751-b621-4baa-a0f1-41b660ca877b';
    private const string BATMOBILE_ID = '8d6d4c4b-5a5b-4d55-9d3b-3e1c4d5e6f70';

    private const string PROJECTS_SQL = <<<'SQL'
        SELECT project.*
        FROM project
        WHERE project.project_id IN (:projectIds)
        ORDER BY project.name
        SQL;

    private ProfilingTracer $tracer;

    private ResultSetMappingConnection $resultSetMappingConnection;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->tracer = new ProfilingTracer(new RunnableQueryRenderer($this->connection));

        $this->resultSetMappingConnection = new ResultSetMappingConnection(
            entityManager: $this->entityManager,
            tracer: $this->tracer,
        );
    }

    #[Test]
    public function find_array_is_traced(): void
    {
        // -- Act
        $line = __LINE__ + 1;
        $projects = $this->resultSetMappingConnection->findArray(
            sql: self::PROJECTS_SQL,
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
        $trace = $this->onlyTrace();
        self::assertSame(ResultSetMappingConnection::class.'::findArray', $trace->method);
        self::assertSame(self::PROJECTS_SQL, $trace->sql);
        self::assertStringContainsString(
            sprintf("IN ('%s', '%s')", self::IRON_MAN_SUIT_ID, self::BATMOBILE_ID),
            (string) $trace->runnableSql,
        );
        self::assertSame([
            'entityClass' => Project::class,
            'alias' => 'project',
        ], $trace->configuration);
        self::assertSame(['file' => __FILE__, 'line' => $line], $trace->caller);
        self::assertSame([
            ProfilingTrace::STAGE_DENORMALIZED => $projects,
        ], $trace->stages);
        self::assertCount(2, $projects);
        self::assertNull($trace->exception);
    }

    #[Test]
    public function find_array_with_index_is_traced(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findArray(
            sql: self::PROJECTS_SQL,
            entityClass: Project::class,
            alias: 'project',
            parameters: [
                'projectIds' => [
                    self::IRON_MAN_SUIT_ID,
                ],
            ],
            parameterTypes: [
                'projectIds' => ArrayParameterType::STRING,
            ],
            indexedBy: static fn (Project $project): string => $project->projectId,
        );

        // -- Assert
        $trace = $this->onlyTrace();
        self::assertSame([
            ProfilingTrace::STAGE_DENORMALIZED => $projects,
        ], $trace->stages);
        self::assertArrayHasKey(self::IRON_MAN_SUIT_ID, $projects);
    }

    #[Test]
    public function find_one_is_traced(): void
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
        $trace = $this->onlyTrace();
        self::assertSame(ResultSetMappingConnection::class.'::findOne', $trace->method);
        self::assertSame([
            'entityClass' => Project::class,
            'alias' => 'project',
        ], $trace->configuration);
        self::assertNotNull($project);
        self::assertSame([
            ProfilingTrace::STAGE_DENORMALIZED => $project,
        ], $trace->stages);
    }

    #[Test]
    public function find_one_without_result_is_traced(): void
    {
        // -- Act
        (void) $this->resultSetMappingConnection->findOne(
            sql: 'SELECT project.* FROM project WHERE false',
            entityClass: Project::class,
            alias: 'project',
        );

        // -- Assert
        $trace = $this->onlyTrace();
        self::assertSame([
            ProfilingTrace::STAGE_DENORMALIZED => null,
        ], $trace->stages);
        self::assertNull($trace->exception);
    }

    #[Test]
    public function get_one_is_traced(): void
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
        $trace = $this->onlyTrace();
        self::assertSame(ResultSetMappingConnection::class.'::getOne', $trace->method);
        self::assertSame([
            'entityClass' => Project::class,
            'alias' => 'project',
        ], $trace->configuration);
        self::assertSame([
            ProfilingTrace::STAGE_DENORMALIZED => $project,
        ], $trace->stages);
    }

    #[Test]
    public function find_generator_is_traced_while_it_is_consumed(): void
    {
        // -- Arrange
        $projects = $this->resultSetMappingConnection->findGenerator(
            sql: self::PROJECTS_SQL,
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

        // -- Act
        $consumedProjects = iterator_to_array($projects, false);

        // -- Assert
        $trace = $this->onlyTrace();
        self::assertSame(ResultSetMappingConnection::class.'::findGenerator', $trace->method);
        self::assertSame([
            'entityClass' => Project::class,
            'alias' => 'project',
        ], $trace->configuration);
        self::assertSame([
            ProfilingTrace::STAGE_DENORMALIZED => $consumedProjects,
        ], $trace->stages);
        self::assertCount(2, $consumedProjects);
    }

    #[Test]
    public function find_generator_measures_only_the_hydration_of_entities(): void
    {
        // -- Act
        $projects = $this->resultSetMappingConnection->findGenerator(
            sql: 'SELECT project.* FROM project',
            entityClass: Project::class,
            alias: 'project',
        );
        foreach ($projects as $project) {
            usleep(20_000);
        }

        // -- Assert
        $trace = $this->onlyTrace();
        self::assertLessThan(20_000_000, $trace->stageDurationsInNanoseconds[ProfilingTrace::STAGE_DENORMALIZED]);
    }

    /**
     * @param \Closure(ResultSetMappingConnection): mixed $usage
     * @param class-string<\Throwable>                    $expectedException
     */
    #[Test]
    #[DataProvider('failureDataProvider')]
    public function failure_is_traced(
        \Closure $usage,
        string $expectedException,
    ): void {
        // -- Act
        $thrownException = null;
        try {
            $usage($this->resultSetMappingConnection);
        } catch (\Throwable $exception) {
            $thrownException = $exception;
        }

        // -- Assert
        self::assertInstanceOf($expectedException, $thrownException);

        $trace = $this->onlyTrace();
        self::assertSame($thrownException, $trace->exception);
    }

    /**
     * @return array<string, array{
     *   0: \Closure(ResultSetMappingConnection): mixed,
     *   1: class-string<\Throwable>,
     * }>
     */
    public static function failureDataProvider(): array
    {
        return [
            'find array' => [
                static fn (ResultSetMappingConnection $connection): mixed => $connection->findArray(
                    sql: 'SELECT project.project_id FROM project',
                    entityClass: Project::class,
                    alias: 'project',
                ),
                Exception\AliasWildcardMissingInSQL::class,
            ],
            'find array with invalid index' => [
                /** @psalm-suppress InvalidArgument The non-string index is the point of the test. */
                static fn (ResultSetMappingConnection $connection): mixed => $connection->findArray(
                    sql: 'SELECT project.* FROM project',
                    entityClass: Project::class,
                    alias: 'project',
                    indexedBy: static fn (Project $project): int => 1,
                ),
                Exception\IndexMustBeString::class,
            ],
            'find one' => [
                static fn (ResultSetMappingConnection $connection): mixed => $connection->findOne(
                    sql: 'SELECT project.project_id FROM project',
                    entityClass: Project::class,
                    alias: 'project',
                ),
                Exception\AliasWildcardMissingInSQL::class,
            ],
            'get one' => [
                static fn (ResultSetMappingConnection $connection): mixed => $connection->getOne(
                    sql: 'SELECT project.* FROM project WHERE false',
                    entityClass: Project::class,
                    alias: 'project',
                ),
                Exception\EntityNotFound::class,
            ],
            'find generator' => [
                static fn (ResultSetMappingConnection $connection): mixed => iterator_to_array($connection->findGenerator(
                    sql: 'SELECT project.project_id FROM project',
                    entityClass: Project::class,
                    alias: 'project',
                )),
                Exception\AliasWildcardMissingInSQL::class,
            ],
        ];
    }

    private function onlyTrace(): ProfilingTrace
    {
        $traces = $this->tracer->traces();
        self::assertCount(1, $traces);

        return $traces[0];
    }
}
