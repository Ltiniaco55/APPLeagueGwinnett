/**
 * ═══════════════════════════════════════════════════════════
 *  GYSL — layout.js (UPDATED)
 *  Injects Header and Footer as direct children of <body>.
 *  - Header always first child of body
 *  - Footer always last child of body
 *  - Prevent duplicates if script runs twice
 *  - Burger toggle + active link + footer year + fav btn + user btn
 * ═══════════════════════════════════════════════════════════
 */

(function () {
    'use strict';

    const BASE_PATH = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/public';
    const HEADER_URL = BASE_PATH + '/layouts/header.html';
    const FOOTER_URL = BASE_PATH + '/layouts/footer.html';

    const HEADER_MARKER_ID = 'gysl-header'; // must exist in header.html
    const FOOTER_MARKER_ID = 'gysl-footer'; // must exist in footer.html

    async function fetchLayout(url) {
        try {
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error(`HTTP ${res.status} — ${url}`);
            return await res.text();
        } catch (err) {
            console.warn('[GYSL Layout] Could not load:', url, err);
            return '';
        }
    }

    function htmlToNodes(html) {
        const tpl = document.createElement('template');
        tpl.innerHTML = html.trim();
        return Array.from(tpl.content.childNodes);
    }

    function injectAtBodyStart(html) {
        const nodes = htmlToNodes(html);
        const first = document.body.firstChild;
        nodes.forEach(node => document.body.insertBefore(node, first));
    }

    function injectAtBodyEnd(html) {
        const nodes = htmlToNodes(html);
        nodes.forEach(node => document.body.appendChild(node));
    }

    function initBurger() {
        const burger = document.getElementById('gysl-burger');
        const menu = document.getElementById('gysl-nav-menu');
        if (!burger || !menu) return;

        burger.addEventListener('click', () => {
            const isOpen = menu.classList.toggle('open');
            burger.setAttribute('aria-expanded', String(isOpen));
            burger.textContent = isOpen ? '✕' : '☰';
        });

        document.addEventListener('click', (e) => {
            if (!burger.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('open');
                burger.setAttribute('aria-expanded', 'false');
                burger.textContent = '☰';
            }
        });
    }

    function initActiveLink() {
        const links = document.querySelectorAll('.gysl-nav__links a');
        if (!links.length) return;

        const path = window.location.pathname;

        links.forEach(link => {
            link.classList.remove('active');
            const href = link.getAttribute('href');
            if (!href || href === '#') return;

            if (path.endsWith(href) || path.includes(href)) {
                link.classList.add('active');
            }
        });
    }

    function initFooterYear() {
        const yearEl = document.getElementById('gysl-footer-year');
        if (yearEl) yearEl.textContent = new Date().getFullYear();
    }

    function initFavBtn() {
        const btn = document.getElementById('gysl-btn-fav');
        if (!btn) return;

        let active = false;
        btn.addEventListener('click', () => {
            active = !active;

            btn.style.color = active ? 'var(--gysl-yellow)' : '';
            btn.style.borderColor = active ? 'var(--gysl-yellow)' : '';
            btn.style.background = active ? 'rgba(241,184,45,.15)' : '';

            const poly = btn.querySelector('polygon');
            if (poly) poly.setAttribute('fill', active ? 'currentColor' : 'none');
        });
    }

    function initUserBtn() {
        const btn = document.getElementById('gysl-btn-user');
        if (!btn) return;

        btn.addEventListener('click', () => {
            document.dispatchEvent(new CustomEvent('gysl:userBtnClicked', { bubbles: true }));
            btn.classList.toggle('gysl-btn-user--active');
        });
    }

    async function checkSession() {
        try {
            const res = await fetch('/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/me', {
                credentials: 'include'
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.success && data.data && data.data.nombre) {
                const label = document.querySelector('.gysl-btn-user__label');
                const btn = document.getElementById('gysl-btn-user');
                if (label) {
                    label.textContent = (data.data.nombre + ' ' + (data.data.apellido || '')).trim();
                }
                // Usuario autenticado: el botón ya no abre el modal de login
                if (btn) btn.onclick = null;
            }
        } catch (_) { /* sin sesión */ }
    }

    async function init() {
        const hasHeader = document.getElementById(HEADER_MARKER_ID);
        const hasFooter = document.getElementById(FOOTER_MARKER_ID);

        if (!hasHeader) {
            const headerHTML = await fetchLayout(HEADER_URL);
            if (headerHTML) injectAtBodyStart(headerHTML);
        }

        if (!hasFooter) {
            const footerHTML = await fetchLayout(FOOTER_URL);
            if (footerHTML) injectAtBodyEnd(footerHTML);
        }

        initBurger();
        initActiveLink();
        initFooterYear();
        initFavBtn();
        initUserBtn();
        checkSession(); // Mostrar nombre si ya hay sesión activa
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();