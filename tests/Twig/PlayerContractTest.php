<?php

namespace Base\Music\Tests\Twig;

use PHPUnit\Framework\TestCase;

/**
 * The player's drawer and what it loads from elsewhere, as its template and
 * its script promise them (the real drawer is checked in a browser): three
 * rests - put away, the bar, the sheet -; the switch of the platforms'
 * players named for omnibase/consent's panel; every platform's player built
 * behind the visitor's yes (withConsent / consented), never on a hover.
 */
final class PlayerContractTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/'.$path);
    }

    public function testTheDrawerHasItsThreeRests(): void
    {
        $bar = self::read('templates/client/_player_bar.html.twig');

        self::assertStringContainsString('data-music-mini', $bar, 'put away: the bubble');
        self::assertStringContainsString('class="music-player-bar"', $bar, 'the bar');
        self::assertStringContainsString('id="music-sheet"', $bar, 'the sheet');
        self::assertStringContainsString('data-music-sheet-embed', $bar, 'the platform\'s player of the whole record, in the sheet');
        self::assertStringContainsString("data-consent-label=\"{{ '@music.consent.label'|trans }}\"", $bar);
    }

    public function testEveryPlatformsPlayerWaitsForTheVisitorsYes(): void
    {
        $js = self::read('public/js/player.js');

        self::assertStringContainsString("const CONSENT = 'MEDIA';", $js);
        // Each place that builds a platform's player asks first.
        foreach ([
            "function full(release, platform)" => "if (!consented()) { withConsent(() => full(release, platform)); return; }",
            "function build(box)" => "if (!consented()) { withConsent(() => build(box)); return; }",
            "function filmOpen(box, preview)" => "if (!preview) withConsent(() => filmOpen(box, false));",
            "function openFull(platform)" => "if (!consented()) { withConsent(() => openFull(platform)); return; }",
        ] as $function => $gate) {
            $at = strpos($js, $function);
            self::assertNotFalse($at, $function);
            $next = strpos($js, "\n    function ", $at + 1) ?: \strlen($js);
            self::assertStringContainsString($gate, substr($js, $at, $next - $at), $function.' asks first');
        }
        self::assertSame(1, substr_count($js, "createElement('iframe')"), 'one place builds an iframe itself: a film, gated above');
    }

    public function testTheSwitchIsNamedInEachLanguage(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $catalogue = \Symfony\Component\Yaml\Yaml::parseFile(\dirname(__DIR__, 2).'/translations/music+intl-icu.'.$locale.'.yaml');
            self::assertNotEmpty($catalogue['consent']['label'] ?? null, $locale);
            self::assertNotEmpty($catalogue['consent']['description'] ?? null, $locale);
        }
    }
}
