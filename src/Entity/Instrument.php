<?php

namespace Base\Music\Entity;

use Base\Database\Attribute\Uploader;
use Base\Music\Repository\InstrumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the musician plays: a Francesco Ruggeri violin, Cremona 1690; a
 * concert harp. Its maker, its year and place, the model it follows, whose
 * hands it went through, and the foundation that lends it when one does.
 * music_instrument() gives the featured one; /instrument shows the visible ones.
 */
#[ORM\Entity(repositoryClass: InstrumentRepository::class)]
#[ORM\Table(name: 'music_instrument')]
class Instrument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    /** The name it goes by: "Francesco Ruggeri". */
    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    protected ?string $name = null;

    /** "violin", "harp". */
    #[ORM\Column(length: 80, nullable: true)]
    protected ?string $kind = null;

    #[ORM\Column(length: 160, nullable: true)]
    protected ?string $maker = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    protected ?int $year = null;

    /** "Cremona". */
    #[ORM\Column(length: 160, nullable: true)]
    protected ?string $place = null;

    /** "after Amati", "Style 23". */
    #[ORM\Column(length: 160, nullable: true)]
    protected ?string $model = null;

    /** Whose it was: "ex-Albert Sammons". */
    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $provenance = null;

    /** The foundation or the patron who lends it. */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $loan = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Uploader(max_size: '16MB', mime_types: ['image/*'])]
    protected $image = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $visible = true;

    /** The one music_instrument() gives. */
    #[ORM\Column(type: 'boolean')]
    protected bool $featured = false;

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getKind(): ?string { return $this->kind; }
    public function setKind(?string $kind): self { $this->kind = $kind ?: null; return $this; }

    public function getMaker(): ?string { return $this->maker; }
    public function setMaker(?string $maker): self { $this->maker = $maker ?: null; return $this; }

    public function getYear(): ?int { return $this->year; }
    public function setYear(?int $year): self { $this->year = $year; return $this; }

    public function getPlace(): ?string { return $this->place; }
    public function setPlace(?string $place): self { $this->place = $place ?: null; return $this; }

    /** "Cremona, 1690". */
    public function getOrigin(): string
    {
        return implode(', ', array_filter([$this->place, $this->year ? (string) $this->year : null]));
    }

    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $model): self { $this->model = $model ?: null; return $this; }

    public function getProvenance(): ?string { return $this->provenance; }
    public function setProvenance(?string $provenance): self { $this->provenance = $provenance ?: null; return $this; }

    public function getLoan(): ?string { return $this->loan; }
    public function setLoan(?string $loan): self { $this->loan = $loan ?: null; return $this; }

    public function getImage(): ?string { return Uploader::getPublic($this, 'image'); }
    public function getImageFile(): ?File { return Uploader::get($this, 'image'); }
    public function setImage($image): self { $this->image = $image; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description ?: null; return $this; }

    public function isVisible(): bool { return $this->visible; }
    public function setVisible(bool $visible): self { $this->visible = $visible; return $this; }

    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $featured): self { $this->featured = $featured; return $this; }
}
