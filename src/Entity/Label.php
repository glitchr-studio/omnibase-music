<?php

namespace Base\Music\Entity;

use Base\Database\Attribute\Slugify;
use Base\Database\Attribute\Uploader;
use Base\Music\Repository\LabelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The house a recording came out on - ES-DUR, FARAO classics - and the one
 * the site puts forward: its logo next to the cover, "released by" above
 * the track list, "buy it at the label" before any streaming platform.
 *
 * A label knows its site, its shop, and how the page of one of its releases
 * is written (releaseUrlPattern): with it a release needs nothing but its
 * catalogue number to link to the label.
 */
#[ORM\Entity(repositoryClass: LabelRepository::class)]
#[ORM\Table(name: 'music_label')]
class Label
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    protected ?string $name = null;

    #[ORM\Column(length: 190, unique: true)]
    #[Slugify(reference: 'name')]
    protected ?string $slug = null;

    /** The label's logo: an upload, shown small next to its name. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Uploader(max_size: '4MB', mime_types: ['image/*'])]
    protected $logo = null;

    /** The label's own site. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    protected ?string $url = null;

    /** Where the label sells: its shop, when it is not its site. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    protected ?string $shopUrl = null;

    /**
     * How the page of a release is written at the label, with {catalogue},
     * {upc} and {slug} for the release's own: "https://www.es-dur.de/releases/{catalogue}".
     */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $releaseUrlPattern = null;

    /** ISO 3166-1 alpha-2. */
    #[ORM\Column(length: 2, nullable: true)]
    #[Assert\Country]
    protected ?string $country = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self { $this->slug = $slug; return $this; }

    public function getLogo(): ?string { return Uploader::getPublic($this, 'logo'); }
    public function getLogoFile(): ?File { return Uploader::get($this, 'logo'); }
    public function setLogo($logo): self { $this->logo = $logo; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url ?: null; return $this; }

    public function getShopUrl(): ?string { return $this->shopUrl; }
    public function setShopUrl(?string $shopUrl): self { $this->shopUrl = $shopUrl ?: null; return $this; }

    public function getReleaseUrlPattern(): ?string { return $this->releaseUrlPattern; }
    public function setReleaseUrlPattern(?string $pattern): self { $this->releaseUrlPattern = $pattern ?: null; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): self { $this->country = $country ? strtoupper($country) : null; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    /**
     * The page of one release at the label, from the pattern: null without
     * a pattern, or when the release lacks what the pattern asks for (a
     * pattern on {catalogue} says nothing of a release with no number).
     */
    public function releaseUrl(Release $release): ?string
    {
        if (null === $this->releaseUrlPattern || '' === $this->releaseUrlPattern) {
            return null;
        }
        $values = [
            '{catalogue}' => $release->getCatalogue(),
            '{upc}' => $release->getUpc(),
            '{slug}' => $release->getSlug(),
        ];
        $url = $this->releaseUrlPattern;
        foreach ($values as $placeholder => $value) {
            if (!str_contains($url, $placeholder)) {
                continue;
            }
            if (null === $value || '' === $value) {
                return null;
            }
            $url = str_replace($placeholder, rawurlencode($value), $url);
        }

        return $url;
    }
}
