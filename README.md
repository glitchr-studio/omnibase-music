# Music

A musician's site on [omnibase](https://github.com/glitchr-studio/omnibase):
the records and the label that released them, the repertoire, the films,
the instrument, the people who play along - and one player for the whole
page, with no dependency. Written first for two classical musicians: a
harpist whose albums came out on ES-DUR, a violinist whose sonatas came out
on FARAO classics. **The label goes first, everywhere**: its logo next to
the sleeve, "released by", "buy it at the label" as the first button of the
page, and only then the streaming platforms.

Six things, each an entity, its back office, its pages and its Twig:

- **Discography.** A `Release` **is** an omnibase `Thread` (a JOINED
  subclass), so it inherits what every thread has - a title, a headline, an
  excerpt, the text (here the liner notes), a slug, publish states and
  scheduling, translations - and adds what a record is: its type (album,
  single, EP, live, compilation), its `Label` and its catalogue number
  there, its UPC, its date ("announced", with a pre-save link, before it is
  out), its sleeve, its `Track`s, who plays on it, the prizes it won (one a
  line: "CD of the year — Radio România Muzical") and its links, one per
  platform. A `Label` knows its site, its shop, and how the page of one of
  its records is written - `https://www.es-dur.de/releases/{catalogue}`,
  with `{catalogue}`, `{upc}` or `{slug}` - so a record needs only its
  number to link to its label; a record's own `labelUrl` wins over the
  pattern. `Release::getPlatformLinks()` gives Omnisong's `PlatformLinks`
  with the label first, then its shop, then the platforms by weight.
  A `Track` has its disc and place, its title - or the `Work` it is and the
  movement - its length, its ISRC, the site's own file (`sample`, an
  upload: an excerpt, or the whole track when `whole` says so), a
  catalogue's 30 s preview (`previewUrl`), the file's waveform (`peaks`)
  and how many times the site played it (`plays`).
- **Repertoire.** A `Work`: the composer, the title and its number
  ("op. 74"), the year, the instrumentation ("harp and orchestra"), the
  formation (solo, chamber, concerto, orchestra, vocal), the period, the
  length, the movements. `/repertoire` lists the visible ones by
  formation, by composer (by surname) or by period (`?by=`).
- **Videos.** A `Video` is a `Thread` too: a file of the site's, or a
  YouTube or Vimeo address (the id is read off it), a poster, where and
  when it was filmed, the work played, the record it belongs to. A
  platform's iframe never loads with the page: the poster and a play
  button first, then youtube-nocookie.com or Vimeo with `dnt=1`.
- **Instrument.** An `Instrument`: "Francesco Ruggeri", a violin, Cremona
  1690, its model, whose hands it went through ("ex-…"), the foundation that
  lends it, a picture. `music_instrument()` gives the featured one;
  `/instrument` shows the visible ones.
- **Performers.** A `Performer` - a person, an ensemble, an orchestra, a
  conductor - and their role ("piano"), shared by the records and the
  films: `music_performers(release)` writes "Guillaume Vincent, piano".
- **Playlists.** A `Playlist`: a playlist or an artist profile on Spotify,
  Apple Music, Deezer, YouTube or SoundCloud (the platform is read off the
  address) - the musician's own (`OWN`), the musician's picks
  (`RECOMMENDED`), or the musician's profile (`ARTIST`, its top tracks).
  `/music` opens with "Listen in full": the profile, then the playlists.

## Install

```bash
composer require omnibase/music:dev-main
```

```php
// config/bundles.php
Omnisong\Bridge\Symfony\OmnisongBundle::class => ['all' => true],
Base\Music\MusicBundle::class => ['all' => true],
```

```yaml
# config/routes.yaml
music_controller:
    resource: "@MusicBundle/src/Controller/Client"
    type: attribute
    prefix: /
```

```yaml
# config/packages/music.yaml (every key optional)
music:
    artist: null              # the musician's name for the JSON-LD; null: the site's title
    player:
        waves: true           # the static waveform behind the progress
        live_waves: true      # the bar's wave follows the sound (Web Audio)
        order: [file, preview, embed]   # what a track plays from, first found first
        full: [spotify, apple_music, deezer]  # "Listen in full", in this order
        theme: light          # the theme the platforms' players are asked for
    label_first: true         # the label before the platforms
    country: 'DE'             # the store the catalogues are asked about
    jsonld: true              # a schema.org MusicAlbum on a record's page
    repertoire:
        enabled: true
        group_by: formation   # formation, composer or period
    instrument:
        enabled: true
```

Then `bin/console doctrine:migrations:diff && bin/console doctrine:migrations:migrate`
and `bin/console assets:install` (the stylesheet lives in `public/css/music.css`,
the player in `public/js/player.js`).

## What the host provides

