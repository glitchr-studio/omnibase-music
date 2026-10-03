/*
 * Reframing the home page's picture, for an administrator, on the page
 * itself (templates/client/_hero_video.html.twig): "Reframe" makes the
 * picture draggable - the point kept in view follows the hand - and the
 * slider brings it closer; "Save" keeps both as settings (music.hero.position,
 * music.hero.zoom, through HeroFrameController); "Reset" gives the site's own
 * framing back. Nothing here is loaded for a visitor.
 */
(function () {
    if (window.MusicHeroFrame) return;
    window.MusicHeroFrame = true;

    function setup(box) {
        if (box.dataset.ready) return;
        box.dataset.ready = '1';
        var hero = box.parentElement;
        var media = Array.prototype.slice.call(hero.querySelectorAll('[data-music-hero-media]'));
        if (!media.length) return;
        var tools = box.querySelector('.music-hero-frame-tools');
        var toggle = box.querySelector('[data-frame-action="edit"]');
        var range = box.querySelector('[data-frame-zoom]');
        var state = null, saved = null, drag = null;

        // The element shown (the film when it plays, else the still) and its natural size.
        function shown() {
            for (var i = 0; i < media.length; i++) {
                if (media[i].offsetParent !== null && getComputedStyle(media[i]).display !== 'none') return media[i];
            }
            return media[0];
        }
        function natural(el) {
            return el.tagName === 'VIDEO' ? [el.videoWidth || 1920, el.videoHeight || 1080] : [el.naturalWidth || 1, el.naturalHeight || 1];
        }
        // How far the picture overflows its box, at the zoom asked: what a drag can move.
        function overflow(el, zoom) {
            var n = natural(el), w = el.clientWidth, h = el.clientHeight;
            var s = Math.max(w / n[0], h / n[1]);
            return [Math.max(0, n[0] * s * zoom - w), Math.max(0, n[1] * s * zoom - h)];
        }
        // The current position in percent, whatever the stylesheet said (a calc() in px included).
        function current(el) {
            var parts = getComputedStyle(el).objectPosition.split(' ');
            var o = overflow(el, 1);
            return parts.map(function (p, i) {
                if (p.slice(-1) === '%') return parseFloat(p);
                return o[i] ? Math.min(100, Math.max(0, -parseFloat(p) / o[i] * 100)) : 50;
            });
        }
        function apply() {
            var pos = state.x.toFixed(2) + '% ' + state.y.toFixed(2) + '%';
            media.forEach(function (el) {
                el.style.objectPosition = pos;
                el.style.transform = 'scale(' + state.zoom + ')';
                el.style.transformOrigin = pos;
                el.style.animation = 'none';
            });
        }

        function edit() {
            var el = shown(), p = current(el);
            saved = media.map(function (m) { return m.getAttribute('style') || ''; });
            // The zoom shown: the saved one, else the one the site's stylesheet gives.
            var shownZoom = getComputedStyle(el).transform === 'none' ? 1 : new DOMMatrix(getComputedStyle(el).transform).a;
            state = { x: p[0], y: p[1], zoom: Math.min(2.5, Math.max(1, box.dataset.position ? (parseFloat(box.dataset.zoom) || 1) : shownZoom)) };
            range.value = state.zoom;
            hero.classList.add('is-framing');
            tools.hidden = false; toggle.hidden = true;
            if (window.StickyStops && window.StickyStops.pause) window.StickyStops.pause();
            apply();
        }
        function leave() {
            hero.classList.remove('is-framing');
            tools.hidden = true; toggle.hidden = false;
            if (window.StickyStops && window.StickyStops.resume) window.StickyStops.resume();
        }
        function send(body) {
            return fetch(box.dataset.url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': box.dataset.token },
                body: JSON.stringify(body)
            }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
        }

        box.addEventListener('click', function (e) {
            var action = e.target.closest('[data-frame-action]');
            if (!action) return;
            var what = action.dataset.frameAction;
            if (what === 'edit') edit();
            else if (what === 'cancel') { media.forEach(function (m, i) { m.setAttribute('style', saved[i]); }); leave(); }
            else if (what === 'save') {
                send({ position: state.x.toFixed(2) + '% ' + state.y.toFixed(2) + '%', zoom: Math.round(state.zoom * 100) / 100 })
                    .then(function () { box.dataset.zoom = state.zoom; box.dataset.position = state.x.toFixed(2) + '% ' + state.y.toFixed(2) + '%'; leave(); })
                    .catch(function () { alert('The frame could not be saved.'); });
            } else if (what === 'reset') {
                send({ reset: true }).then(function () { media.forEach(function (m) { m.removeAttribute('style'); }); box.dataset.zoom = 1; box.dataset.position = ''; leave(); });
            }
        });
        range.addEventListener('input', function () { if (state) { state.zoom = parseFloat(range.value); apply(); } });

        hero.addEventListener('pointerdown', function (e) {
            if (!state || !hero.classList.contains('is-framing') || e.target.closest('.music-hero-frame')) return;
            var el = shown();
            drag = { x: e.clientX, y: e.clientY, from: [state.x, state.y], over: overflow(el, state.zoom) };
            hero.setPointerCapture(e.pointerId);
            e.preventDefault();
        });
        hero.addEventListener('pointermove', function (e) {
            if (!drag) return;
            // Dragging the picture right shows more of its left: the position goes down.
            if (drag.over[0]) state.x = Math.min(100, Math.max(0, drag.from[0] - (e.clientX - drag.x) / drag.over[0] * 100));
            if (drag.over[1]) state.y = Math.min(100, Math.max(0, drag.from[1] - (e.clientY - drag.y) / drag.over[1] * 100));
            apply();
        });
        ['pointerup', 'pointercancel'].forEach(function (type) { hero.addEventListener(type, function () { drag = null; }); });
    }

    function scan() { document.querySelectorAll('[data-music-hero-frame]').forEach(setup); }
    scan();
    document.addEventListener('DOMContentLoaded', scan);
    window.addEventListener('transparent:load', scan);
})();
