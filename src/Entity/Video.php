<?php

namespace Base\Music\Entity;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Database\Attribute\Uploader;
use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Music\Repository\VideoRepository;
use Base\Service\Model\LinkableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A film of the musician playing: a file of the site's, or a video on
 * YouTube or Vimeo. An omnibase thread for its title, its text and its
 * publication; on top, its poster, where and when it was filmed, the work
 * played and the release it belongs to. An embed never loads with the page:
 * the poster and a play button first, the iframe on the click.
 */
#[ORM\Entity(repositoryClass: VideoRepository::class)]
#[ORM\Table(name: 'music_video')]
#[DiscriminatorEntry(value: 'music_video')]
class Video extends Thread implements LinkableInterface
{
    public static function __iconizeStatic(): ?array
    {
        return ['fa-solid fa-film'];
    }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        return $this->getRouter()->generate('music_video', array_merge($routeParameters, ['slug' => $this->getSlug()]), $referenceType);
    }

    /** The film itself, when the site hosts it. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Uploader(max_size: '512MB', mime_types: ['video/*'])]
    protected $file = null;

    #[ORM\Column(length: 32, nullable: true)]
    protected ?string $youtubeId = null;

    #[ORM\Column(length: 32, nullable: true)]
    protected ?string $vimeoId = null;

    /** The still shown before it plays: an upload. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Uploader(max_size: '16MB', mime_types: ['image/*'])]
    protected $poster = null;

    /** Seconds. */
    #[ORM\Column(type: 'integer', nullable: true)]
    protected ?int $duration = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $featured = false;

    #[ORM\ManyToOne(targetEntity: Work::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?Work $work = null;

    #[ORM\ManyToOne(targetEntity: Release::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?Release $release = null;

    #[ORM\Column(type: 'date', nullable: true)]
    protected ?\DateTimeInterface $recordedAt = null;

    /** "Elbphilharmonie, Hamburg". */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $venue = null;

    /** @var Collection<int, Performer> */
    #[ORM\ManyToMany(targetEntity: Performer::class)]
    #[ORM\JoinTable(name: 'music_video_performer')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    protected Collection $performers;

    public function __construct(?\Base\Entity\User $owner = null, ?Thread $parent = null, ?string $title = null, ?string $slug = null)
    {
        parent::__construct($owner, $parent, $title, $slug);
        $this->performers = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? '';
    }

    public function getFile(): ?string { return Uploader::getPublic($this, 'file'); }
    public function setFile($file): self { $this->file = $file; return $this; }

    public function getYoutubeId(): ?string { return $this->youtubeId; }

    /** The id, or any YouTube address holding it (watch?v=, youtu.be/, /embed/, /shorts/, /live/). */
    public function setYoutubeId(?string $youtubeId): self
    {
        $youtubeId = trim((string) $youtubeId);
        if (preg_match('#(?:youtu\.be/|[?&]v=|/embed/|/shorts/|/live/)([A-Za-z0-9_-]{6,})#', $youtubeId, $match)) {
            $youtubeId = $match[1];
        }
        $this->youtubeId = '' !== $youtubeId ? $youtubeId : null;

        return $this;
    }

    public function getVimeoId(): ?string { return $this->vimeoId; }

    /** The id, or a vimeo.com address ending with it. */
    public function setVimeoId(?string $vimeoId): self
    {
        $vimeoId = trim((string) $vimeoId);
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $vimeoId, $match)) {
            $vimeoId = $match[1];
        }
        $this->vimeoId = '' !== $vimeoId ? $vimeoId : null;

        return $this;
    }

    /** Hosted by the site: a <video>, no third party. */
    public function isLocal(): bool
    {
        return null !== $this->file && '' !== $this->file && [] !== $this->file;
    }

    /**
     * The address of the player's iframe - youtube-nocookie.com, or
     * player.vimeo.com with dnt=1 - null for a local film or none at all.
     * Only ever put in a data attribute: the iframe is made on the click.
     */
    public function getEmbedUrl(bool $autoplay = true): ?string
    {
        if ($this->isLocal()) {
            return null;
        }
        if ($this->youtubeId) {
            return sprintf('https://www.youtube-nocookie.com/embed/%s?rel=0&modestbranding=1%s', rawurlencode($this->youtubeId), $autoplay ? '&autoplay=1' : '');
        }
        if ($this->vimeoId) {
            return sprintf('https://player.vimeo.com/video/%s?dnt=1%s', rawurlencode($this->vimeoId), $autoplay ? '&autoplay=1' : '');
        }

        return null;
    }

    /** Its page on the platform, for a visitor who would rather watch it there. */
    public function getWatchUrl(): ?string
    {
        return match (true) {
            null !== $this->youtubeId => 'https://www.youtube.com/watch?v='.rawurlencode($this->youtubeId),
            null !== $this->vimeoId => 'https://vimeo.com/'.rawurlencode($this->vimeoId),
            default => null,
        };
    }

    public function isPlayable(): bool
    {
        return $this->isLocal() || null !== $this->getEmbedUrl();
    }

    public function getPoster(): ?string { return Uploader::getPublic($this, 'poster'); }
    public function getPosterFile(): ?File { return Uploader::get($this, 'poster'); }
    public function setPoster($poster): self { $this->poster = $poster; return $this; }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): self { $this->duration = null !== $duration && $duration > 0 ? $duration : null; return $this; }

    public function getDurationText(): string
    {
        return Track::formatDuration($this->duration);
    }

    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $featured): self { $this->featured = $featured; return $this; }

    public function getWork(): ?Work { return $this->work; }
    public function setWork(?Work $work): self { $this->work = $work; return $this; }

    public function getRelease(): ?Release { return $this->release; }
    public function setRelease(?Release $release): self { $this->release = $release; return $this; }

    public function getRecordedAt(): ?\DateTimeInterface { return $this->recordedAt; }
    public function setRecordedAt(?\DateTimeInterface $recordedAt): self { $this->recordedAt = $recordedAt; return $this; }

    public function getVenue(): ?string { return $this->venue; }
    public function setVenue(?string $venue): self { $this->venue = $venue ?: null; return $this; }

    /** @return Collection<int, Performer> */
    public function getPerformers(): Collection { return $this->performers; }

    public function addPerformer(Performer $performer): self
    {
        if (!$this->performers->contains($performer)) {
            $this->performers->add($performer);
        }

        return $this;
    }

    public function removePerformer(Performer $performer): self
    {
        $this->performers->removeElement($performer);

        return $this;
    }

    /** Publish it now (or at $at), or take it back to draft. */
    public function publish(bool $published = true, ?\DateTimeInterface $at = null): self
    {
        $this->setState($published ? ThreadState::PUBLISH : ThreadState::DRAFT);
        if ($published) {
            $this->setPublishedAt($at ? \DateTime::createFromInterface($at) : ($this->getPublishedAt() ?? new \DateTime()));
        }

        return $this;
    }
}
