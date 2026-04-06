/**
 * ═══════════════════════════════════════════════════════════
 *  GYSL — adminNav.js
 *  Inyecta navegación flotante admin SOLO si el usuario es ADMIN.
 *  - Consulta /auth/me
 *  - Si rol === 'ADMIN' → inyecta admin-bottom-nav.html
 *  - Si no es ADMIN → no hace nada
 *  - Marca botón activo según la URL actual
 * ═══════════════════════════════════════════════════════════
 */

(function () {
    "use strict";

    const API_BASE = "/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular";
    const PUBLIC_BASE = API_BASE + "/public";
    const NAV_HTML_URL = PUBLIC_BASE + "/layouts/admin-bottom-nav.html";
    const AUTH_ME_URL = API_BASE + "/auth/me";

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

    /**
     * Obtiene el HTML del componente de navegación admin.
     * SOLO para ADMIN.
     * La navegación STAFF no se gestiona en este archivo.
     */
    async function fetchNavHTML() {
        try {
            const res = await fetch(NAV_HTML_URL, { cache: "no-store" });
            if (!res.ok) throw new Error("HTTP " + res.status);
            return await res.text();
        } catch (err) {
            console.warn("[GYSL AdminNav] No se pudo cargar:", err);
            return "";
        }
    }

    /**
     * Detecta la ruta activa y marca el botón correspondiente.
     */
    function highlightActiveRoute() {
        const nav = document.getElementById("gysl-admin-nav");
        if (!nav) return;

        const path = window.location.pathname;
        const buttons = nav.querySelectorAll(".gysl-admin-nav__item[data-admin-route]");

        const routeMap = {
            dashboard: "admin-dashboard",
            ligas: "admin-ligas",
            equipos: "admin-equipos",
            jugadores: "admin-jugadores",
            usuarios: "admin-usuarios"
        };

        buttons.forEach((btn) => {
            const route = btn.dataset.adminRoute;
            const match = routeMap[route];

            btn.classList.remove("is-active");

            if (match && path.includes(match)) {
                btn.classList.add("is-active");
            }
        });
    }

    /**
     * Comprueba dinámicamente si se debe mostrar u ocultar la navegación admin.
     * SOLO se muestra si el usuario autenticado es ADMIN.
     */
    window.checkAdminNav = async function () {
        const role = await getUserRole();
        window.gyslAdminUserRole = role;

        const isAdmin = role === "ADMIN";
        const existingNav =
            document.getElementById("gysl-admin-nav") ||
            document.querySelector(".gysl-admin-nav-container");

        // Si no es ADMIN, eliminar nav si existiera y salir
        if (!isAdmin) {
            if (existingNav) {
                existingNav.remove();
                console.log("❌ [GYSL AdminNav] Nav admin eliminada: usuario no ADMIN");
            }
            return;
        }

        // Si ya existe, no duplicar
        if (existingNav) return;

        const html = await fetchNavHTML();
        if (!html) return;

        document.body.insertAdjacentHTML("beforeend", html);
        highlightActiveRoute();

        console.log("✅ [GYSL AdminNav] Nav inyectada correctamente para rol ADMIN");
    };

    /**
     * Punto de entrada principal.
     */
    async function init() {
        await window.checkAdminNav();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();