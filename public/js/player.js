/*
 * omnibase/music: one player for the whole page, no dependency.
 *
 * One <audio> plays every track. A track is any element with
 * [data-music-track] and data-src (the file or the preview), data-title,
 * data-count (where a play of it is counted, once 30 s of it were heard),
 * data-whole (the file is the whole track: no "Listen in full" for it),
 * data-release (the list next/prev walk), data-peaks (JSON, 0..1) and
 * data-kind ("file", "preview" or "embed": no audio, the release's embed
 * opens instead). The bar #music-player (music_player_bar()) shows what
 * plays: title, play/pause, prev/next, progress on the waveform, time,
 * mute, and a cross ([data-music-tuck]). The bar is a drawer with three
 * rests, pulled from one to the other under the finger ([data-music-drag]
 * surfaces, the handle too) or clicked: put away (the music goes on; only
 * a bubble [data-music-mini] stays - the sleeve, play/pause; its rim brings
 * the bar back; carried, it goes to the corner it is let go nearest to), the bar, and
 * the sheet under it (the sleeve large - the bar's small sleeve
 * [data-music-expand] opens and folds it - and the platform's player of
 * the whole record). Escape folds the sheet, then puts the bar away. It
 * stays as it was left across pages - a page swapped in place
 * (transparent:load) or loaded anew (the track, its time and the drawer's
 * rest are kept for the session; a new page never starts sound by itself).
 * A record plays through, and from its last track back to its first, round
 * and round, until it is paused.
 * Space plays or pauses, the arrows seek 5 s,
 * Shift+arrows change track. A [data-music-release-play="slug"] button
 * plays that release from its first track, then pauses and resumes it. An embed ([data-music-embed] holding a <template>) is built only
 * on its button's click. "Listen in full" ([data-music-full], one
 * <template data-platform> per platform) swaps the preview for the
 * platform's own player of the whole release - from the release's page,
 * or from the bar's button while one of its tracks plays: "Listen in
 * full" during a preview (the track's data-full: where the platforms'
 * players are fetched, on the click only), then "See the album" - its
 * page on that platform - while the platform's player is open in the sheet.
 *
 * Films: a [data-music-film] poster (data-kind, data-src) plays where it is - silently under
 * the mouse, for good on its button [data-music-film-play]; the music pauses for it, and it
 * goes when the music is asked for again (music:film, detail: {title}).
 *
 * Waves: a canvas.music-wave with data-peaks is drawn as bars, the played
 * part in --music-wave-played, the rest in --music-wave. With
 * data-music-waves="live" on the bar, its canvas follows the sound
 * (an AnalyserNode) - unless the visitor asks for less motion.
 *
 * window.MusicPlayer: { audio, analyser, current, play(el|data), pause(),
 * toggle(), next(), prev(), seek(seconds), close(), tuck(), untuck(), expand(open?) }; on document the events
 * music:play, music:pause, music:ended, music:track (detail: the track),
 * music:close (the bar put away: nothing plays, nothing is current)
 * and music:time (~4 a second, detail: {currentTime, duration}); music:full
 * when a platform's player replaces the preview (detail: {release, platform}).
 */
