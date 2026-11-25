<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\Test\DTO;

final readonly class ProjectWithTimeEntries
{
    /**
     * @param list<TimeEntry> $timeEntries
     */
    public function __construct(
        public string $name,
        /**
         * @var list<TimeEntry>
         */
        public array $timeEntries,
    ) {
    }
}
