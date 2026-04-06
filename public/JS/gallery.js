/**
 * ==========================================================
 * GYSL — gallery.js
 * Dropdown jump to sections (smooth scroll)
 * ==========================================================
 */
(function () {
    "use strict";

    function scrollToHash(hash) {
        const el = document.querySelector(hash);
        if (!el) return;

        const y = el.getBoundingClientRect().top + window.pageYOffset - 110; // offset for sticky header
        window.scrollTo({ top: y, behavior: "smooth" });
    }

    function initJump() {
        const select = document.getElementById("gysl-gallery-jump");
        if (!select) return;

        select.addEventListener("change", () => {
            const value = select.value;
            if (!value) return;
            scrollToHash(value);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initJump);
    } else {
        initJump();
    }
})();