The templates extend `layout1.html.twig` and fill `title`, `description`,
`content`, `stylesheets` and `javascripts`: that is the whole contract.
The pages are `/music` (`music_index`), `/music/{slug}` (`music_release`),
`/repertoire`, `/videos`, `/videos/{slug}` and `/instrument`, each in the
sitemap but the single film.

**The player's bar is the host's**: `{{ music_player_bar() }}` once in its
layout, before `</body>`, so the music goes on from page to page of a
Turbo site and is there on the home page. It loads `player.js` itself.
Its cross (`[data-music-close]`, or Escape with the focus in the bar) stops
the music and hides the bar - `MusicPlayer.close()`, the `music:close`
event; playing anything brings it back. `html.music-is-playing` while it
sounds, `html.music-playing-bar` while the bar shows.

Twig, for a host's own pages:

| | |
|---|---|
| `music_player(release)` | "Listen in full" on the platforms, then the tracks with their play buttons and waveforms |
| `music_player(release, {fold: true})` | the same for a page showing several records: one "listen" button (`[data-music-release-play]`), the tracks behind a disclosure |
| `music_player(track)` | one track's button |
| `music_links(release)` | the label block, then the platforms as pills |
| `music_full(release)` | "Listen in full on Spotify / Apple Music / Deezer" alone |
| `music_embed(release, platform?)`, `music_listen_embed(release, platform?)` | one platform's player of the record, on a click |
| `music_latest(n, type?)` (`'album'`: the albums, not the singles between them), `music_releases()`, `music_featured()`, `music_upcoming()` | the records |
| `music_videos(n)`, `music_hero_video()` | the films; the home page's film |
| `music_playlists(kind?)`, `music_playlist_embed(playlist)` | the playlists (`own`, `recommended`, `artist`) and one's player |
| `music_instrument()`, `music_performers(release\|video)` | the instrument; the line of who plays |
| `x\|music_duration` | seconds as a sleeve prints them: `4:07`, `1:02:45` |

The look is `music.css`, on custom properties a site sets to its own:
`--music-accent`, `--music-bg`, `--music-fg`, `--music-soft`, `--music-line`,
`--music-surface`, and the waves' `--music-wave` and `--music-wave-played`.

The back office gets the `Release`, `Label`, `Work`, `Video`, `Instrument`,
`Performer` and `Playlist` CRUDs, and a dashboard widget, `music_latest`:
the last record out, the next one announced, and how many tracks the
player has nothing to play for.

## Omnisong: the catalogues

