<?php

namespace Base\Music\Entity;

use Base\Database\Attribute\Uploader;
use Base\Music\Repository\TrackRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;

/**
 * One recording of a release: its disc and its place, its title - or the
 * work it is (Work) and which movement - its length, the ISRC that names it
 * everywhere, and what the site's player plays for it: the site's own
 * excerpt (sample) first, else a catalogue's 30 seconds (previewUrl).
 * The peaks are the excerpt's waveform, computed once (Service\Peaks,
 * `music:peaks`) and drawn by player.js with no decoding in the browser.
 */
#[ORM\Entity(repositoryClass: TrackRepository::class)]
#[ORM\Table(name: 'music_track')]
#[ORM\Index(columns: ['isrc'], name: 'music_track_isrc_idx')]
class Track
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\ManyToOne(targetEntity: Release::class, inversedBy: 'tracks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?Release $release = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    protected ?int $position = null;

    #[ORM\Column(type: 'integer')]
    protected int $disc = 1;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $title = null;

    /** The work of the repertoire this track is (a movement of). */
    #[ORM\ManyToOne(targetEntity: Work::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?Work $work = null;

    /** "II. Andante con moto". */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $movement = null;

    /** Seconds. */
    #[ORM\Column(type: 'integer', nullable: true)]
    protected ?int $duration = null;

    #[ORM\Column(length: 12, nullable: true)]
    protected ?string $isrc = null;

    /** A catalogue's 30 s preview (iTunes'), filled by Service\Importer. */
    #[ORM\Column(length: 500, nullable: true)]
    protected ?string $previewUrl = null;

    /** The site's own excerpt: an upload, played before any preview. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Uploader(max_size: '32MB', mime_types: ['audio/*'])]
    protected $sample = null;

    /** @var list<float>|null ~200 values in 0..1: the static waveform of the excerpt */
    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $peaks = null;

    /** Who plays on this track alone, when it is not the release's performers. */
    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $performers = null;

    public function __toString(): string
    {
        return $this->getDisplayTitle();
    }

    public function getId(): ?int { return $this->id; }

    public function getRelease(): ?Release { return $this->release; }
    public function setRelease(?Release $release): self { $this->release = $release; return $this; }

    public function getPosition(): ?int { return $this->position; }
    public function setPosition(?int $position): self { $this->position = $position; return $this; }

    public function getDisc(): int { return $this->disc; }
    public function setDisc(?int $disc): self { $this->disc = max(1, (int) $disc); return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self { $this->title = $title ?: null; return $this; }

    public function getWork(): ?Work { return $this->work; }
    public function setWork(?Work $work): self { $this->work = $work; return $this; }

    public function getMovement(): ?string { return $this->movement; }
    public function setMovement(?string $movement): self { $this->movement = $movement ?: null; return $this; }

    /** What the list shows: the title typed, else the work and its movement. */
    public function getDisplayTitle(): string
    {
        if (null !== $this->title && '' !== $this->title) {
            return $this->title;
        }
        $parts = array_filter([$this->work ? (string) $this->work : null, $this->movement]);

        return $parts ? implode(' – ', $parts) : '';
    }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): self { $this->duration = null !== $duration && $duration > 0 ? $duration : null; return $this; }

    /** "4:07", "1:02:45"; "" without a duration. */
    public function getDurationText(): string
    {
        return self::formatDuration($this->duration);
    }

    /** Seconds as a sleeve prints them: m:ss, h:mm:ss from an hour on. */
    public static function formatDuration(null|int|float $seconds): string
    {
        if (null === $seconds || $seconds < 0) {
            return '';
        }
        $seconds = (int) round($seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60)
            : sprintf('%d:%02d', $minutes, $seconds % 60);
    }

    public function getIsrc(): ?string { return $this->isrc; }
    public function setIsrc(?string $isrc): self
    {
        $isrc = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $isrc) ?? '');
        $this->isrc = '' !== $isrc ? substr($isrc, 0, 12) : null;

        return $this;
    }

    public function getPreviewUrl(): ?string { return $this->previewUrl; }
    public function setPreviewUrl(?string $previewUrl): self { $this->previewUrl = $previewUrl ?: null; return $this; }

    public function getSample(): ?string { return Uploader::getPublic($this, 'sample'); }
    public function getSampleFile(): ?File { return Uploader::get($this, 'sample'); }
    public function setSample($sample): self
    {
        // Another excerpt (a new upload) or none any more: the waveform goes with it.
        if ($sample instanceof File || null === $sample || '' === $sample || [] === $sample) {
            $this->peaks = null;
        }
        $this->sample = $sample;

        return $this;
    }

    public function hasSample(): bool { return null !== $this->sample && '' !== $this->sample && [] !== $this->sample; }

    /** Something an <audio> can play: the site's excerpt or a catalogue's preview. */
    public function hasAudio(): bool
    {
        return $this->hasSample() || null !== $this->previewUrl;
    }

    /** @return list<float>|null */
    public function getPeaks(): ?array { return $this->peaks; }

    /** @param list<float>|null $peaks */
    public function setPeaks(?array $peaks): self
    {
        $this->peaks = $peaks ? array_values(array_map(static fn ($peak) => round(max(0.0, min(1.0, (float) $peak)), 3), $peaks)) : null;

        return $this;
    }

    public function getPerformers(): ?string { return $this->performers; }
    public function setPerformers(?string $performers): self { $this->performers = $performers ?: null; return $this; }
}
