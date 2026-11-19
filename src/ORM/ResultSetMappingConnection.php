<?php

declare(strict_types=1);

namespace DigitalCraftsman\DeserializingConnection\ORM;

use DigitalCraftsman\DeserializingConnection\Profiler\NullTracer;
use DigitalCraftsman\DeserializingConnection\Profiler\Tracer;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Doctrine\ORM\Query\ParameterTypeInferer;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

/**
 * Gets managed entities from native SQL. It wraps the result set mapping builder and the native query of the ORM, so that only the
 * SQL, the entity class, its table alias and the parameters have to be stated. The SQL must select the wildcard of the alias
 * (e.g. "event.*"), otherwise the entity would be hydrated from the wrong columns.
 *
 * Only registered as a service when doctrine/orm is installed.
 *
 * Objects that aren't entities (like ids, id lists or other value objects) are passed with an explicit type, so that the ORM
 * doesn't try to resolve them as entities (see resolveParameterWithoutType).
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
            $type = $parameterTypes[$key] ?? null;
            if ($type === null) {
                [$value, $type] = $this->resolveParameterWithoutType($value);
            }

            $query->setParameter(
                key: $key,
                value: $value,
                type: $type,
            );
        }

        return $query;
    }

    /**
     * Without a type, the ORM tries to resolve every object parameter as an entity (to use its identifier instead). For an object that
     * isn't an entity, this fails with a mapping exception (which is silenced), and for a traversable it's repeated for every item. As
     * this is expensive (especially for long id lists), the ORM handling is skipped by stating the type for the ORM:
     *
     * - An object that isn't an entity, a traversable or a backed enum is passed as it is, with the type the ORM would infer for it
     *   (e.g. a string for an id). That way the DBAL connection (and with it a wrapper class) gets the object itself.
     * - A traversable that isn't an entity (e.g. an id list) is converted into a list of its items, with the array type the ORM would
     *   infer for it. This is only done when the items are scalars or objects that aren't entities, traversables or backed enums.
     *
     * Everything else (scalars, arrays, entities, backed enums and traversables with other items) is left to the ORM without a type.
     *
     * @return array{0: mixed, 1: ParameterType|ArrayParameterType|string|null}
     */
    private function resolveParameterWithoutType(mixed $value): array
    {
        if (!is_object($value)
            || $value instanceof \BackedEnum
            || $this->isEntity($value)
        ) {
            return [$value, null];
        }

        if (!$value instanceof \Traversable) {
            return [$value, ParameterTypeInferer::inferType($value)];
        }

        $items = [];
        $itemClasses = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($value as $item) {
            if (is_array($item)
                || $item instanceof \Traversable
                || $item instanceof \BackedEnum
            ) {
                return [$value, null];
            }

            if (is_object($item)) {
                $itemClass = DefaultProxyClassNameResolver::getClass($item);
                $itemClasses[$itemClass] = $itemClass;
            }

            $items[] = $item;
        }

        // Every class is only checked once, as all items of an id list share the same class.
        foreach ($itemClasses as $itemClass) {
            if (!$this->entityManager->getMetadataFactory()->isTransient($itemClass)) {
                return [$value, null];
            }
        }

        return [$items, ParameterTypeInferer::inferType($items)];
    }

    private function isEntity(object $value): bool
    {
        return !$this->entityManager->getMetadataFactory()->isTransient(DefaultProxyClassNameResolver::getClass($value));
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
