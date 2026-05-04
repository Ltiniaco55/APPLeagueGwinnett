/**
 * ==========================================================
 * GYSL — gallery.js
 * Smooth scroll para navegación de secciones de galería
 * ==========================================================
 */
(function () {
    "use strict";

    const OFFSET_HEADER = 110;

    function scrollToHash(hash) {
        const el = document.querySelector(hash);
        if (!el) return;

        const y = el.getBoundingClientRect().top + window.pageYOffset - OFFSET_HEADER;

        window.scrollTo({
            top: y,
            behavior: "smooth"
        });
    }

    function initGalleryNav() {
        const links = document.querySelectorAll('.gysl-gallery-nav__item');

        if (!links.length) return;

        links.forEach(link => {
            link.addEventListener('click', (e) => {
                const hash = link.getAttribute('href');

                if (!hash || !hash.startsWith('#')) return;

                e.preventDefault();

                scrollToHash(hash);

                history.replaceState(null, null, hash);
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initGalleryNav);
    } else {
        initGalleryNav();
    }
})();