The links, the previews and the embeds come from
[glitchr/omnisong](https://github.com/glitchr-studio/omnisong): one
contract over song.link (Odesli) for the links to every platform, the
iTunes Search API for the tracks and their 30 s previews, and the
iframes of Spotify, Apple Music, Deezer, YouTube and SoundCloud. Its
bundle registers `Omnisong\Catalog\Catalog`, `Omnisong\Player\EmbedderInterface`
and `Omnisong\Player\EmbedOptions`; this one only asks them.

On a record in the back office, **Complete** asks the catalogues what they
know of it - by its UPC, else its first platform link - and **Look up a
record** makes one from a single URL or UPC: the links to every platform,
the UPC, the sleeve (kept as `coverUrl`, a remote address, until one is
uploaded), the date, the label when it has none and the catalogue names
one, and the tracks - matched by their ISRC, else their disc and place -
with their length and preview. **Nothing typed by hand is overwritten**:
only empty fields are filled, and the label's link never comes from a
catalogue. A catalogue that does not answer is not "not found": the flash
says which one was down, try again later. Looked up today, for example:

- Anaëlle Tourret, *Perspectives Concertantes* - iTunes 1793146044,
  28 February 2025, ℗ C2 Hamburg, on ES-DUR;
- Anaëlle Tourret, *Perspectives* - iTunes 1594656563, 12 November 2021;
- Brieuc Vourch & Guillaume Vincent, *Strauss & Franck: Sonatas for Violin
  and Piano* - iTunes 1576897066, 17 July 2021, ℗ FARAO classics.

## The player

One `<audio>` for the whole page. A track plays, in the order
`music.player.order` gives, the site's own excerpt (`file`), else a
catalogue's preview (`preview`), else - nothing to play here - the record's
player on a platform (`embed`), on the click. The bar shows what plays,
play/pause, the previous and next track of the same record, the progress
on the waveform, the time, the sound; **space** plays or pauses, the
**arrows** seek five seconds, **Shift+arrows** change track.

The waveform is static: 200 bars, computed once on the server from the
excerpt and kept on the track (`peaks`), drawn on a `<canvas>` with no
decoding in the browser - the played part in `--music-wave-played`, the
rest in `--music-wave`. With `live_waves`, the bar's canvas follows the
sound instead (an `AnalyserNode`), unless the visitor asks for less motion.

**Listen in full.** A 30 s preview is a taste; a play on Spotify, Apple
Music or Deezer by a listener logged in there is a stream, and counts for
the musician - in the platform's statistics and its payments. So beside the
previews, on a record's page and in the bar while one of its tracks plays,
"Listen in full on Spotify / Apple Music / Deezer" swaps in the platform's
own player of the whole record (`music.player.full` says which platforms,
in which order). Nothing loads from a platform before that click.

**The whole track, on the site.** A catalogue gives 30 seconds; the site
plays a track in full from a file of its own: upload it on the track
(64 MB at most) and tick "Whole track" - a box per track, unticked by
default: nothing plays a work in full unless someone ticked it. The bar then
has no "Listen in full" for it - it is. **Playing a recording in full on the
site presumes the agreement of whoever holds its rights** (the label, the
publisher): get it in writing before ticking the box; an excerpt stays the
default.

**The platforms' players wait for a yes.** Spotify, Apple Music, Deezer,
YouTube or Vimeo set their own cookies: with omnibase/consent installed, a
player of theirs ("Listen in full", the sheet of the bar, a film from a
platform) loads only once the visitor accepts the `MEDIA` feature - asked on
the click that wants it, the panel opening, and played as soon as they say
yes; never on a hover. Its switch is in the panel, named by
`music.consent.label`.

**Plays.** The player tells the site (`POST /music/play/{id}`,
`music_track_play`) once a track was heard - 30 seconds of it, or most of a
shorter excerpt; a jump on the waveform is not listening. `Track::getPlays()`
is the track's count, `Release::getPlays()` its record's; the back office
shows both on a record (the list, the record, each track's row) and the
total on the dashboard's tile. The back office's own listening is not
counted, the same visitor on the same track once every 20 seconds, and
nothing about the listener is kept. What plays in a platform's own player
is counted there, not here.

### The player's JavaScript API

`player.js` exposes `window.MusicPlayer`, for a site that draws its own
visualisation or drives the player from its own buttons:

| | |
|---|---|
| `audio` | the `<audio>` element playing now |
| `analyser` | the `AnalyserNode`, made on the first play a visitor asked for - even without `live_waves` - and `null` before (or when the browser has no Web Audio) |
| `context` | its `AudioContext` |
| `current` | the current track: `{src, title, release, peaks}`, or `null` |
| `play(el \| data)` | plays a `[data-music-track]` element, or `{src, title, release, peaks}` |
| `pause()`, `toggle()`, `seek(seconds)`, `next()`, `prev()` | |
| `full(release, platform?)` | opens "listen in full" for a record (its slug) |
| `redraw()` | draws the waveforms again (after a host changed the page) |

and dispatches on `document`:

| | `detail` |
|---|---|
| `music:track` | the track that starts |
| `music:play`, `music:pause`, `music:ended` | the current track |
| `music:time` | `{currentTime, duration}`, about four times a second |
| `music:full` | `{release, platform}`: a platform's player replaced the preview |

```js
document.addEventListener('music:play', () => {
    const { analyser } = window.MusicPlayer;
    if (!analyser) return;
    const data = new Uint8Array(analyser.frequencyBinCount);
    (function frame() { analyser.getByteFrequencyData(data); /* draw */ requestAnimationFrame(frame); })();
});
```

A preview from another site (iTunes') goes through Web Audio only when
that site answers with CORS; when it does not, it plays on a plain
element, without the live wave.

## The hero film

`{{ music_hero_video() }}` on the home page: a film muted, looping and
playing by itself (`autoplay muted loop playsinline`), with a button to
hear it, and its poster alone for a visitor who asks for less motion.
Which film and which poster are two settings of the back office,
**`music.hero.video`** and **`music.hero.poster`** (an upload or an
address); with neither, nothing is rendered.

## The waveforms: ffmpeg

```bash
bin/console music:peaks            # the excerpts without a waveform yet
bin/console music:peaks --all      # every excerpt again
bin/console music:peaks --track 12
```

or the **Waveforms** button of a record in the back office. ffmpeg decodes
the excerpt to raw mono PCM at 8 kHz (`ffmpeg -i in -ac 1 -ar 8000 -f s16le -`)
and the samples are reduced to 200 loudness values (RMS, the loudest 1).
Without ffmpeg on the server (`apt install ffmpeg`, `apk add ffmpeg`)
nothing is computed - it is said and logged - and the player draws a flat
line instead. A new excerpt forgets the old waveform.

## Tests

`tests/` runs without a kernel: the waveform's reduction (`Peaks::reduce()`
on a synthetic buffer), the label first in `Release::getPlatformLinks()`
(its pattern, the record's own page, its shop), the importer filling only
what is empty, durations, the repertoire's grouping, the films' addresses,
the playlists' platforms and the links' form.
