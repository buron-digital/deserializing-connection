<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\Profiler;

/**
 * Holds the data of every step of a single usage. The duration of every step is measured from the end of the previous step (or the start
 * of the trace for the raw result, which is the query duration). The durations are stored in nanoseconds and only converted when
 * displayed.
 */
final class ProfilingTrace implements Trace
{
    public const string STAGE_RAW = 'raw';
    public const string STAGE_DECODED = 'decoded';
    public const string STAGE_TRANSFORMED = 'transformed';
    public const string STAGE_DENORMALIZED = 'denormalized';

    /**
     * Only the first items of a generator are kept, as the trace would otherwise keep all items in memory until the end of the request.
     * Generators are used to iterate over a lot of items without keeping them in memory (e.g. by clearing the entity manager in between).
     */
    public const int MAX_RECORDED_DENORMALIZED_ITEMS = 100;

    /** @var array<self::STAGE_*, mixed> */
    public private(set) array $stages = [];

    /** @var array<self::STAGE_*, int> */
    public private(set) array $stageDurationsInNanoseconds = [];

    public private(set) ?\Throwable $exception = null;

    /** Number of items of a generator that were denormalized, but not recorded (see MAX_RECORDED_DENORMALIZED_ITEMS). */
    public private(set) int $numberOfOmittedDenormalizedItems = 0;

    private int $lastRecordedAt;

    private int $denormalizedItemsDurationInNanoseconds = 0;

    /**
     * @param array<string, mixed>                $configuration
     * @param array{file: string, line: int}|null $caller
     */
    public function __construct(
        public readonly string $method,
        public readonly string $sql,
        public readonly ?string $runnableSql,
        public readonly ?string $runnableSqlError,
        public readonly array $configuration,
        public readonly ?array $caller,
    ) {
        $this->lastRecordedAt = hrtime(true);
    }

    #[\Override]
    public function recordRawResult(mixed $rawResult): void
    {
        $this->recordStage(self::STAGE_RAW, $rawResult);
    }

    #[\Override]
    public function recordDecodedResult(mixed $decodedResult): void
    {
        $this->recordStage(self::STAGE_DECODED, $decodedResult);
    }

    #[\Override]
    public function recordTransformedResult(mixed $transformedResult): void
    {
        $this->recordStage(self::STAGE_TRANSFORMED, $transformedResult);
    }

    #[\Override]
    public function recordDenormalizedResult(mixed $denormalizedResult): void
    {
        $this->recordStage(self::STAGE_DENORMALIZED, $denormalizedResult);
    }

    #[\Override]
    public function startItemDenormalization(): void
    {
        $this->lastRecordedAt = hrtime(true);
    }

    /**
     * The durations of all items are summed up, also of the items that aren't recorded.
     *
     * @psalm-suppress MixedArrayAssignment The stage is only filled through this method in case of generators
     */
    #[\Override]
    public function addDenormalizedItem(object $denormalizedItem): void
    {
        $now = hrtime(true);

        /** @var list<object> $recordedItems */
        $recordedItems = $this->stages[self::STAGE_DENORMALIZED] ?? [];
        if (count($recordedItems) < self::MAX_RECORDED_DENORMALIZED_ITEMS) {
            $this->stages[self::STAGE_DENORMALIZED][] = $denormalizedItem;
        } else {
            ++$this->numberOfOmittedDenormalizedItems;
        }

        $this->denormalizedItemsDurationInNanoseconds += $now - $this->lastRecordedAt;
        $this->stageDurationsInNanoseconds[self::STAGE_DENORMALIZED] = $this->denormalizedItemsDurationInNanoseconds;

        $this->lastRecordedAt = $now;
    }

    #[\Override]
    public function recordException(\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    /**
     * @param self::STAGE_* $stage
     */
    private function recordStage(string $stage, mixed $result): void
    {
        $now = hrtime(true);

        $this->stages[$stage] = $result;
        $this->stageDurationsInNanoseconds[$stage] = $now - $this->lastRecordedAt;

        $this->lastRecordedAt = $now;
    }
}
