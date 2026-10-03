<?php

namespace Base\Music\Entity;

use Base\Music\Enum\PerformerKind;
use Base\Music\Repository\PerformerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Who plays with the musician: a pianist, a quartet, an orchestra, its
 * conductor. Shared by the releases and the videos; music_performers()
 * writes the line under a title: "with Guillaume Vincent, piano".
 */
#[ORM\Entity(repositoryClass: PerformerRepository::class)]
#[ORM\Table(name: 'music_performer')]
class Performer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    protected ?string $name = null;

    #[ORM\Column(type: 'string', length: 16, enumType: PerformerKind::class)]
    protected PerformerKind $kind = PerformerKind::PERSON;

    /** "piano", "conductor": what follows the name. */
    #[ORM\Column(length: 120, nullable: true)]
    protected ?string $role = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    protected ?string $url = null;

    /** The order of the billing, lowest first. */
    #[ORM\Column(type: 'integer')]
    protected int $position = 0;

    public function __toString(): string
    {
        return implode(', ', array_filter([$this->name, $this->role]));
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getKind(): PerformerKind { return $this->kind; }
    public function setKind(PerformerKind $kind): self { $this->kind = $kind; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $role): self { $this->role = $role ?: null; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url ?: null; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(?int $position): self { $this->position = (int) $position; return $this; }
}
