<?php

declare(strict_types=1);

namespace DigitalCraftsman\DeserializingConnection\ORM\Exception;

/**
 * @psalm-immutable
 */
final class AliasWildcardMissingInSQL extends \DomainException
{
    public function __construct(string $aliasWithWildcard)
    {
        parent::__construct(sprintf(
            'The alias wildcard "%s" must be part of the SQL query.',
            $aliasWithWildcard,
        ));
    }
}
