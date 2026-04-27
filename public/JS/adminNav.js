/**
 * ═══════════════════════════════════════════════════════════
 *  GYSL — adminNav.js
 *
 *  Inyecta la navegación flotante admin SOLO si el usuario
 *  es ADMIN. Gestiona highlight de ruta activa y el dropdown
 *  "Más" (Jugadores / Usuarios).
 *
 *  Flujo:
 *  1. Consulta /auth/me
 *  2. Si rol === 'ADMIN' → inyecta admin-bottom-nav.html
 *  3. Ejecuta highlightActiveRoute()
 *  4. Inicializa el dropdown "Más"
 * ═══════════════════════════════════════════════════════════
 */

(function () {
    "use strict";

    const API_BASE    = "/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular";
    const PUBLIC_BASE = API_BASE + "/public";
    const NAV_HTML_URL = PUBLIC_BASE + "/layouts/admin-bottom-nav.html";
    const AUTH_ME_URL  = API_BASE + "/auth/me";

    /* ─────────────────────────────────────────────────────────
       AUTENTICACIÓN
    ───────────────────────────────────────────────────────── */

    async function getUserRole() {
        try {
            const res = await fetch(AUTH_ME_URL, { credentials: "include" });
            if (!res.ok) return null;
            const data = await res.json();
            return (data.success && data.data) ? data.data.rol : null;
        } catch (_) {
            return null;
        }
    }

    /* ─────────────────────────────────────────────────────────
       CARGA DEL HTML DEL NAV
    ───────────────────────────────────────────────────────── */

    async function fetchNavHTML() {
        try {
            const res = await fetch(NAV_HTML_URL, { cache: "no-store" });
            if (!res.ok) throw new Error("HTTP " + res.status);
            return await res.text();
        } catch (err) {
            console.warn("[GYSL AdminNav] No se pudo cargar el nav:", err);
            return "";
        }
    }

    /* ─────────────────────────────────────────────────────────
       HIGHLIGHT DE RUTA ACTIVA
    ───────────────────────────────────────────────────────── */

    /**
     * Mapa route-key → fragmento de URL que identifica la página.
     * Los botones principales usan data-admin-route="<key>".
     * Los sub-items del dropdown usan data-admin-subroute="<key>".
     */
    const ROUTE_MAP = {
        dashboard: "admin-dashboard",
        ligas:     "admin-ligas",
        equipos:   "admin-equipos",
        partidos:  "admin-partidos",
        jugadores: "admin-jugadores",
        usuarios:  "admin-usuarios",
    };

    /** Rutas que pertenecen al dropdown "Más" */
    const MORE_ROUTES = ["jugadores", "usuarios"];

    function highlightActiveRoute() {
        const nav = document.getElementById("gysl-admin-nav");
        if (!nav) return;

        const path = window.location.pathname;

        /* ── Botones principales ── */
        const mainBtns = nav.querySelectorAll(".gysl-admin-nav__item[data-admin-route]");
        mainBtns.forEach(btn => {
            const key   = btn.dataset.adminRoute;
            const match = ROUTE_MAP[key];
            btn.classList.remove("is-active");

            // El botón "Más" se activa si la ruta actual es jugadores o usuarios
            if (key === "more") {
                const isMoreRoute = MORE_ROUTES.some(r => path.includes(ROUTE_MAP[r]));
                if (isMoreRoute) btn.classList.add("is-active");
                return;
            }

            if (match && path.includes(match)) {
                btn.classList.add("is-active");
            }
        });

        /* ── Sub-items del dropdown ── */
        const subItems = nav.querySelectorAll(".gysl-nav-more__item[data-admin-subroute]");
        subItems.forEach(item => {
            const key   = item.dataset.adminSubroute;
            const match = ROUTE_MAP[key];
            item.classList.remove("is-active");
            if (match && path.includes(match)) {
                item.classList.add("is-active");
            }
        });
    }

    /* ─────────────────────────────────────────────────────────
       DROPDOWN "MÁS"
    ───────────────────────────────────────────────────────── */

    function initMoreDropdown() {
        const wrapper = document.getElementById("gysl-more-wrapper");
        const trigger = document.getElementById("gysl-more-btn");
        const menu    = document.getElementById("gysl-more-menu");

        if (!wrapper || !trigger || !menu) return;

        /* Abrir / cerrar al hacer clic en el trigger */
        trigger.addEventListener("click", function (e) {
            e.stopPropagation();
            const isOpen = menu.classList.contains("is-open");
            closeMoreDropdown();                      // cierra si había otro abierto
            if (!isOpen) openMoreDropdown();
        });

        /* Cerrar al pulsar Escape */
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") closeMoreDropdown();
        });

        /* Cerrar al clicar fuera del wrapper */
        document.addEventListener("click", function (e) {
            if (!wrapper.contains(e.target)) closeMoreDropdown();
        });

        /* Evitar que un clic dentro del menú lo cierre */
        menu.addEventListener("click", function (e) {
            e.stopPropagation();
        });
    }

    function openMoreDropdown() {
        const trigger = document.getElementById("gysl-more-btn");
        const menu    = document.getElementById("gysl-more-menu");
        if (!trigger || !menu) return;

        menu.classList.add("is-open");
        trigger.classList.add("is-active");
        trigger.setAttribute("aria-expanded", "true");
    }

    function closeMoreDropdown() {
        const trigger = document.getElementById("gysl-more-btn");
        const menu    = document.getElementById("gysl-more-menu");
        if (!trigger || !menu) return;

        menu.classList.remove("is-open");
        trigger.setAttribute("aria-expanded", "false");

        // Solo quitar is-active del trigger si la página actual NO es jugadores/usuarios
        const path = window.location.pathname;
        const isMoreRoute = MORE_ROUTES.some(r => path.includes(ROUTE_MAP[r]));
        if (!isMoreRoute) {
            trigger.classList.remove("is-active");
        }
    }

    /* ─────────────────────────────────────────────────────────
       INYECCIÓN DEL NAV
    ───────────────────────────────────────────────────────── */

    window.checkAdminNav = async function () {
        const role = await getUserRole();
        window.gyslAdminUserRole = role;

        const isAdmin = role === "ADMIN";
        const existingNav =
            document.getElementById("gysl-admin-nav") ||
            document.querySelector(".gysl-admin-nav-container");

        // Si no es ADMIN, eliminar nav si existe
        if (!isAdmin) {
            if (existingNav) {
                existingNav.remove();
                console.log("❌ [GYSL AdminNav] Nav eliminada: usuario no es ADMIN");
            }
            return;
        }

        // No duplicar
        if (existingNav) return;

        const html = await fetchNavHTML();
        if (!html) return;

        document.body.insertAdjacentHTML("beforeend", html);

        // Esperar un microtask para que el DOM se actualice antes de operar sobre él
        await Promise.resolve();

        highlightActiveRoute();
        initMoreDropdown();

        console.log("✅ [GYSL AdminNav] Nav inyectada para rol ADMIN");
    };

    /* ─────────────────────────────────────────────────────────
       PUNTO DE ENTRADA
    ───────────────────────────────────────────────────────── */

    async function init() {
        await window.checkAdminNav();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

})();