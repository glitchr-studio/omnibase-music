<?php

namespace Base\Music\Tests\Entity;

use Base\Music\Entity\Label;
use Base\Music\Entity\Release;
use Base\Music\Entity\Track;
use Doctrine\Common\Collections\ArrayCollection;
use Omnisong\Platform;
use PHPUnit\Framework\TestCase;

/**
 * The label first. A Release is an omnibase Thread, whose constructor needs
 * the kernel (translations, the router): the tests build one without it,
 * as Doctrine does when it hydrates a row.
 */
final class ReleaseTest extends TestCase
{
    private static function release(): Release
    {
        $release = (new \ReflectionClass(Release::class))->newInstanceWithoutConstructor();
        foreach (['performers', 'tracks'] as $property) {
            (new \ReflectionProperty(Release::class, $property))->setValue($release, new ArrayCollection());
        }

        return $release;
    }

    private static function esDur(): Label
    {
        return (new Label())->setName('ES-DUR')
            ->setUrl('https://www.es-dur.de')
            ->setReleaseUrlPattern('https://www.es-dur.de/releases/{catalogue}');
    }

    /** Anaëlle Tourret, "Perspectives Concertantes" (ES-DUR, 28 February 2025). */
    private static function perspectivesConcertantes(): Release
    {
        return self::release()
            ->setLabel(self::esDur())
            ->setCatalogue('ES 2099') // a catalogue number for the test
            ->setReleasedAt(new \DateTimeImmutable('2025-02-28'))
            ->setLinks([
                Platform::DEEZER->value => 'https://www.deezer.com/album/1',
                Platform::APPLE_MUSIC->value => 'https://music.apple.com/de/album/perspectives-concertantes/1793146044',
                Platform::SPOTIFY->value => 'https://open.spotify.com/album/abc',
                Platform::QOBUZ->value => '',
            ]);
    }

    public function testTheLabelComesFirstAtTheReleasePageItsPatternGives(): void
    {
        $links = self::perspectivesConcertantes()->getPlatformLinks();
        $first = $links->first();

        self::assertSame(Platform::LABEL, $first->platform);
        self::assertSame('ES-DUR', $first->title());
        self::assertSame('https://www.es-dur.de/releases/ES%202099', $first->url);
        self::assertSame(
            [Platform::LABEL, Platform::SPOTIFY, Platform::APPLE_MUSIC, Platform::DEEZER],
            array_map(static fn ($link) => $link->platform, $links->all()),
            'then the streamers by Platform::rank(); an empty address is no link',
        );
    }

    public function testTheReleasesOwnPageAtTheLabelWinsOverThePattern(): void
    {
        $release = self::perspectivesConcertantes()->setLabelUrl('https://www.es-dur.de/en/perspectives-concertantes');

        self::assertSame('https://www.es-dur.de/en/perspectives-concertantes', $release->getPlatformLinks()->first()->url);
        self::assertSame('https://www.es-dur.de/en/perspectives-concertantes', $release->getLabelLink()->url);
    }

    public function testWithoutWhatThePatternAsksTheLabelsSiteIsUsed(): void
    {
        $release = self::perspectivesConcertantes()->setCatalogue(null);

        self::assertNull(self::esDur()->releaseUrl($release));
        self::assertSame('https://www.es-dur.de', $release->getPlatformLinks()->first()->url);
    }

    public function testThePatternTakesTheUpcAndTheSlugToo(): void
    {
        $label = (new Label())->setName('FARAO classics')->setReleaseUrlPattern('https://www.farao-classics.de/{slug}?ean={upc}');
        $release = self::release()->setLabel($label)->setUpc('4 025438 081123')->setSlug('strauss-franck-sonatas');

        self::assertSame('4025438081123', $release->getUpc(), 'a UPC keeps its digits only');
        self::assertSame('https://www.farao-classics.de/strauss-franck-sonatas?ean=4025438081123', $label->releaseUrl($release));
    }

    public function testBuyItAtTheLabelOpensTheRecordsPageElseTheShop(): void
    {
        $label = self::esDur()->setShopUrl('https://shop.es-dur.de');
        $release = self::perspectivesConcertantes()->setLabel($label);
        $links = $release->getPlatformLinks();

        self::assertSame([Platform::LABEL, Platform::SHOP], [$links->all()[0]->platform, $links->all()[1]->platform]);
        self::assertSame('https://www.es-dur.de/releases/ES%202099', $release->getLabelLink()->url, 'the record\'s own page at the label first');

        $release->setCatalogue(null);
        self::assertSame('https://shop.es-dur.de', $release->getLabelLink()->url, 'else the label\'s shop');
        self::assertSame('ES-DUR', $release->getLabelLink()->title());
    }

    public function testWithoutALabelTheLinksAreTheLinks(): void
    {
        $release = self::release()->setLinks([Platform::SPOTIFY->value => 'https://open.spotify.com/album/abc']);

        self::assertSame(Platform::SPOTIFY, $release->getPlatformLinks()->first()->platform);
        self::assertNull($release->getLabelLink());
        self::assertSame(Platform::LABEL, $release->setLabelUrl('https://www.farao-classics.de/x')->getPlatformLinks()->first()->platform);
    }

    public function testAwardsOneALine(): void
    {
        $release = self::release()->setAwards("CD of the year — Radio România Muzical\n\n  Diapason d'or  \nSupersonic – Pizzicato");

        self::assertSame([
            ['title' => 'CD of the year', 'by' => 'Radio România Muzical'],
            ['title' => "Diapason d'or", 'by' => null],
            ['title' => 'Supersonic', 'by' => 'Pizzicato'],
        ], $release->getAwardList());
    }

    public function testTracksByDiscThenPlaceAndTheirTotal(): void
    {
        $release = self::release();
        $release->addTrack((new Track())->setDisc(2)->setPosition(1)->setTitle('C')->setDuration(60));
        $release->addTrack((new Track())->setPosition(2)->setTitle('B')->setDuration(61));
        $release->addTrack((new Track())->setPosition(1)->setTitle('A')->setDuration(59));

        self::assertSame(['A', 'B', 'C'], array_map(static fn (Track $t) => $t->getTitle(), $release->getOrderedTracks()));
        self::assertSame([1, 2], array_keys($release->getDiscs()));
        self::assertSame(180, $release->getDuration());
        self::assertSame($release, $release->getOrderedTracks()[0]->getRelease());

        $release->addTrack((new Track())->setTitle('D'));
        self::assertNull($release->getDuration(), 'one track of unknown length: the total is unknown');
        self::assertSame(['A', 'B', 'D', 'C'], array_map(static fn (Track $t) => $t->getTitle(), $release->getOrderedTracks()), 'a track with no place goes last of its disc');
        self::assertSame(4, $release->getOrderedTracks()[2]->getPosition());
    }

    public function testOutUpcoming(): void
    {
        self::assertTrue(self::release()->setReleasedAt(new \DateTimeImmutable('2021-11-12'))->isOut());
        self::assertFalse(self::release()->setReleasedAt(new \DateTimeImmutable('+1 month'))->isOut());
        self::assertFalse(self::release()->setUpcoming(true)->isOut());
    }
}
