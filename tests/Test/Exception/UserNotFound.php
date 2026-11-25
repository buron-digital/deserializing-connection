<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\Test\Exception;

/**
 * @psalm-immutable
 */
final class UserNotFound extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The user could not be found');
    }
}