(() => {
    'use strict';
    if (window.MusicPlayer) return;

    const reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
    const main = new Audio();
    main.preload = 'none';
    let audio = main;          // the element playing now (main, or plain for a source CORS refuses)
    let plain = null;          // created only when a cross-origin preview cannot go through Web Audio
    let context = null, analyser = null, routed = false;
    let volume = null, muted = false; // the graph's last node: the mute button's, whatever the browser makes of <audio muted> once routed
    let current = null, currentEl = null, lastTime = 0, frame = 0;

    const bar = () => document.getElementById('music-player');

    /*
     * A platform's own player (Spotify, Apple Music, Deezer, YouTube, Vimeo) brings its cookies with it: it waits
     * for the visitor's yes to the MEDIA feature of omnibase/consent, when the site has that bundle. Asked on the
     * click that wants it - the panel opens -, and played as soon as the visitor accepts; never on a hover.
     */
    const CONSENT = 'MEDIA';
    const consentOptions = () => { const b = bar(); return { label: (b && b.dataset.consentLabel) || 'Players', description: (b && b.dataset.consentDescription) || '' }; };
    const consented = () => { const c = window.Consent; return !c || typeof c.enabled !== 'function' || c.enabled(CONSENT); };
    function withConsent(run) {
        const c = window.Consent;
        if (consented()) { run(); return; }
        let done = false;
        c.use(CONSENT, consentOptions(), () => { if (!done) { done = true; run(); } });
        if (!done && typeof c.open === 'function') c.open();
    }
    const declareConsent = () => { if (window.Consent && typeof window.Consent.use === 'function') window.Consent.use(CONSENT, consentOptions()); };
    const $ = (sel, root) => (root || document).querySelector(sel);
    const emit = (name, detail) => document.dispatchEvent(new CustomEvent('music:' + name, { detail }));
    const format = (s) => {
        if (!isFinite(s) || s < 0) return '0:00';
        s = Math.floor(s);
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), r = String(s % 60).padStart(2, '0');
        return h ? h + ':' + String(m).padStart(2, '0') + ':' + r : m + ':' + r;
    };
    const parsePeaks = (raw) => { try { const p = JSON.parse(raw || 'null'); return Array.isArray(p) ? p : null; } catch (e) { return null; } };
    const sameOrigin = (src) => { try { return new URL(src, location.href).origin === location.origin; } catch (e) { return true; } };

    function data(el) {
        if (!(el instanceof Element)) return el;
        return { src: el.dataset.src || '', kind: el.dataset.kind || 'file', title: el.dataset.title || '', release: el.dataset.release || '', cover: el.dataset.cover || '', full: el.dataset.full || '', about: el.dataset.about || '', count: el.dataset.count || '', whole: el.dataset.whole !== undefined, releaseTitle: el.dataset.releaseTitle || '', peaks: parsePeaks(el.dataset.peaks), el };
    }

    /* The Web Audio graph: made on the first play a visitor asked for (a browser refuses it before). */
    function graph() {
        if (context || !(window.AudioContext || window.webkitAudioContext)) return;
        try {
            context = new (window.AudioContext || window.webkitAudioContext)();
            analyser = context.createAnalyser();
            analyser.fftSize = 256;
            analyser.smoothingTimeConstant = 0.8;
            context.createMediaElementSource(main).connect(analyser);
            // The sound leaves through a gain of its own: muting turns that down, after the analyser,
            // so the waves keep moving in silence - and it holds in every browser (an element's
            // `muted` is not applied the same way everywhere once it feeds a Web Audio graph).
            volume = context.createGain();
            volume.gain.value = muted ? 0 : 1;
            analyser.connect(volume);
            volume.connect(context.destination);
            routed = true;
        } catch (e) { context = null; analyser = null; }
    }

    function use(el) {
        if (audio === el) return;
        audio.pause();
        audio = el;
    }

    function plainAudio() {
        if (!plain) { plain = new Audio(); plain.preload = 'none'; plain.muted = muted; bind(plain); }
        return plain;
    }

    function play(target) {
        const track = data(target);
        if (!track) return;
        if (track.kind === 'embed' || !track.src) { openEmbed(track.release); return; }
        graph();
        if (context && context.state === 'suspended') context.resume();
        // One thing sounds at a time: a film playing for good goes, a film of the page that sounds pauses.
        if (film && !film.preview) filmClose();
        document.querySelectorAll('video').forEach((v) => { if (!v.muted && !v.paused) v.pause(); });
        // A preview asked for again: the platform's player, if it was open, gives way.
        leaveFull();
        let same = current && current.src === track.src;
        // Restored from the last page on the plain file: its first play here takes it through the graph, where it was.
        let resume = 0;
        if (same && restored) { resume = main.currentTime || 0; same = false; }
        restored = false;
        if (!same) {
            // Through Web Audio, a cross-origin file must come with CORS; plain.onerror falls back when it does not.
            main.crossOrigin = routed && !sameOrigin(track.src) ? 'anonymous' : null;
            use(main);
            main.src = track.src;
            if (resume > 0) main.addEventListener('loadedmetadata', () => { try { main.currentTime = resume; } catch (e) { /* not seekable */ } }, { once: true });
            current = track;
            unheard();
            leaveFull();
            mark(track.el);
            show();
            emit('track', publicTrack());
        }
        audio.play().catch(() => {});
    }

    /*
     * A play is counted once it was heard: 30 seconds of the track, or most of a shorter one (a
     * preview is 30 seconds long). Only the time that sounded adds up - a jump on the wave does
     * not - and the track's data-count is told once (the site keeps the number, per track).
     */
    let heard = 0, heardAt = null, counted = false;
    function unheard() { heard = 0; heardAt = null; counted = false; }
    function listened() {
        const now = audio.currentTime;
        if (heardAt !== null && now > heardAt && now - heardAt < 2) heard += now - heardAt;
        heardAt = now;
        if (counted || !current || !current.count || !isFinite(audio.duration) || heard < Math.min(30, audio.duration * 0.8)) return;
        counted = true;
        fetch(current.count, { method: 'POST', keepalive: true, credentials: 'same-origin' }).catch(() => {});
    }

    /*
     * The films ([data-music-film]: data-kind "file", "youtube" or "vimeo", data-src the file or the
     * platform's player). A film plays where its poster is. Under the mouse, silently: a file of the
     * site's always, a platform's only once the visitor has played one from it (their click is their
     * choice; before it, nothing loads from the platform). On a click, for good, with its controls
     * and its sound - and one thing sounds at a time: the music pauses, another film goes; the music
     * asked for again, the film goes.
     */
    let film = null, filmTimer = 0;
    const filmAllowed = (kind) => { if (kind === 'file') return true; try { return sessionStorage.getItem('music-film-' + kind) === '1'; } catch (e) { return false; } };
    function filmOpen(box, preview) {
        if (film && film.box === box && film.preview === preview) return;
        // A platform's film: only with the visitor's yes - asked on a click, never on a hover.
        if ((box.dataset.kind || 'file') !== 'file' && !consented()) {
            if (!preview) withConsent(() => filmOpen(box, false));
            return;
        }
        // A file already playing silently goes on, with its sound: no second load.
        if (film && film.box === box && film.el instanceof HTMLVideoElement && !preview) {
            film.preview = false; film.el.muted = false; film.el.controls = true; film.el.loop = false;
        } else {
            filmClose();
            const kind = box.dataset.kind || 'file';
            let el;
            if (kind === 'file') {
                el = document.createElement('video');
                el.playsInline = true; el.muted = preview; el.loop = preview; el.controls = !preview;
                el.src = box.dataset.src;
                el.play().catch(() => {});
            } else {
                let src = box.dataset.src || '';
                try { const url = new URL(src); url.searchParams.set('autoplay', '1'); url.searchParams.set('playsinline', '1'); if (preview) { url.searchParams.set(kind === 'vimeo' ? 'muted' : 'mute', '1'); url.searchParams.set('controls', '0'); } src = url.href; } catch (e) { return; }
                el = document.createElement('iframe');
                el.title = box.dataset.title || '';
                el.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
                el.allowFullscreen = true;
                el.src = src;
            }
            box.append(el);
            film = { box, el, preview };
        }
        box.classList.add('is-playing');
        box.classList.toggle('is-preview', preview);
        const cross = $('[data-music-film-close]', box);
        if (cross) cross.hidden = preview;
        if (!preview) {
            try { sessionStorage.setItem('music-film-' + (box.dataset.kind || 'file'), '1'); } catch (e) { /* no storage: no preview later */ }
            audio.pause();
            emit('film', { title: box.dataset.title || '' });
        }
    }
    function filmClose() {
        clearTimeout(filmTimer);
        if (!film) return;
        const { box, el } = film;
        film = null;
        if (el instanceof HTMLVideoElement) el.pause();
        el.remove();
        box.classList.remove('is-playing', 'is-preview');
        const cross = $('[data-music-film-close]', box);
        if (cross) cross.hidden = true;
    }
    document.addEventListener('pointerover', (event) => {
        const box = event.pointerType === 'mouse' && event.target instanceof Element ? event.target.closest('[data-music-film]') : null;
        if (!box || (event.relatedTarget instanceof Node && box.contains(event.relatedTarget))) return;
        // Not over a film that plays for good, and not for a visitor who asks for less motion.
        if ((film && !film.preview) || reduced.matches || !filmAllowed(box.dataset.kind || 'file')) return;
        clearTimeout(filmTimer);
        filmTimer = setTimeout(() => { if (box.isConnected && box.matches(':hover') && !(film && !film.preview)) filmOpen(box, true); }, 450);
    });
    document.addEventListener('pointerout', (event) => {
        const box = event.target instanceof Element ? event.target.closest('[data-music-film]') : null;
        if (!box || (event.relatedTarget instanceof Node && box.contains(event.relatedTarget))) return;
        clearTimeout(filmTimer);
        if (film && film.preview && film.box === box) filmClose();
    });
    // Any film of the page that sounds (a <video> with its controls, the hero's once its sound is on): the music pauses.
    document.addEventListener('play', (event) => { if (event.target instanceof HTMLVideoElement && !event.target.muted) audio.pause(); }, true);
    document.addEventListener('volumechange', (event) => { if (event.target instanceof HTMLVideoElement && !event.target.muted && !event.target.paused) audio.pause(); }, true);

    function pause() { audio.pause(); }
    function toggle() { if (!current) { const first = $('[data-music-track]:not([data-kind="embed"])'); if (first) play(first); return; } audio.paused ? play(current) : pause(); }
    /* A release's own button: its first track, then pause and resume while one of its tracks is the current one. */
    function playRelease(slug) {
        if (current && current.release === slug) { toggle(); return; }
        const first = [...document.querySelectorAll('[data-music-track]')].find((el) => el.dataset.release === slug && el.dataset.kind !== 'embed' && el.dataset.src);
        if (first) play(first);
    }
    /*
     * The drawer. The player is the bar with the sheet above it. One measure, y, says where it
     * is: 0, the sheet is open to its full height (--music-open) above the bar; the sheet's height,
     * it is shut and only the bar shows; further, the bar itself goes down (--music-y) until it is
     * all away (the handle instead). A pull moves it under the finger; let go, it comes to the
     * nearest rest - or the next one in the pull's direction, for a flick.
     */
    let tucked = false, expanded = false, dragged = false;
    function mini() { return $('[data-music-mini]'); }
    function sheetEl() { const b = bar(); return b && $('.music-sheet', b); }
    function rests() {
        const b = bar(), sheet = sheetEl(), row = b && $('.music-player-bar', b);
        const h = sheet ? sheet.offsetHeight : 0;
        return { sheet: 0, bar: h, away: h + (row ? row.offsetHeight : 0) + 24 };
    }
    function place(y, animate) {
        const b = bar();
        if (!b) return;
        const h = rests().bar;
        b.classList.toggle('is-moving', animate === false);
        b.style.setProperty('--music-open', Math.round(Math.max(0, h - y)) + 'px');
        b.style.setProperty('--music-y', Math.round(Math.max(0, y - h)) + 'px');
    }
    /** The drawer at the rest its state says, the rest of the page told (body padding, the handle, aria). */
    function settle(animate) {
        const b = bar(), m = mini(), sheet = sheetEl();
        if (!b) return;
        // Shown before it is measured (a hidden bar has no height): coming back, it starts from below.
        if (current && !tucked && b.hidden) { b.hidden = false; place(rests().away, false); b.getBoundingClientRect(); }
        const r = rests();
        place(tucked ? r.away : (expanded ? r.sheet : r.bar), animate);
        clearTimeout(settle.timer);
        if (tucked) settle.timer = setTimeout(() => { if (tucked) b.hidden = true; }, animate === false || reduced.matches ? 0 : 380);
        if (m) { corner(); m.hidden = !(tucked && current); }
        b.classList.toggle('is-expanded', expanded);
        if (sheet) sheet.inert = !expanded;
        about();
        document.documentElement.classList.toggle('music-playing-bar', !!current && !tucked);
        b.querySelectorAll('[data-music-expand]').forEach((x) => { x.setAttribute('aria-expanded', String(expanded)); if (x.dataset.labelExpand) x.setAttribute('aria-label', x.dataset[expanded ? 'labelCollapse' : 'labelExpand']); });
        remember();
    }
    function tuck() {
        const b = bar(), focused = !!(b && b.contains(document.activeElement));
        tucked = true; expanded = false;
        settle();
        if (focused) $('[data-music-untuck]', mini())?.focus({ preventScroll: true });
        emit('tuck', publicTrack());
    }
    function untuck() {
        const b = bar(), m = mini(), focused = !!(m && m.contains(document.activeElement));
        if (b && b.hidden) { b.hidden = false; place(rests().away, false); b.getBoundingClientRect(); }
        tucked = false;
        show();
        if (focused) $('[data-music-toggle]', b)?.focus({ preventScroll: true });
        emit('untuck', publicTrack());
    }
    function expand(open) {
        const next = open === undefined ? !expanded : !!open;
        if (next && tucked) untuck();
        expanded = next;
        settle();
    }

    function close() {
        const b = bar(), was = publicTrack(), back = currentEl;
        tucked = false; expanded = false;
        leaveFull();
        if (mini()) mini().hidden = true;
        const focused = !!(b && b.contains(document.activeElement));
        audio.pause();
        cancelAnimationFrame(frame); frame = 0;
        try { if (audio.readyState) audio.currentTime = 0; } catch (e) { /* nothing loaded yet */ }
        current = null;
        mark(null);
        state(false);
        if (b) b.hidden = true;
        document.documentElement.classList.remove('music-playing-bar');
        remember();
        // The focus goes back to the button that started it, when it can take it.
        if (focused && back && back.isConnected) back.focus({ preventScroll: true });
        if (was) emit('close', was);
    }
    function seek(seconds) { if (current && isFinite(audio.duration)) audio.currentTime = Math.max(0, Math.min(audio.duration, seconds)); }

    /*
     * The record's tracks, in their order: the ones on the page (their buttons) - and, on a page
     * that does not list them (the agenda, a biography), the list kept from the page they were
     * played from, so "next" and "previous" go on wherever the visitor went.
     */
    let queue = [];
    function siblings() {
        if (!current) return [];
        const here = [...document.querySelectorAll('[data-music-track]')].filter((el) => el.dataset.release === current.release && el.dataset.kind !== 'embed' && el.dataset.src);
        if (here.length) { queue = here.map((el) => { const d = data(el); delete d.el; return d; }); return here; }
        return queue.filter((d) => d.release === current.release);
    }
    const srcOf = (item) => (item instanceof Element ? item.dataset.src : item.src);
    function step(delta) {
        const list = siblings(), at = list.findIndex((item) => srcOf(item) === current.src);
        // Past the last track, the first again: the record goes round.
        const next = list[at + delta] || (delta > 0 && at >= 0 ? list[0] : null);
        if (next) play(next); else if (delta < 0) seek(0);
    }

    const fullBox = (release) => $('[data-music-full][data-release="' + CSS.escape(release || '') + '"]');
    function openEmbed(release) {
        if (fullBox(release)) { full(release); return; }
        const box = $('[data-music-embed][data-release="' + CSS.escape(release || '') + '"]') || $('[data-music-embed]');
        if (!box) return;
        build(box);
        box.scrollIntoView({ behavior: reduced.matches ? 'auto' : 'smooth', block: 'center' });
    }
    /* The whole release on a platform, in place of the preview: the platform's player, built now. */
    function full(release, platform) {
        const box = fullBox(release);
        if (!box) return;
        const tpl = platform ? $('template[data-platform="' + CSS.escape(platform) + '"]', box) : $('template[data-platform]', box);
        const frame = $('[data-music-full-frame]', box);
        if (!tpl || !frame) return;
        if (!consented()) { withConsent(() => full(release, platform)); return; }
        audio.pause();
        frame.replaceChildren(tpl.content.cloneNode(true));
        frame.hidden = false;
        box.querySelectorAll('[data-music-full-play]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.platform === tpl.dataset.platform)));
        box.scrollIntoView({ behavior: reduced.matches ? 'auto' : 'smooth', block: 'nearest' });
        // The bar follows: its sheet's own player goes, "See the album" comes (when this is the record it plays).
        const b = bar(), sheetFrame = b && $('[data-music-sheet-embed]', b);
        if (sheetFrame) { sheetFrame.replaceChildren(); sheetFrame.hidden = true; }
        if (current && current.release === release) enterFull({ release, platform: tpl.dataset.platform, label: tpl.dataset.label || tpl.dataset.platform, page: tpl.dataset.page || location.href });
        else emit('full', { release, platform: tpl.dataset.platform });
    }
    function build(box) {
        const tpl = $('template', box);
        if (!tpl || box.dataset.built) return;
        if (!consented()) { withConsent(() => build(box)); return; }
        box.dataset.built = '1';
        box.replaceChildren(tpl.content.cloneNode(true));
        const iframe = $('iframe', box);
        if (iframe) iframe.focus();
    }

    function publicTrack() { return current ? { src: current.src, title: current.title, release: current.release, peaks: current.peaks } : null; }

    /* The bar and the rows. */
    function show() {
        const b = bar();
        if (!b || !current) return;
        const m = mini();
        if (m) { const open = $('[data-music-untuck]', m); if (open) open.title = current.title ? `${open.getAttribute('aria-label')} - ${current.title}` : open.getAttribute('aria-label'); }
        b.querySelectorAll('[data-music-title]').forEach((t) => { t.textContent = current.title; });
        b.querySelectorAll('[data-music-release-title]').forEach((r) => { r.textContent = current.releaseTitle; });
        // The sleeve: small in the bar, large in the sheet, on the handle (the record's, from its track's data-cover).
        document.querySelectorAll('#music-player [data-music-cover], [data-music-mini] [data-music-cover]').forEach((img) => {
            const holder = img.closest('button[data-music-expand]') || img;
            if (current.cover) { if (img.getAttribute('src') !== current.cover) img.src = current.cover; holder.hidden = false; img.hidden = false; } else { img.removeAttribute('src'); holder.hidden = true; }
        });
        const canvas = $('canvas.music-wave', b);
        if (canvas) { canvas.dataset.peaks = JSON.stringify(current.peaks || []); kick(); }
        const list = siblings(), at = list.findIndex((item) => srcOf(item) === current.src);
        const prev = $('[data-music-prev]', b), next = $('[data-music-next]', b);
        if (prev) prev.disabled = at < 0;
        if (next) next.disabled = at < 0 || list.length < 2;
        offer();
        settle();
    }

    /*
     * Listening in full. A preview is 30 seconds and counts for nothing; the platform's own
     * player plays the whole record and, for a listener signed in there, each track is a stream
     * for the musician. So: "Listen in full" opens that player in the sheet (fetched on the click,
     * nothing from the platform before), and the bar then offers "See the album" - its page there,
     * where it is liked and saved.
     */
    let fullMode = null;               // { release, platform, label, page } while a platform's player is open
    const fullCache = new Map();
    function offer() {
        const b = bar();
        if (!b) return;
        const open = $('[data-music-full-open]', b), album = $('[data-music-album]', b), on = $('[data-music-on]', b), note = $('[data-music-full-note]', b);
        const inFull = !!(fullMode && current && fullMode.release === current.release);
        // The site's own file of the whole track: it is already heard in full.
        if (open) open.hidden = inFull || !current || current.whole || !(current.full || fullBox(current.release));
        if (album) { album.hidden = !inFull; if (inFull) album.href = fullMode.page; else album.removeAttribute('href'); }
        if (on) { on.hidden = !inFull; on.textContent = inFull ? (b.dataset.labelOn || '%platform%').replace('%platform%', fullMode.label) : ''; }
        if (note) note.hidden = !inFull;
        b.classList.toggle('is-full', inFull);
    }
    function leaveFull() {
        if (!fullMode) return;
        fullMode = null;
        const b = bar(), frame = b && $('[data-music-sheet-embed]', b), choices = b && $('[data-music-platforms]', b);
        if (frame) { frame.replaceChildren(); frame.hidden = true; }
        if (choices) { choices.replaceChildren(); choices.hidden = true; }
        offer();
    }
    function enterFull(mode) {
        audio.pause();
        fullMode = mode;
        offer();
        emit('full', { release: mode.release, platform: mode.platform });
        remember();
    }
    /** The platform's player of the current record, in the sheet - wherever the visitor is. */
    function fetched(url) {
        const known = fullCache.get(url);
        return known ? Promise.resolve(known) : fetch(url, { headers: { Accept: 'application/json' } }).then((r) => (r.ok ? r.json() : Promise.reject(r.status))).then((json) => { fullCache.set(url, json); return json; });
    }
    /** What the sheet says about the record that plays: asked for when the sheet opens, once per record. */
    let aboutOf = null;
    function about() {
        const b = bar(), box = b && $('[data-music-about]', b);
        if (!box || !current) return;
        const url = current.about || current.full;
        if (!url) { box.hidden = true; box.replaceChildren(); aboutOf = null; return; }
        if (aboutOf === url) return;
        if (!expanded) { if (aboutOf && aboutOf !== url) { box.hidden = true; box.replaceChildren(); aboutOf = null; } return; }
        aboutOf = url;
        fetched(url).then((json) => {
            if (aboutOf !== url) return;
            box.innerHTML = json.about || '';
            box.hidden = !json.about;
            // The sheet is taller now: it stays open to its new height.
            if (expanded) settle();
        }).catch(() => { aboutOf = null; });
    }
    function openFull(platform) {
        if (!current) return;
        const release = current.release, b = bar();
        if (!current.full) { if (fullBox(release)) full(release, platform); return; }
        const got = fetched(current.full);
        got.then((json) => {
            if (!current || current.release !== release || !b) return;
            const list = json.platforms || [];
            let wished = platform; try { wished = wished || localStorage.getItem('music-platform'); } catch (e) { /* no storage */ }
            const one = list.find((x) => x.platform === wished) || list[0];
            if (!one) return;
            if (platform) { try { localStorage.setItem('music-platform', platform); } catch (e) { /* no storage */ } }
            const frame = $('[data-music-sheet-embed]', b), choices = $('[data-music-platforms]', b);
            if (!consented()) { withConsent(() => openFull(platform)); return; }
            if (frame) { frame.innerHTML = one.html; frame.hidden = false; const iframe = $('iframe', frame); if (iframe) iframe.removeAttribute('loading'); }
            if (choices) {
                choices.replaceChildren(...(list.length > 1 ? list.map((x) => { const c = document.createElement('button'); c.type = 'button'; c.textContent = x.label; c.dataset.musicPlatform = x.platform; c.setAttribute('aria-pressed', String(x.platform === one.platform)); return c; }) : []));
                choices.hidden = list.length < 2;
            }
            enterFull({ release, platform: one.platform, label: one.label, page: one.page || json.page });
            expand(true);
        }).catch(() => { if (fullBox(release)) full(release, platform); });
    }

    /*
     * From one page to the next. A page swapped in place keeps this script and the bar: only the
     * page's own marks are put back. A page loaded anew starts from what the session remembers -
     * the track, where it was, the drawer's rest - paused: a page never starts sound by itself.
     */
    function remember() {
        try {
            if (!current) { sessionStorage.removeItem('music-player'); return; }
            sessionStorage.setItem('music-player', JSON.stringify({ track: { src: current.src, kind: current.kind, title: current.title, release: current.release, releaseTitle: current.releaseTitle, cover: current.cover, full: current.full, about: current.about, count: current.count, whole: current.whole, peaks: current.peaks }, queue, time: audio.currentTime || 0, tucked, expanded: expanded && !fullMode }));
        } catch (e) { /* no storage: nothing is kept */ }
    }
    function resync() {
        if (film && !film.box.isConnected) film = null;
        if (!current) return;
        // A new page is read first: the player steps aside, in its bubble (not while a platform's player is open in its sheet).
        if (!fullMode && !tucked) { tucked = true; expanded = false; emit('tuck', publicTrack()); }
        mark(null);
        show();
        state(!audio.paused);
        drawAll();
    }
    function restore() {
        if (current || !bar()) return;
        let kept = null;
        try { kept = JSON.parse(sessionStorage.getItem('music-player') || 'null'); } catch (e) { kept = null; }
        if (!kept || !kept.track || !kept.track.src) return;
        current = kept.track;
        queue = Array.isArray(kept.queue) ? kept.queue : [];
        // A page loaded anew: the bubble, whatever it was.
        tucked = true; expanded = false;
        // Not through Web Audio yet (a context made without a gesture stays silent): the plain file, at its time.
        main.crossOrigin = null;
        use(main);
        main.preload = 'metadata';
        main.src = current.src;
        const at = Number(kept.time) || 0;
        if (at > 0) main.addEventListener('loadedmetadata', () => { try { main.currentTime = Math.min(at, (main.duration || at) - 0.25); } catch (e) { /* not seekable */ } }, { once: true });
        restored = true;
        mark(null);
        show();
        state(false);
    }
    let restored = false;

    function mark(el) {
        document.querySelectorAll('[data-music-track].is-current').forEach((x) => { x.classList.remove('is-current', 'is-playing'); x.setAttribute('aria-pressed', 'false'); });
        currentEl = el || [...document.querySelectorAll('[data-music-track]')].find((x) => current && x.dataset.src === current.src) || null;
        if (currentEl) currentEl.classList.add('is-current');
    }
    function state(playing) {
        const b = bar();
        // For the host's own buttons and pictures (a "listen" button, waves): html.music-is-playing while it sounds.
        document.documentElement.classList.toggle('music-is-playing', playing);
        if (b) {
            b.classList.toggle('is-playing', playing);
            const toggleBtn = $('[data-music-toggle]', b);
            if (toggleBtn) { toggleBtn.setAttribute('aria-pressed', String(playing)); toggleBtn.setAttribute('aria-label', toggleBtn.dataset[playing ? 'labelPause' : 'labelPlay'] || ''); }
        }
        const m = mini();
        if (m) {
            m.classList.toggle('is-playing', playing);
            const toggleBtn = $('[data-music-toggle]', m);
            if (toggleBtn) { toggleBtn.setAttribute('aria-pressed', String(playing)); toggleBtn.setAttribute('aria-label', toggleBtn.dataset[playing ? 'labelPause' : 'labelPlay'] || ''); }
        }
        if (currentEl) { currentEl.classList.toggle('is-playing', playing); currentEl.setAttribute('aria-pressed', String(playing)); }
        document.querySelectorAll('[data-music-release-play]').forEach((x) => { const on = playing && !!current && x.dataset.musicReleasePlay === current.release; x.classList.toggle('is-playing', on); x.setAttribute('aria-pressed', String(on)); });
        kick();
        remember();
    }

    /* Waves. */
    function colours(canvas) {
        const css = getComputedStyle(canvas);
        return [css.getPropertyValue('--music-wave').trim() || 'rgba(127,127,127,.45)', css.getPropertyValue('--music-wave-played').trim() || 'currentColor'];
    }
    function fit(canvas) {
        const ratio = window.devicePixelRatio || 1, w = Math.max(1, canvas.clientWidth), h = Math.max(1, canvas.clientHeight);
        if (canvas.width !== Math.round(w * ratio) || canvas.height !== Math.round(h * ratio)) { canvas.width = Math.round(w * ratio); canvas.height = Math.round(h * ratio); }
        const ctx = canvas.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        return [ctx, w, h];
    }
    function bars(canvas, values, progress) {
        const [ctx, w, h] = fit(canvas), [rest, played] = colours(canvas);
        ctx.clearRect(0, 0, w, h);
        const n = values.length || 1, gap = w / n > 3 ? 1 : 0, width = Math.max(1, w / n - gap);
        values.forEach((v, i) => {
            const height = Math.max(2, Math.min(1, v) * h);
            ctx.fillStyle = (i + 0.5) / n <= progress ? played : rest;
            ctx.fillRect(i * (w / n), (h - height) / 2, width, height);
        });
    }
    function draw(canvas, progress) {
        const peaks = parsePeaks(canvas.dataset.peaks);
        bars(canvas, peaks && peaks.length ? peaks : new Array(80).fill(0.08), progress);
    }
    function live() { const b = bar(); return !!(b && b.dataset.musicWaves === 'live' && analyser && audio === main && !reduced.matches); }
    /*
     * The bar's own wave, moving from one picture to the next instead of switching: the track's
     * shape (its peaks) at rest, the sound's spectrum while it plays - each bar going to its new
     * height over a few frames -, and the played part sweeping to a new time when one is picked
     * on it, not jumping there.
     */
    const BARS = 96;
    const wave = { shown: new Float32Array(BARS).fill(0.08), at: 0, peaksOf: null, peaks: null, buffer: null };
    function resample(values, n) {
        const out = new Float32Array(n), m = values.length;
        if (!m) return out.fill(0.08);
        for (let i = 0; i < n; i++) {
            const from = i * m / n, to = Math.max(from + 1, (i + 1) * m / n);
            let sum = 0, count = 0;
            for (let j = Math.floor(from); j < Math.min(m, Math.ceil(to)); j++) { sum += values[j]; count++; }
            out[i] = count ? sum / count : 0;
        }
        return out;
    }
    function loop() {
        cancelAnimationFrame(frame);
        frame = 0;
        const b = bar(), canvas = b && $('canvas.music-wave', b);
        if (!canvas || !current || b.hidden) return;
        // Where each bar is going: the spectrum while it sounds, the track's shape otherwise.
        const sounding = live() && !audio.paused;
        let target;
        if (sounding) {
            if (!wave.buffer || wave.buffer.length !== analyser.frequencyBinCount) wave.buffer = new Uint8Array(analyser.frequencyBinCount);
            analyser.getByteFrequencyData(wave.buffer);
            // The low three quarters of the spectrum: above, a recording has next to nothing.
            target = resample(Array.from(wave.buffer.subarray(0, Math.round(wave.buffer.length * 0.72)), (v) => v / 255), BARS);
        } else {
            if (wave.peaksOf !== canvas.dataset.peaks) { wave.peaksOf = canvas.dataset.peaks; const peaks = parsePeaks(canvas.dataset.peaks); wave.peaks = resample(peaks && peaks.length ? peaks : [0.08], BARS); }
            target = wave.peaks;
        }
        const p = isFinite(audio.duration) && audio.duration ? audio.currentTime / audio.duration : 0;
        let moving = sounding;
        if (reduced.matches) { wave.shown.set(target); wave.at = p; }
        else {
            const pace = sounding ? 0.34 : 0.13;
            for (let i = 0; i < BARS; i++) { const d = target[i] - wave.shown[i]; if (Math.abs(d) > 0.004) { wave.shown[i] += d * pace; moving = true; } else wave.shown[i] = target[i]; }
            // A time picked on the wave is a jump for the sound; the played part sweeps there.
            const gap = p - wave.at;
            if (Math.abs(gap) > 0.012) { wave.at += gap * 0.2; moving = true; } else wave.at = p;
        }
        sweep(canvas, wave.shown, wave.at);
        if (moving) frame = requestAnimationFrame(loop);
    }
    /** The bars in the colour of what is to come, and over them, up to the played part's edge, in the colour of what was played. */
    function sweep(canvas, values, progress) {
        const [ctx, w, h] = fit(canvas), [rest, played] = colours(canvas);
        ctx.clearRect(0, 0, w, h);
        const n = values.length, step = w / n, gap = step > 3 ? 1 : 0, width = Math.max(1, step - gap);
        const paint = (colour) => { ctx.fillStyle = colour; for (let i = 0; i < n; i++) { const height = Math.max(2, Math.min(1, values[i]) * h); ctx.fillRect(i * step, (h - height) / 2, width, height); } };
        paint(rest);
        ctx.save(); ctx.beginPath(); ctx.rect(0, 0, Math.max(0, Math.min(1, progress)) * w, h); ctx.clip(); paint(played); ctx.restore();
    }
    function kick() { if (!frame) frame = requestAnimationFrame(loop); }
    function progress() {
        const p = isFinite(audio.duration) && audio.duration ? audio.currentTime / audio.duration : 0, b = bar();
        if (b) {
            const time = $('[data-music-time]', b), total = $('[data-music-duration]', b), range = $('[data-music-seek]', b);
            if (time) time.textContent = format(audio.currentTime);
            if (total) total.textContent = format(audio.duration);
            if (range && document.activeElement !== range) range.value = String(Math.round(p * 1000));
            kick();
        }
        const row = currentEl && $('canvas.music-wave', currentEl.closest('[data-music-row]') || currentEl);
        if (row) draw(row, p);
        const now = performance.now();
        if (now - lastTime >= 250) { lastTime = now; emit('time', { currentTime: audio.currentTime, duration: audio.duration }); }
    }

    function bind(el) {
        el.addEventListener('play', () => { if (el === audio) { state(true); emit('play', publicTrack()); } });
        el.addEventListener('pause', () => { if (el === audio) { state(false); emit('pause', publicTrack()); } });
        el.addEventListener('timeupdate', () => { if (el === audio) { listened(); progress(); } });
        el.addEventListener('loadedmetadata', () => { if (el === audio) progress(); });
        el.addEventListener('ended', () => { if (el !== audio) return; state(false); emit('ended', publicTrack()); unheard(); const list = siblings(), at = list.findIndex((item) => srcOf(item) === current.src); const next = list[at + 1] || list[0]; if (next) play(next); });
        el.addEventListener('error', () => {
            // A cross-origin preview without CORS cannot go through Web Audio: it plays on a plain element, without the live wave.
            if (el === main && main.crossOrigin && current) { const p = plainAudio(); use(p); p.src = current.src; p.play().catch(() => {}); }
        });
    }
    bind(main);

    document.addEventListener('click', (event) => {
        const t = event.target instanceof Element ? event.target : null;
        if (!t) return;
        const track = t.closest('[data-music-track]');
        if (track) { event.preventDefault(); current && current.src === track.dataset.src && !audio.paused ? pause() : play(track); return; }
        const releaseButton = t.closest('[data-music-release-play]');
        if (releaseButton) { event.preventDefault(); playRelease(releaseButton.dataset.musicReleasePlay); return; }
        const fullButton = t.closest('[data-music-full-play]');
        if (fullButton) { event.preventDefault(); full(fullButton.closest('[data-music-full]').dataset.release, fullButton.dataset.platform); return; }
        const embedButton = t.closest('[data-music-embed-play]');
        if (embedButton) { event.preventDefault(); audio.pause(); build(embedButton.closest('[data-music-embed]')); return; }
        const filmButton = t.closest('[data-music-film-play]');
        if (filmButton) { event.preventDefault(); filmOpen(filmButton.closest('[data-music-film]'), false); return; }
        if (t.closest('[data-music-film-close]')) { event.preventDefault(); filmClose(); return; }
        const b = bar();
        // The handle, while the bar is away: bring it back, or play/pause from there.
        const m = mini();
        if (m && m.contains(t)) {
            if (dragged) return;
            if (t.closest('[data-music-untuck]')) untuck();
            else if (t.closest('[data-music-toggle]')) toggle();
            return;
        }
        if (!b || !b.contains(t)) {
            const wave = t.closest('[data-music-row] canvas.music-wave');
            if (wave && currentEl && wave.closest('[data-music-row]').contains(currentEl)) { const r = wave.getBoundingClientRect(); seek((event.clientX - r.left) / r.width * audio.duration); }
            return;
        }
        if (dragged) { event.preventDefault(); return; }
        if (t.closest('[data-music-expand], [data-music-grab]')) expand();
        else if (t.closest('[data-music-platform]')) openFull(t.closest('[data-music-platform]').dataset.musicPlatform);
        else if (t.closest('[data-music-tuck]')) tuck();
        else if (t.closest('[data-music-close]')) close();
        else if (t.closest('[data-music-full-open]')) openFull();
        else if (t.closest('[data-music-toggle]')) toggle();
        else if (t.closest('[data-music-prev]')) step(-1);
        else if (t.closest('[data-music-next]')) step(1);
        else if (t.closest('[data-music-mute]')) { muted = !muted; if (volume) volume.gain.setTargetAtTime(muted ? 0 : 1, context.currentTime, 0.015); else main.muted = muted; if (plain) plain.muted = muted; t.closest('[data-music-mute]').setAttribute('aria-pressed', String(muted)); b.classList.toggle('is-muted', muted); }
        else if (t.closest('canvas.music-wave')) { const r = t.getBoundingClientRect(); seek((event.clientX - r.left) / r.width * audio.duration); }
    });
    /* The pull: the bar, its sheet or the handle taken by a finger (or the mouse) and moved; let go, the nearest rest. */
    document.addEventListener('pointerdown', (event) => {
        const t = event.target instanceof Element ? event.target : null;
        const surface = t && t.closest('[data-music-grab], [data-music-drag]');
        if (!surface || event.button > 0 || !current) return;
        // What is used in place keeps its own gestures: the waveform, the seek range, the platform's player, a link.
        if (t.closest('input, canvas, iframe, a, [data-music-sheet-embed], [data-music-platforms]')) return;
        const b = bar(), fromHandle = false;
        // From the handle, the bar is there again (below the screen) to be measured and pulled.
        if (b.hidden) { b.hidden = false; place(rests().away, false); b.getBoundingClientRect(); }
        const r = rests();
        const origin = tucked ? r.away : (expanded ? r.sheet : r.bar), y0 = event.clientY;
        let y = origin, moved = false, lastY = y0, lastT = event.timeStamp, speed = 0;
        dragged = false;
        // No native drag of the sleeve or selection of the title under a mouse.
        if (event.pointerType === 'mouse' && (!t.closest('button, a') || t.closest('[data-music-grab]'))) event.preventDefault();
        const pointer = event.pointerId;
        const move = (e) => {
            const dy = e.clientY - y0;
            if (!moved) {
                if (Math.abs(dy) < 7) return;
                moved = dragged = true;
                // Only now is the pointer taken (the moves keep coming when it leaves the bar): taken at the press,
                // a plain click would land on the drawer instead of the button under it - play/pause among them.
                try { surface.setPointerCapture(pointer); } catch (e) { /* a synthetic pointer */ }
                if (fromHandle) { const m = mini(); if (m) m.hidden = true; }
                const sheet = sheetEl(); if (sheet) sheet.inert = false;
            }
            const dt = Math.max(1, e.timeStamp - lastT);
            speed = 0.7 * speed + 0.3 * (e.clientY - lastY) / dt; lastY = e.clientY; lastT = e.timeStamp;
            y = Math.max(r.sheet, Math.min(r.away, origin + dy));
            place(y, false);
            if (e.cancelable) e.preventDefault();
        };
        const up = () => {
            window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up);
            if (!moved) { if (tucked) b.hidden = true; return; }
            const order = [r.sheet, r.bar, r.away];
            let to = order.reduce((best, v) => (Math.abs(v - y) < Math.abs(best - y) ? v : best), order[0]);
            // Pulled a good quarter of the way to the next rest: it goes there, not back.
            const at0 = order.indexOf(origin), toward = order[Math.max(0, Math.min(2, at0 + (y > origin ? 1 : -1)))];
            if (to === origin && toward !== origin && Math.abs(y - origin) > 0.28 * Math.abs(toward - origin)) to = toward;
            // A flick: one rest further in its direction, wherever it was let go.
            if (Math.abs(speed) > 0.45) { const at = order.indexOf(origin); to = order[Math.max(0, Math.min(2, at + (speed > 0 ? 1 : -1)))]; }
            const wasTucked = tucked;
            tucked = to === r.away; expanded = to === r.sheet;
            settle();
            if (wasTucked !== tucked) emit(tucked ? 'tuck' : 'untuck', publicTrack());
            // The click that follows the release is not a click.
            setTimeout(() => { dragged = false; }, 0);
        };
        window.addEventListener('pointermove', move, { passive: false }); window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
    });
    /*
     * The bubble carried: taken (a finger, the mouse) and moved anywhere on the page; let go, it goes
     * to the nearest corner and keeps it (for the next pages and visits). A press without a move is
     * a click: play/pause on the sleeve, the bar back on the rim.
     */
    function corner(set) {
        const m = mini();
        if (!m) return;
        if (set) { try { localStorage.setItem('music-mini-corner', set); } catch (e) { /* no storage */ } }
        let kept = set; try { kept = kept || localStorage.getItem('music-mini-corner'); } catch (e) { /* no storage */ }
        if (/^[bt][lr]$/.test(kept || '')) m.dataset.corner = kept;
    }
    document.addEventListener('pointerdown', (event) => {
        const t = event.target instanceof Element ? event.target : null, m = t && t.closest('[data-music-mini]');
        if (!m || event.button > 0) return;
        const box = m.getBoundingClientRect(), dx = event.clientX - box.left, dy = event.clientY - box.top, x0 = event.clientX, y0 = event.clientY, pointer = event.pointerId;
        let moved = false;
        if (event.pointerType === 'mouse') event.preventDefault();
        const move = (e) => {
            if (!moved) {
                if (Math.hypot(e.clientX - x0, e.clientY - y0) < 7) return;
                moved = dragged = true;
                try { m.setPointerCapture(pointer); } catch (err) { /* a synthetic pointer */ }
                m.classList.remove('is-landing'); m.classList.add('is-carried'); m.style.transform = '';
                m.style.right = 'auto'; m.style.bottom = 'auto';
            }
            const x = Math.max(4, Math.min(window.innerWidth - box.width - 4, e.clientX - dx)), y = Math.max(4, Math.min(window.innerHeight - box.height - 4, e.clientY - dy));
            m.style.left = x + 'px'; m.style.top = y + 'px';
            if (e.cancelable) e.preventDefault();
        };
        const up = () => {
            window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up);
            if (!moved) return;
            // The corner nearest to where it was let go; from there to it, a glide.
            const from = m.getBoundingClientRect(), cx = from.left + from.width / 2, cy = from.top + from.height / 2;
            m.classList.remove('is-carried');
            ['left', 'top', 'right', 'bottom'].forEach((side) => { m.style[side] = ''; });
            corner((cy < window.innerHeight / 2 ? 't' : 'b') + (cx < window.innerWidth / 2 ? 'l' : 'r'));
            const to = m.getBoundingClientRect();
            if (!reduced.matches) {
                m.style.transform = `translate(${from.left - to.left}px, ${from.top - to.top}px)`;
                m.getBoundingClientRect();
                m.classList.add('is-landing');
                m.style.transform = '';
                setTimeout(() => m.classList.remove('is-landing'), 420);
            }
            setTimeout(() => { dragged = false; }, 0);
        };
        window.addEventListener('pointermove', move, { passive: false }); window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
    });
    window.addEventListener('resize', () => { if (current) settle(false); });
    // A page swapped in place (transparent.js): the bar and the sound stayed, the page's marks are put back.
    window.addEventListener('transparent:load', resync);
    // The swap also rewrites <html>'s classes, sometimes after its event: ours are put back whenever they go.
    new MutationObserver(() => {
        if (!current) return;
        const root = document.documentElement, playing = !audio.paused;
        if (root.classList.contains('music-is-playing') !== playing) root.classList.toggle('music-is-playing', playing);
        if (root.classList.contains('music-playing-bar') !== !tucked) root.classList.toggle('music-playing-bar', !tucked);
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('pagehide', remember);
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', restore) : restore();
    // The players of the platforms are a feature of the cookie panel, with their own switch.
    // (A deferred script may run before omnibase/consent's: once the page has loaded, both are there.)
    if (document.readyState === 'complete') declareConsent(); else window.addEventListener('load', declareConsent, { once: true });
    document.addEventListener('input', (event) => {
        if (event.target instanceof Element && event.target.matches('#music-player [data-music-seek]')) seek(event.target.value / 1000 * audio.duration);
    });
    document.addEventListener('keydown', (event) => {
        if (!current || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
        const t = event.target;
        // Escape, the focus in the bar (its seek range included): the sheet folds, then the bar is put away; the music goes on.
        if (event.key === 'Escape') { const b = bar(); if (b && !b.hidden && t instanceof Element && b.contains(t)) { event.preventDefault(); expanded ? expand(false) : tuck(); } return; }
        if (t instanceof Element && t.closest('input, textarea, select, [contenteditable], iframe')) return;
        if (event.key === ' ' && !(t instanceof Element && t.closest('button, a, [role="button"]'))) { event.preventDefault(); toggle(); }
        else if (event.key === 'ArrowRight') { event.preventDefault(); event.shiftKey ? step(1) : seek(audio.currentTime + 5); }
        else if (event.key === 'ArrowLeft') { event.preventDefault(); event.shiftKey ? step(-1) : seek(audio.currentTime - 5); }
    });

    function drawAll() { document.querySelectorAll('canvas.music-wave[data-peaks]').forEach((c) => draw(c, c.closest('[data-music-row]') && currentEl && c.closest('[data-music-row]').contains(currentEl) && isFinite(audio.duration) ? audio.currentTime / audio.duration : 0)); }
    let resizeTimer = 0;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(drawAll, 120); });
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', drawAll) : drawAll();

    /* The hero film: muted until its button says otherwise; the poster alone for less motion. */
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element && event.target.closest('[data-music-hero-sound]');
        if (!button) return;
        const video = button.closest('.music-hero') && $('video', button.closest('.music-hero'));
        if (!video) return;
        video.muted = !video.muted;
        if (!video.muted) video.play().catch(() => {});
        button.setAttribute('aria-pressed', String(!video.muted));
        button.setAttribute('aria-label', button.dataset[video.muted ? 'labelOn' : 'labelOff'] || '');
    });
    const calm = () => document.querySelectorAll('.music-hero video').forEach((v) => { if (reduced.matches) { v.pause(); v.removeAttribute('autoplay'); } });
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', calm) : calm();

    window.MusicPlayer = {
        get audio() { return audio; },
        get analyser() { return analyser; },
        get context() { return context; },
        get current() { return publicTrack(); },
        play, pause, toggle, seek, close, playRelease, tuck, untuck, expand, listenInFull: openFull,
        next: () => step(1),
        prev: () => step(-1),
        full,
        redraw: drawAll,
    };
})();
