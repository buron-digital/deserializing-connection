<?php

declare(strict_types=1);

namespace DigitalCraftsman\DeserializingConnection\Test;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;

/**
 * Records the parameters and types of every query, to verify what reaches the DBAL connection (and with it a potential wrapper class).
 */
final class RecordingConnection extends Connection
{
    /**
     * @var list<array{
     *   parameters: list<mixed>|array<string, mixed>,
     *   types: array<int<0, max>|string, ArrayParameterType|ParameterType|Type|string>,
     * }>
     */
    public array $executedQueries = [];

    /**
     * @param list<mixed>|array<string, mixed>                                        $params
     * @param array<int<0, max>|string, ArrayParameterType|ParameterType|Type|string> $types
     */
    #[\Override]
    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null,
    ): Result {
        $this->executedQueries[] = [
            'parameters' => $params,
            'types' => $types,
        ];

        return parent::executeQuery($sql, $params, $types, $qcp);
    }
}
