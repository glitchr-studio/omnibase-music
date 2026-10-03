/*
 * omnibase/music: one player for the whole page, no dependency.
 *
 * One <audio> plays every track. A track is any element with
 * [data-music-track] and data-src (the file or the preview), data-title,
 * data-release (the list next/prev walk), data-peaks (JSON, 0..1) and
 * data-kind ("file", "preview" or "embed": no audio, the release's embed
 * opens instead). The bar #music-player (music_player_bar()) shows what
 * plays: title, play/pause, prev/next, progress on the waveform, time,
 * mute, and a cross ([data-music-close]) that stops the music and hides
 * the bar - Escape does the same while the bar has the focus; playing
 * anything brings it back. Space plays or pauses, the arrows seek 5 s,
 * Shift+arrows change track. A [data-music-release-play="slug"] button
 * plays that release from its first track, then pauses and resumes it. An embed ([data-music-embed] holding a <template>) is built only
 * on its button's click. "Listen in full" ([data-music-full], one
 * <template data-platform> per platform) swaps the preview for the
 * platform's own player of the whole release - from the release's page,
 * or from the bar's button while one of its tracks plays.
 *
 * Waves: a canvas.music-wave with data-peaks is drawn as bars, the played
 * part in --music-wave-played, the rest in --music-wave. With
 * data-music-waves="live" on the bar, its canvas follows the sound
 * (an AnalyserNode) - unless the visitor asks for less motion.
 *
 * window.MusicPlayer: { audio, analyser, current, play(el|data), pause(),
 * toggle(), next(), prev(), seek(seconds), close() }; on document the events
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
        return { src: el.dataset.src || '', kind: el.dataset.kind || 'file', title: el.dataset.title || '', release: el.dataset.release || '', releaseTitle: el.dataset.releaseTitle || '', peaks: parsePeaks(el.dataset.peaks), el };
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
        const same = current && current.src === track.src;
        if (!same) {
            // Through Web Audio, a cross-origin file must come with CORS; plain.onerror falls back when it does not.
            main.crossOrigin = routed && !sameOrigin(track.src) ? 'anonymous' : null;
            use(main);
            main.src = track.src;
            current = track;
            mark(track.el);
            show();
            emit('track', publicTrack());
        }
        audio.play().catch(() => {});
    }

    function pause() { audio.pause(); }
    function toggle() { if (!current) { const first = $('[data-music-track]:not([data-kind="embed"])'); if (first) play(first); return; } audio.paused ? play(current) : pause(); }
    /* A release's own button: its first track, then pause and resume while one of its tracks is the current one. */
    function playRelease(slug) {
        if (current && current.release === slug) { toggle(); return; }
        const first = [...document.querySelectorAll('[data-music-track]')].find((el) => el.dataset.release === slug && el.dataset.kind !== 'embed' && el.dataset.src);
        if (first) play(first);
    }
    /* The cross: the music stops and the bar goes away, until something is played again. */
    function close() {
        const b = bar(), was = publicTrack(), back = currentEl;
        const focused = !!(b && b.contains(document.activeElement));
        audio.pause();
        cancelAnimationFrame(frame);
        try { if (audio.readyState) audio.currentTime = 0; } catch (e) { /* nothing loaded yet */ }
        current = null;
        mark(null);
        state(false);
        if (b) b.hidden = true;
        document.documentElement.classList.remove('music-playing-bar');
        // The focus goes back to the button that started it, when it can take it.
        if (focused && back && back.isConnected) back.focus({ preventScroll: true });
        if (was) emit('close', was);
    }
    function seek(seconds) { if (current && isFinite(audio.duration)) audio.currentTime = Math.max(0, Math.min(audio.duration, seconds)); }

    function siblings() {
        if (!current) return [];
        return [...document.querySelectorAll('[data-music-track]')].filter((el) => el.dataset.release === current.release && el.dataset.kind !== 'embed' && el.dataset.src);
    }
    function step(delta) {
        const list = siblings(), at = list.findIndex((el) => el.dataset.src === current.src);
        const next = list[at + delta];
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
        audio.pause();
        frame.replaceChildren(tpl.content.cloneNode(true));
        frame.hidden = false;
        box.querySelectorAll('[data-music-full-play]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.platform === tpl.dataset.platform)));
        box.scrollIntoView({ behavior: reduced.matches ? 'auto' : 'smooth', block: 'nearest' });
        emit('full', { release, platform: tpl.dataset.platform });
    }
    function build(box) {
        const tpl = $('template', box);
        if (!tpl || box.dataset.built) return;
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
        b.hidden = false;
        document.documentElement.classList.add('music-playing-bar');
        const t = $('[data-music-title]', b), r = $('[data-music-release-title]', b);
        if (t) t.textContent = current.title;
        if (r) r.textContent = current.releaseTitle;
        const wave = $('canvas.music-wave', b);
        if (wave) { wave.dataset.peaks = JSON.stringify(current.peaks || []); draw(wave, 0); }
        const list = siblings(), at = list.findIndex((el) => el.dataset.src === current.src);
        const fullButton = $('[data-music-full-open]', b);
        if (fullButton) fullButton.hidden = !fullBox(current.release);
        const prev = $('[data-music-prev]', b), next = $('[data-music-next]', b);
        if (prev) prev.disabled = at < 0;
        if (next) next.disabled = at < 0 || at >= list.length - 1;
    }
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
        if (currentEl) { currentEl.classList.toggle('is-playing', playing); currentEl.setAttribute('aria-pressed', String(playing)); }
        document.querySelectorAll('[data-music-release-play]').forEach((x) => { const on = playing && !!current && x.dataset.musicReleasePlay === current.release; x.classList.toggle('is-playing', on); x.setAttribute('aria-pressed', String(on)); });
        playing && live() ? loop() : cancelAnimationFrame(frame);
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
    function loop() {
        cancelAnimationFrame(frame);
        const b = bar(), canvas = b && $('canvas.music-wave', b);
        if (!canvas || !live() || audio.paused) return;
        const buffer = new Uint8Array(analyser.frequencyBinCount);
        analyser.getByteFrequencyData(buffer);
        const values = Array.from(buffer.slice(0, 64), (v) => v / 255);
        bars(canvas, values, isFinite(audio.duration) ? audio.currentTime / audio.duration : 0);
        frame = requestAnimationFrame(loop);
    }
    function progress() {
        const p = isFinite(audio.duration) && audio.duration ? audio.currentTime / audio.duration : 0, b = bar();
        if (b) {
            const time = $('[data-music-time]', b), total = $('[data-music-duration]', b), range = $('[data-music-seek]', b);
            if (time) time.textContent = format(audio.currentTime);
            if (total) total.textContent = format(audio.duration);
            if (range && document.activeElement !== range) range.value = String(Math.round(p * 1000));
            const canvas = $('canvas.music-wave', b);
            if (canvas && !live()) draw(canvas, p);
        }
        const row = currentEl && $('canvas.music-wave', currentEl.closest('[data-music-row]') || currentEl);
        if (row) draw(row, p);
        const now = performance.now();
        if (now - lastTime >= 250) { lastTime = now; emit('time', { currentTime: audio.currentTime, duration: audio.duration }); }
    }

    function bind(el) {
        el.addEventListener('play', () => { if (el === audio) { state(true); emit('play', publicTrack()); } });
        el.addEventListener('pause', () => { if (el === audio) { state(false); emit('pause', publicTrack()); } });
        el.addEventListener('timeupdate', () => { if (el === audio) progress(); });
        el.addEventListener('loadedmetadata', () => { if (el === audio) progress(); });
        el.addEventListener('ended', () => { if (el !== audio) return; state(false); emit('ended', publicTrack()); const list = siblings(), at = list.findIndex((x) => x.dataset.src === current.src); if (list[at + 1]) play(list[at + 1]); });
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
        if (embedButton) { event.preventDefault(); build(embedButton.closest('[data-music-embed]')); return; }
        const b = bar();
        if (!b || !b.contains(t)) {
            const wave = t.closest('[data-music-row] canvas.music-wave');
            if (wave && currentEl && wave.closest('[data-music-row]').contains(currentEl)) { const r = wave.getBoundingClientRect(); seek((event.clientX - r.left) / r.width * audio.duration); }
            return;
        }
        if (t.closest('[data-music-close]')) close();
        else if (t.closest('[data-music-full-open]')) { if (current) full(current.release); }
        else if (t.closest('[data-music-toggle]')) toggle();
        else if (t.closest('[data-music-prev]')) step(-1);
        else if (t.closest('[data-music-next]')) step(1);
        else if (t.closest('[data-music-mute]')) { muted = !muted; if (volume) volume.gain.setTargetAtTime(muted ? 0 : 1, context.currentTime, 0.015); else main.muted = muted; if (plain) plain.muted = muted; t.closest('[data-music-mute]').setAttribute('aria-pressed', String(muted)); b.classList.toggle('is-muted', muted); }
        else if (t.closest('canvas.music-wave')) { const r = t.getBoundingClientRect(); seek((event.clientX - r.left) / r.width * audio.duration); }
    });
    document.addEventListener('input', (event) => {
        if (event.target instanceof Element && event.target.matches('#music-player [data-music-seek]')) seek(event.target.value / 1000 * audio.duration);
    });
    document.addEventListener('keydown', (event) => {
        if (!current || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
        const t = event.target;
        // Escape, the focus in the bar (its seek range included): the bar is put away.
        if (event.key === 'Escape') { const b = bar(); if (b && !b.hidden && t instanceof Element && b.contains(t)) { event.preventDefault(); close(); } return; }
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
        play, pause, toggle, seek, close, playRelease,
        next: () => step(1),
        prev: () => step(-1),
        full,
        redraw: drawAll,
    };
})();
