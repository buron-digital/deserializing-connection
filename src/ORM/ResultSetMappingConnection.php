<?php

declare(strict_types=1);

namespace DigitalCraftsman\DeserializingConnection\ORM;

use DigitalCraftsman\DeserializingConnection\Profiler\NullTracer;
use DigitalCraftsman\DeserializingConnection\Profiler\Tracer;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

/**
 * Gets managed entities from native SQL. It wraps the result set mapping builder and the native query of the ORM, so that only the
 * SQL, the entity class, its table alias and the parameters have to be stated. The SQL must select the wildcard of the alias
 * (e.g. "event.*"), otherwise the entity would be hydrated from the wrong columns.
 *
 * Only registered as a service when doctrine/orm is installed.
 *
 * Every usage is traced for the Symfony profiler. As the ORM executes the query and hydrates the entities in one step, the trace only
 * contains the hydrated entities with the duration of both.
 */
final readonly class ResultSetMappingConnection
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Tracer $tracer = new NullTracer(),
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T>                                                    $entityClass
     * @param list<mixed>|array<string, mixed>                                   $parameters
     * @param array<int<0, max>|string, ParameterType|ArrayParameterType|string> $parameterTypes
     * @param (\Closure(T $item): string)|null                                   $indexedBy
     *
     * @return ($indexedBy is null ? list<T> : array<string, T>)
     *
     * @throws Exception\AliasWildcardMissingInSQL
     * @throws Exception\IndexMustBeString
     */
    #[\NoDiscard('as the result of the query would be lost')]
    public function findArray(
        string $sql,
        string $entityClass,
        string $alias,
        array $parameters = [],
        array $parameterTypes = [],
        ?\Closure $indexedBy = null,
    ): array {
        $trace = $this->tracer->startTrace(__METHOD__, $sql, $parameters, $parameterTypes, [
            'entityClass' => $entityClass,
            'alias' => $alias,
        ]);

        try {
            $query = $this->createNativeQuery(
                $sql,
                $entityClass,
                $alias,
                $parameters,
                $parameterTypes,
            );

            /** @var list<T> $result */
            $result = $query->getResult();
            if ($indexedBy === null) {
                $trace->recordDenormalizedResult($result);

                return $result;
            }

            $indexedResult = [];
            foreach ($result as $item) {
                $index = $indexedBy($item);
                /**
                 * @psalm-suppress TypeDoesNotContainType Validation against missing compliance with Psalm.
                 */
                if (!is_string($index)) {
                    throw new Exception\IndexMustBeString();
                }

                $indexedResult[$index] = $item;
            }

            $trace->recordDenormalizedResult($indexedResult);

            return $indexedResult;
        } catch (\Throwable $exception) {
            $trace->recordException($exception);

            throw $exception;
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T>                                                    $entityClass
     * @param list<mixed>|array<string, mixed>                                   $parameters
     * @param array<int<0, max>|string, ParameterType|ArrayParameterType|string> $parameterTypes
     *
     * @return T|null
     *
     * @throws Exception\AliasWildcardMissingInSQL
     */
    #[\NoDiscard('as the result of the query would be lost')]
    public function findOne(
        string $sql,
        string $entityClass,
        string $alias,
        array $parameters = [],
        array $parameterTypes = [],
    ): ?object {
        $trace = $this->tracer->startTrace(__METHOD__, $sql, $parameters, $parameterTypes, [
            'entityClass' => $entityClass,
            'alias' => $alias,
        ]);

        try {
            $query = $this->createNativeQuery(
                $sql,
                $entityClass,
                $alias,
                $parameters,
                $parameterTypes,
            );

            /** @var T|null $entity */
            $entity = $query->getOneOrNullResult();

            $trace->recordDenormalizedResult($entity);

            return $entity;
        } catch (\Throwable $exception) {
            $trace->recordException($exception);

            throw $exception;
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T>                                                    $entityClass
     * @param list<mixed>|array<string, mixed>                                   $parameters
     * @param array<int<0, max>|string, ParameterType|ArrayParameterType|string> $parameterTypes
     * @param ?callable(): \Throwable                                            $otherwiseThrow
     *
     * @return T
     *
     * @throws \Throwable
     * @throws Exception\AliasWildcardMissingInSQL
     * @throws Exception\EntityNotFound
     */
    #[\NoDiscard('as the result of the query would be lost')]
    public function getOne(
        string $sql,
        string $entityClass,
        string $alias,
        array $parameters = [],
        array $parameterTypes = [],
        ?callable $otherwiseThrow = null,
    ): object {
        $trace = $this->tracer->startTrace(__METHOD__, $sql, $parameters, $parameterTypes, [
            'entityClass' => $entityClass,
            'alias' => $alias,
        ]);

        try {
            $query = $this->createNativeQuery(
                $sql,
                $entityClass,
                $alias,
                $parameters,
                $parameterTypes,
            );

            /** @var T|null $entity */
            $entity = $query->getOneOrNullResult();

            $trace->recordDenormalizedResult($entity);

            if ($entity === null) {
                throw $otherwiseThrow !== null
                    ? $otherwiseThrow()
                    : new Exception\EntityNotFound();
            }

            return $entity;
        } catch (\Throwable $exception) {
            $trace->recordException($exception);

            throw $exception;
        }
    }

    /**
     * Hydrates the entities one after another while the generator is consumed, instead of all at once. The query is only executed when the
     * generator is consumed. The entities stay managed by the entity manager, so to keep the memory usage low, the entity manager needs to
     * be cleared in between by the caller.
     *
     * @template T of object
     *
     * @param class-string<T>                                                    $entityClass
     * @param list<mixed>|array<string, mixed>                                   $parameters
     * @param array<int<0, max>|string, ParameterType|ArrayParameterType|string> $parameterTypes
     *
     * @return \Generator<int, T>
     *
     * @throws Exception\AliasWildcardMissingInSQL
     */
    #[\NoDiscard('as the query is only executed when the generator is consumed')]
    public function findGenerator(
        string $sql,
        string $entityClass,
        string $alias,
        array $parameters = [],
        array $parameterTypes = [],
    ): \Generator {
        $trace = $this->tracer->startTrace(__METHOD__, $sql, $parameters, $parameterTypes, [
            'entityClass' => $entityClass,
            'alias' => $alias,
        ]);

        try {
            $query = $this->createNativeQuery(
                $sql,
                $entityClass,
                $alias,
                $parameters,
                $parameterTypes,
            );

            // The trace starts when the generator is consumed, so the duration of the first entity contains the query. The duration of
            // every further entity starts right before it's fetched, so that the time in between (when the generator is paused) isn't part
            // of it.
            /** @var T $entity */
            foreach ($query->toIterable() as $entity) {
                $trace->addDenormalizedItem($entity);

                yield $entity;

                $trace->startItemDenormalization();
            }
        } catch (\Throwable $exception) {
            $trace->recordException($exception);

            throw $exception;
        }
    }

    /**
     * @param class-string                                                       $entityClass
     * @param list<mixed>|array<string, mixed>                                   $parameters
     * @param array<int<0, max>|string, ParameterType|ArrayParameterType|string> $parameterTypes
     *
     * @throws Exception\AliasWildcardMissingInSQL
     */
    private function createNativeQuery(
        string $sql,
        string $entityClass,
        string $alias,
        array $parameters,
        array $parameterTypes,
    ): NativeQuery {
        self::aliasWildcardMustExistInSQL($sql, $alias);

        $resultSetMappingBuilder = new ResultSetMappingBuilder($this->entityManager);
        $resultSetMappingBuilder->addRootEntityFromClassMetadata(
            $entityClass,
            $alias,
        );

        $query = $this->entityManager->createNativeQuery(
            $sql,
            $resultSetMappingBuilder,
        );

        /** @psalm-suppress MixedAssignment */
        foreach ($parameters as $key => $value) {
            $query->setParameter(
                key: $key,
                value: $value,
                type: $parameterTypes[$key] ?? null,
            );
        }

        return $query;
    }

    /**
     * The lookbehind for a whitespace prevents matches within other names (e.g. the alias "t" within "tenant.*").
     *
     * @throws Exception\AliasWildcardMissingInSQL
     */
    private static function aliasWildcardMustExistInSQL(
        string $sql,
        string $alias,
    ): void {
        $pattern = sprintf('/(?<=\s)"?%s"?\.\*/', preg_quote($alias, '/'));
        if (preg_match($pattern, $sql) !== 1) {
            throw new Exception\AliasWildcardMissingInSQL(sprintf('%s.*', $alias));
        }
    }
}
