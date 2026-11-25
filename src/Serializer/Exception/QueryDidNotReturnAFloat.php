<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\Serializer\Exception;

/**
 * @psalm-immutable
 */
final class QueryDidNotReturnAFloat extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The query did not return a float.');
    }
}
