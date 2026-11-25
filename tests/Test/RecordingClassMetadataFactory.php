<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\Test;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;

/**
 * Records for which classes the metadata was requested, to verify whether the ORM tried to resolve an object as entity (it requests the
 * metadata of the class of every object parameter without a type).
 */
final class RecordingClassMetadataFactory extends ClassMetadataFactory
{
    /** @var list<string> */
    public array $requestedClasses = [];

    #[\Override]
    public function getMetadataFor(string $className): ClassMetadata
    {
        $this->requestedClasses[] = $className;

        return parent::getMetadataFor($className);
    }
}
