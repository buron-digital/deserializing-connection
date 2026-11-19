<?php

declare(strict_types=1);

namespace DigitalCraftsman\DeserializingConnection\Test\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'project')]
final class Project
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'project_id', type: 'string')]
        public string $projectId,
        #[ORM\Column(name: 'name', type: 'string')]
        public string $name,
    ) {
    }
}
