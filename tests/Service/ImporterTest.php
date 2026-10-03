<?php

namespace Base\Music\Tests\Service;

use Base\Music\Entity\Label;
use Base\Music\Entity\Release;
use Base\Music\Entity\Track;
use Base\Music\Repository\LabelRepository;
use Base\Music\Repository\ReleaseRepository;
use Base\Music\Service\Importer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Omnisong\Model\Label as CatalogueLabel;
use Omnisong\Model\PlatformLink;
use Omnisong\Model\PlatformLinks;
use Omnisong\Model\Release as CatalogueRelease;
use Omnisong\Model\Track as CatalogueTrack;
use Omnisong\Platform;
use PHPUnit\Framework\TestCase;

/**
 * Importer::apply(): what a catalogue found goes into the empty fields
 * only. Brieuc Vourch & Guillaume Vincent, "Strauss & Franck: Sonatas for
 * Violin and Piano" (FARAO classics, iTunes collection 1576897066).
 */
final class ImporterTest extends TestCase
{
    private static function release(): Release
    {
        $release = (new \ReflectionClass(UntranslatedRelease::class))->newInstanceWithoutConstructor();
        foreach (['performers', 'tracks'] as $property) {
            (new \ReflectionProperty(Release::class, $property))->setValue($release, new ArrayCollection());
        }

        return $release;
    }

    private static function found(): CatalogueRelease
    {
        return new CatalogueRelease(
            title: 'Strauss & Franck: Sonatas for Violin and Piano',
            artist: 'Brieuc Vourch & Guillaume Vincent',
            label: new CatalogueLabel('FARAO classics'),
            upc: '4025438081123',
            releasedAt: new \DateTimeImmutable('2021-07-17'),
            coverUrl: 'https://is1-ssl.mzstatic.com/image/thumb/Music/cover/600x600bb.jpg',
            tracks: [
                new CatalogueTrack('Violin Sonata in E-flat major, Op. 18: I. Allegro ma non troppo', 1, 1, 601, 'DEF470001001', 'https://audio-ssl.itunes.apple.com/1.m4a'),
                new CatalogueTrack('Violin Sonata in E-flat major, Op. 18: II. Improvisation', 2, 1, 389, 'DEF470001002', 'https://audio-ssl.itunes.apple.com/2.m4a'),
            ],
            links: new PlatformLinks([
                new PlatformLink(Platform::LABEL, 'https://catalogue.example/farao', null, 'FARAO classics'),
                new PlatformLink(Platform::SPOTIFY, 'https://open.spotify.com/album/from-catalogue'),
                new PlatformLink(Platform::APPLE_MUSIC, 'https://music.apple.com/de/album/strauss-franck/1576897066'),
            ]),
        );
    }

    private function importer(?Label $known = null): Importer
    {
        $labels = $this->createMock(LabelRepository::class);
        $labels->method('findOneByName')->willReturn($known);

        return new Importer($this->createMock(EntityManagerInterface::class), $labels, $this->createMock(ReleaseRepository::class));
    }

    public function testOnlyTheEmptyFieldsAreFilled(): void
    {
        $release = self::release()
            ->setReleasedAt(new \DateTimeImmutable('2021-07-16')) // typed by hand: kept
            ->setLinks([Platform::SPOTIFY->value => 'https://open.spotify.com/album/by-hand']);
        $release->addTrack((new Track())->setPosition(1)->setTitle('Allegro ma non troppo'));

        $report = $this->importer()->apply($release, self::found());

        self::assertTrue($report->found);
        self::assertSame('Strauss & Franck: Sonatas for Violin and Piano', $release->getTitle(), 'no title yet: the catalogue\'s');
        self::assertSame('2021-07-16', $release->getReleasedAt()->format('Y-m-d'));
        self::assertSame('4025438081123', $release->getUpc());
        self::assertSame('https://is1-ssl.mzstatic.com/image/thumb/Music/cover/600x600bb.jpg', $release->getCoverUrl());
        self::assertSame('FARAO classics', $release->getLabel()->getName(), 'a label the site did not know is made');
        self::assertSame([
            Platform::SPOTIFY->value => 'https://open.spotify.com/album/by-hand',
            Platform::APPLE_MUSIC->value => 'https://music.apple.com/de/album/strauss-franck/1576897066',
        ], $release->getLinks(), 'the hand-typed link stays, the label\'s never comes from a catalogue');
        self::assertContains('links.apple_music', $report->filled);
        self::assertNotContains('releasedAt', $report->filled);

        $tracks = $release->getOrderedTracks();
        self::assertCount(2, $tracks);
        self::assertSame('Allegro ma non troppo', $tracks[0]->getTitle(), 'matched by its place, its title kept');
        self::assertSame('https://audio-ssl.itunes.apple.com/1.m4a', $tracks[0]->getPreviewUrl());
        self::assertSame(601, $tracks[0]->getDuration());
        self::assertSame('DEF470001001', $tracks[0]->getIsrc());
        self::assertSame('Violin Sonata in E-flat major, Op. 18: II. Improvisation', $tracks[1]->getTitle());
        self::assertSame([1, 1], [$report->tracksCreated, $report->tracksUpdated]);
    }

    public function testATrackIsMatchedByItsIsrcFirst(): void
    {
        $release = self::release();
        // The second movement, typed first and at the wrong place: its ISRC finds it.
        $release->addTrack((new Track())->setPosition(1)->setIsrc('DEF470001002')->setPreviewUrl('https://example.org/own.m4a'));

        $report = $this->importer()->apply($release, self::found());
        $tracks = $release->getOrderedTracks();

        self::assertSame('https://example.org/own.m4a', $tracks[0]->getPreviewUrl(), 'a preview set by hand stays');
        self::assertSame(389, $tracks[0]->getDuration());
        self::assertSame(1, $report->tracksCreated, 'the first movement, at place 1 already taken, is still made');
        self::assertCount(2, $tracks);
    }

    public function testAKnownLabelIsReusedAndASecondRunChangesNothing(): void
    {
        $farao = (new Label())->setName('FARAO classics');
        $release = self::release();
        $importer = $this->importer($farao);

        $importer->apply($release, self::found());
        self::assertSame($farao, $release->getLabel());

        $again = $importer->apply($release, self::found());
        self::assertFalse($again->changedSomething());
    }
}

/** A release whose title is a plain property: Thread's own is a translation, which needs the kernel. */
class UntranslatedRelease extends Release
{
    private ?string $plainTitle = null;

    public function getTitle(?string $locale = null, int $inheritanceDepthIfNotSet = 0): ?string
    {
        return $this->plainTitle;
    }

    public function setTitle(?string $title, ?string $locale = null, int $inheritanceDepthIfNotSet = 0)
    {
        $this->plainTitle = $title;

        return $this;
    }
}
