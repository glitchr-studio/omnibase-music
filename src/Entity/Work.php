<?php

namespace Base\Music\Entity;

use Base\Music\Enum\Formation;
use Base\Music\Enum\Period;
use Base\Music\Repository\WorkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A piece of the repertoire: who wrote it, its title and its number, what
 * it is written for, how long it lasts, its movements. The /repertoire page
 * lists the visible ones - what a programmer reads before writing - and a
 * track or a video can say which work it is.
 */
#[ORM\Entity(repositoryClass: WorkRepository::class)]
#[ORM\Table(name: 'music_work')]
#[ORM\Index(columns: ['composer'], name: 'music_work_composer_idx')]
class Work
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    /** As a programme prints it: "Reinhold Glière". Sorted by its last word. */
    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    protected ?string $composer = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    protected ?string $title = null;

    /** "op. 74", "BWV 1004", "L. 137". */
    #[ORM\Column(length: 64, nullable: true)]
    protected ?string $opus = null;

    /** The year it was written. */
    #[ORM\Column(type: 'integer', nullable: true)]
    protected ?int $year = null;

    /** "harp and orchestra", "violin and piano". */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $instrumentation = null;

    #[ORM\Column(type: 'string', length: 16, enumType: Formation::class)]
    protected Formation $formation = Formation::SOLO;

    /** Minutes. */
    #[ORM\Column(type: 'integer', nullable: true)]
    protected ?int $duration = null;

    /** One per line. */
    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $movements = null;

    #[ORM\Column(type: 'string', length: 16, nullable: true, enumType: Period::class)]
    protected ?Period $period = null;

    /** On the /repertoire page. */
    #[ORM\Column(type: 'boolean')]
    protected bool $visible = true;

    #[ORM\Column(type: 'integer')]
    protected int $position = 0;

    public function __toString(): string
    {
        return trim(sprintf('%s: %s', $this->composer, $this->getFullTitle()), ': ');
    }

    public function getId(): ?int { return $this->id; }

    public function getComposer(): ?string { return $this->composer; }
    public function setComposer(?string $composer): self { $this->composer = $composer; return $this; }

    /** What the composers are sorted by: the last word of the name ("Glière"). */
    public function getComposerSortKey(): string
    {
        $words = preg_split('/\s+/u', trim((string) $this->composer)) ?: [];

        return mb_strtolower((string) end($words).' '.$this->composer);
    }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }

    public function getOpus(): ?string { return $this->opus; }
    public function setOpus(?string $opus): self { $this->opus = $opus ?: null; return $this; }

    /** "Harp Concerto in E-flat major, op. 74". */
    public function getFullTitle(): string
    {
        return implode(', ', array_filter([$this->title, $this->opus]));
    }

    public function getYear(): ?int { return $this->year; }
    public function setYear(?int $year): self { $this->year = $year; return $this; }

    public function getInstrumentation(): ?string { return $this->instrumentation; }
    public function setInstrumentation(?string $instrumentation): self { $this->instrumentation = $instrumentation ?: null; return $this; }

    public function getFormation(): Formation { return $this->formation; }
    public function setFormation(Formation $formation): self { $this->formation = $formation; return $this; }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): self { $this->duration = null !== $duration && $duration > 0 ? $duration : null; return $this; }

    public function getMovements(): ?string { return $this->movements; }
    public function setMovements(?string $movements): self { $this->movements = $movements ?: null; return $this; }

    /** @return list<string> */
    public function getMovementList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->movements) ?: [])));
    }

    public function getPeriod(): ?Period { return $this->period; }
    public function setPeriod(?Period $period): self { $this->period = $period; return $this; }

    public function isVisible(): bool { return $this->visible; }
    public function setVisible(bool $visible): self { $this->visible = $visible; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(?int $position): self { $this->position = (int) $position; return $this; }
}
