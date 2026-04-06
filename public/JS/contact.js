(function () {
    "use strict";

    const BASE_PATH = "/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/public";
    const CONTACT_URL = BASE_PATH + "/layouts/contact.html";

    /* ───────── fetch html ───────── */
    async function fetchHTML(url) {
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error("HTTP " + res.status);
            return await res.text();
        } catch (_) {
            return "";
        }
    }

    /* ───────── buscar botón contact ───────── */
    function findContactTrigger() {
        const byData = document.querySelector('.gysl-nav__links a[data-nav="contact"]');
        if (byData) return byData;

        const links = document.querySelectorAll(".gysl-nav__links a");

        for (const a of links) {
            if ((a.textContent || "").toLowerCase().includes("contact")) {
                return a;
            }
        }

        return null;
    }

    /* ───────── esperar header inyectado ───────── */
    async function waitForTrigger() {
        for (let i = 0; i < 50; i++) {
            const el = findContactTrigger();
            if (el) return el;

            await new Promise(r => setTimeout(r, 100));
        }

        return null;
    }

    /* ───────── abrir modal ───────── */
    function openModal() {
        const overlay = document.getElementById("gysl-contact-overlay");
        if (!overlay) return;

        overlay.classList.add("active");
        document.body.style.overflow = "hidden";
    }

    /* ───────── cerrar modal ───────── */
    function closeModal() {
        const overlay = document.getElementById("gysl-contact-overlay");
        if (!overlay) return;

        overlay.classList.remove("active");
        document.body.style.overflow = "";
    }

    /* ───────── inyectar modal ───────── */
    async function ensureModalInjected() {
        if (document.getElementById("gysl-contact-overlay")) {
            return true;
        }

        const html = await fetchHTML(CONTACT_URL);
        if (!html) return false;

        document.body.insertAdjacentHTML("beforeend", html);
        return true;
    }

    /* ───────── eventos modal ───────── */
    function wireEvents() {
        const overlay = document.getElementById("gysl-contact-overlay");
        if (!overlay) return;

        const closeBtn = document.getElementById("gysl-contact-close");

        if (closeBtn) {
            closeBtn.addEventListener("click", closeModal);
        }

        overlay.addEventListener("click", e => {
            if (e.target === overlay) closeModal();
        });
    }

    /* ───────── init ───────── */
    async function init() {
        const trigger = await waitForTrigger();
        if (!trigger) return;

        trigger.addEventListener("click", async (e) => {
            e.preventDefault();

            const ok = await ensureModalInjected();
            if (!ok) return;

            wireEvents();
            openModal();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

})();