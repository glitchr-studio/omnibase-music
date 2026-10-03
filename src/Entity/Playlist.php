<?php

namespace Base\Music\Entity;

use Base\Database\Attribute\Slugify;
use Base\Music\Enum\PlaylistKind;
use Base\Music\Repository\PlaylistRepository;
use Doctrine\ORM\Mapping as ORM;
use Omnisong\Model\PlatformLink;
use Omnisong\Platform;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A playlist or a profile on a streaming platform - Spotify, Apple Music,
 * Deezer, YouTube, SoundCloud - played in its own iframe, on a click. What
 * is listened to there, by a listener logged in on the platform, counts as
 * a stream for the musician; a 30 s preview of the site's player does not.
 */
#[ORM\Entity(repositoryClass: PlaylistRepository::class)]
#[ORM\Table(name: 'music_playlist')]
class Playlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(length: 190)]
    #[Assert\NotBlank]
    protected ?string $title = null;

    #[ORM\Column(length: 190, unique: true)]
    #[Slugify(reference: 'title')]
    protected ?string $slug = null;

    /** The playlist's (or the profile's) address on the platform. */
    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    protected ?string $url = null;

    #[ORM\Column(type: 'string', length: 16, enumType: PlaylistKind::class)]
    protected PlaylistKind $kind = PlaylistKind::OWN;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $featured = false;

    #[ORM\Column(type: 'integer')]
    protected int $position = 0;

    #[ORM\Column(type: 'boolean')]
    protected bool $visible = true;

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self { $this->slug = $slug; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url ? trim($url) : null; return $this; }

    /**
     * The platform, read off the address. Platform::fromUrl() knows an
     * Apple Music album or song only, so a playlist or an artist there is
     * recognised here by its host.
     */
    public function getPlatform(): ?Platform
    {
        if (null === $this->url) {
            return null;
        }
        $platform = Platform::fromUrl($this->url);
        if (null === $platform && str_ends_with(strtolower((string) parse_url($this->url, \PHP_URL_HOST)), 'music.apple.com')) {
            return Platform::APPLE_MUSIC;
        }

        return $platform;
    }

    /** What Omnisong's Embedder is given. */
    public function getLink(): ?PlatformLink
    {
        $platform = $this->getPlatform();

        return $platform && $this->url ? new PlatformLink($platform, $this->url, null, $this->title) : null;
    }

    public function getKind(): PlaylistKind { return $this->kind; }
    public function setKind(PlaylistKind $kind): self { $this->kind = $kind; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description ?: null; return $this; }

    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $featured): self { $this->featured = $featured; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(?int $position): self { $this->position = (int) $position; return $this; }

    public function isVisible(): bool { return $this->visible; }
    public function setVisible(bool $visible): self { $this->visible = $visible; return $this; }
}
