<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\Test\DTO;

use BuronDigital\DeserializingConnection\Test\ValueObject\ProjectId;
use BuronDigital\SelfAwareNormalizers\Serializer\ArrayNormalizable;

final readonly class Project implements ArrayNormalizable
{
    public function __construct(
        public ProjectId $projectId,
        public string $name,
    ) {
    }

    // -- Array normalizable

    /**
     * @param array{
     *   projectId: string,
     *    name: string,
     * } $data
     */
    #[\Override]
    public static function denormalize(array $data): self
    {
        return new self(
            projectId: ProjectId::denormalize($data['projectId']),
            name: $data['name'],
        );
    }

    /**
     * @return array{
     *   projectId: string,
     *   name: string,
     * }
     */
    #[\Override]
    public function normalize(): array
    {
        return [
            'projectId' => $this->projectId->normalize(),
            'name' => $this->name,
        ];
    }
}
