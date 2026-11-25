<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\ORM\Exception;

/**
 * @psalm-immutable
 */
final class EntityNotFound extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The entity could not be found.');
    }
}
