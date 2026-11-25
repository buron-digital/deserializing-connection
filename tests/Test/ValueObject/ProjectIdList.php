<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\Test\ValueObject;

use BuronDigital\Ids\ValueObject\IdList;

/**
 * @template-extends IdList<ProjectId>
 */
final readonly class ProjectIdList extends IdList
{
    #[\Override]
    public static function handlesIdClass(): string
    {
        return ProjectId::class;
    }
}
