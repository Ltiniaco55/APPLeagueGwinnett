/**
 * ═══════════════════════════════════════════════════════════
 *  GYSL — header_secun.js
 *  Injects the secondary competition sub-nav below #gysl-header.
 *  - Prevents duplicate injection
 *  - Preserves `liga` and `formato` query params across links
 *  - Highlights the active link based on current pathname
 *  - Oculta Clasificación cuando formato es ELIMINATORIA o AMISTOSO
 * ═══════════════════════════════════════════════════════════
 */

(function () {
    'use strict';

    const BASE_PATH = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/public';
    const HEADER_SECUN_URL = BASE_PATH + '/layouts/header_secun.html';

    const MARKER_ID       = 'gysl-header-secun';
    const MAIN_HEADER_ID  = 'gysl-header';
    const LINKS_SELECTOR  = '.gysl-header-secun__link';

    /* ── Helpers ───────────────────────────────────────────── */

    async function fetchLayout(url) {
        try {
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error(`HTTP ${res.status} — ${url}`);
            return await res.text();
        } catch (err) {
            console.warn('[GYSL HeaderSecun] Could not load:', url, err);
            return '';
        }
    }

    function htmlToNodes(html) {
        const tpl = document.createElement('template');
        tpl.innerHTML = html.trim();
        return Array.from(tpl.content.childNodes);
    }

    /* ── Injection ─────────────────────────────────────────── */

    function injectHeaderSecun(html) {
        const nodes = htmlToNodes(html);
        const mainHeader = document.getElementById(MAIN_HEADER_ID);

        if (mainHeader) {
            // Insert every node right after #gysl-header
            let refNode = mainHeader.nextSibling;
            nodes.forEach(node => {
                mainHeader.parentNode.insertBefore(node, refNode);
            });
        } else {
            // Fallback: prepend as first child of body
            const first = document.body.firstChild;
            nodes.forEach(node => document.body.insertBefore(node, first));
        }
    }

    /* ── Query-param management ────────────────────────────── */

    function getCompetitionParams() {
        const params = new URLSearchParams(window.location.search);
        const result = {};

        if (params.has('liga'))    result.liga    = params.get('liga');
        if (params.has('formato')) result.formato = params.get('formato');

        return result;
    }

    function buildHeaderSecunLinks() {
        const competitionParams = getCompetitionParams();
        const links = document.querySelectorAll(LINKS_SELECTOR);

        links.forEach(link => {
            const baseHref = link.getAttribute('href');
            if (!baseHref || baseHref === '#') return;

            // Strip any existing query string from the template href
            const cleanHref = baseHref.split('?')[0];

            // Build new search string from current competition params
            const qs = new URLSearchParams(competitionParams).toString();
            link.setAttribute('href', qs ? `${cleanHref}?${qs}` : cleanHref);
        });
    }

    /* ── Format-based visibility ───────────────────────────── */

    /**
     * Oculta el enlace Clasificación cuando el formato es ELIMINATORIA o AMISTOSO.
     * El <li> debe tener data-secun-hide="clasificacion" en header_secun.html.
     */
    function aplicarVisibilidadPorFormato() {
        const params = new URLSearchParams(window.location.search);
        const formato = (params.get('formato') || '').toUpperCase().trim();

        // Clasificación solo visible en formato JORNADAS
        var liClasificacion = document.querySelector('[data-secun-hide="clasificacion"]');
        if (liClasificacion) {
            if (formato === 'ELIMINATORIA' || formato === 'AMISTOSO') {
                liClasificacion.style.display = 'none';
            } else {
                liClasificacion.style.display = '';
            }
        }
    }

    /* ── Active link detection ─────────────────────────────── */

    function initActiveHeaderSecunLink() {
        const links = document.querySelectorAll(LINKS_SELECTOR);
        if (!links.length) return;

        const path = window.location.pathname;

        links.forEach(link => {
            link.classList.remove('active');

            const href = link.getAttribute('href');
            if (!href || href === '#') return;

            // Compare by filename only (ignore query string)
            const hrefFile = href.split('?')[0];
            if (path.endsWith(hrefFile)) {
                link.classList.add('active');
            }
        });
    }

    /* ── Init ──────────────────────────────────────────────── */

    async function init() {
        // Guard: prevent duplicate injection
        if (document.getElementById(MARKER_ID)) return;

        const html = await fetchLayout(HEADER_SECUN_URL);
        if (!html) return;

        injectHeaderSecun(html);
        buildHeaderSecunLinks();
        aplicarVisibilidadPorFormato();
        initActiveHeaderSecunLink();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
