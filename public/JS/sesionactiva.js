(function () {
    "use strict";

    const BASE_PATH = "/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/public";
    const MINI_MENU_URL = BASE_PATH + "/layouts/mini-menu.html";

    let miniMenuInjected = false;

    function waitForElement(selector, timeout = 3000) {
        return new Promise((resolve) => {
            const el = document.querySelector(selector);
            if (el) return resolve(el);

            const interval = 50;
            let elapsed = 0;
            const timer = setInterval(() => {
                elapsed += interval;
                const found = document.querySelector(selector);
                if (found) {
                    clearInterval(timer);
                    resolve(found);
                } else if (elapsed >= timeout) {
                    clearInterval(timer);
                    resolve(null);
                }
            }, interval);
        });
    }

    async function injectMiniMenu() {
        if (miniMenuInjected) return;

        const host = await waitForElement("#gysl-mini-menu-host");
        if (!host) return;

        try {
            const res = await fetch(MINI_MENU_URL, { cache: "no-store" });
            if (!res.ok) return;

            const html = await res.text();
            host.innerHTML = html;
            miniMenuInjected = true;
        } catch (_) { }
    }

    window.actualizarHeaderUsuario = function (usuario) {
        const label = document.querySelector(".gysl-btn-user__label");
        const btn = document.getElementById("gysl-btn-user");
        const nameEl = document.getElementById("gysl-dropdown-name");
        const emailEl = document.getElementById("gysl-dropdown-email");

        if (!btn || !label) return;

        if (usuario && usuario.nombre) {
            const nombreCompleto = (usuario.nombre + " " + (usuario.apellido || "")).trim();

            label.textContent = nombreCompleto;
            btn.setAttribute("data-auth", "true");

            if (nameEl) nameEl.textContent = nombreCompleto;
            if (emailEl) emailEl.textContent = usuario.email || "";
        } else {
            label.textContent = "My account";
            btn.setAttribute("data-auth", "false");
            closeMiniMenu();
        }

        aplicarMenuStaff(usuario);
    };

    function aplicarMenuStaff(usuario) {
        const menuList = document.querySelector(".gysl-mini-menu__list");
        const miniMenu = document.getElementById("btn-mini-menu");

        if (!menuList || !miniMenu) return;

        const bloquesPrevios = miniMenu.querySelectorAll('[data-staff-menu="true"]');
        bloquesPrevios.forEach(el => el.remove());

        const rolNormalizado = String(usuario?.rol || "").trim().toUpperCase();

        if (!usuario || rolNormalizado !== "STAFF") return;

        const staffItems = `
        <li role="none" data-staff-menu="true">
            <button class="gysl-mini-menu__item" data-mini="equipos" role="menuitem"
                onclick="gyslMiniAction('equipos')">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2l7 4v6c0 5-3.5 9-7 10-3.5-1-7-5-7-10V6l7-4z" />
                </svg>
                <span>Equipos</span>
            </button>
        </li>

        <li role="none" data-staff-menu="true">
            <button class="gysl-mini-menu__item" data-mini="gestion_jugadores" role="menuitem"
                onclick="gyslMiniAction('gestion_jugadores')">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <line x1="19" y1="8" x2="19" y2="14" />
                    <line x1="22" y1="11" x2="16" y2="11" />
                </svg>
                <span>Gesti&#xf3;n de jugadores</span>
            </button>
        </li>

        <li role="none" data-staff-menu="true">
            <button class="gysl-mini-menu__item" data-mini="incidencias" role="menuitem"
                onclick="gyslMiniAction('incidencias')">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span>Incidencias</span>
            </button>
        </li>
        `;

        menuList.insertAdjacentHTML("beforeend", staffItems);
    }

    async function comprobarSesionActiva() {
        try {
            const res = await fetch(
                "/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/me",
                { credentials: "include" }
            );

            if (!res.ok) return window.actualizarHeaderUsuario(null);

            const data = await res.json();

            if (data.success && data.data) {
                window.actualizarHeaderUsuario(data.data);
            } else {
                window.actualizarHeaderUsuario(null);
            }
        } catch (_) {
            window.actualizarHeaderUsuario(null);
        }
    }

    function closeMiniMenu() {
        const btn = document.getElementById("gysl-btn-user");
        const miniMenu = document.getElementById("btn-mini-menu");
        if (!miniMenu) return;

        miniMenu.classList.remove("is-open");
        miniMenu.setAttribute("aria-hidden", "true");
        if (btn) btn.setAttribute("aria-expanded", "false");
    }

    function toggleMiniMenu() {
        const btn = document.getElementById("gysl-btn-user");
        const miniMenu = document.getElementById("btn-mini-menu");
        if (!miniMenu || !btn) return;

        const willOpen = !miniMenu.classList.contains("is-open");
        miniMenu.classList.toggle("is-open", willOpen);
        miniMenu.setAttribute("aria-hidden", willOpen ? "false" : "true");
        btn.setAttribute("aria-expanded", willOpen ? "true" : "false");
    }

    window.closeMiniMenu = closeMiniMenu;

    window.gyslMiniAction = function (action) {
        closeMiniMenu();
        console.log("Mini menu action:", action);

        switch (action) {
            case 'equipos':
                window.location.href = BASE_PATH + "/staff-equipos.html";
                break;
            case 'gestion_jugadores':
                window.location.href = BASE_PATH + "/staff-jugadores.html";
                break;
            case 'registrar_jugadores':
                window.location.href = BASE_PATH + "/staff-jugadores.html";
                break;
            default:
                // Otras acciones globales...
                break;
        }
    };

    window.gyslLogout = async function () {
        closeMiniMenu();
        try {
            const exactUrl =
                "/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/logout";

            const res = await fetch(exactUrl, {
                method: "POST",
                credentials: "include",
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.actualizarHeaderUsuario(null);

                if (typeof window.checkAdminNav === "function") {
                    window.checkAdminNav();
                }

                if (window.location.pathname.includes("admin-")) {
                    window.location.href = "home.html";
                } else {
                    alert("Has cerrado sesión correctamente.");
                }
            } else {
                alert("Error al cerrar sesión: " + (data.message || ""));
            }
        } catch (_) {
            alert("Falló la conexión al servidor. Inténtalo de nuevo.");
        }
    };

    function initDropdownMode() {
        const btn = document.getElementById("gysl-btn-user");
        if (!btn) return;

        btn.onclick = null;
        btn.removeAttribute("onclick");

        btn.setAttribute("aria-haspopup", "menu");
        btn.setAttribute("aria-expanded", "false");

        btn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();

            const isAuth = btn.getAttribute("data-auth") === "true";

            if (!isAuth) {
                if (typeof window.findLoginTrigger === "function") {
                    window.findLoginTrigger();
                }
                return;
            }

            toggleMiniMenu();
        });

        document.addEventListener("click", (e) => {
            const miniMenu = document.getElementById("btn-mini-menu");
            if (!miniMenu || !miniMenu.classList.contains("is-open")) return;

            const dropdownRoot = btn.closest(".gysl-user-dropdown");
            if (!dropdownRoot || !dropdownRoot.contains(e.target)) {
                closeMiniMenu();
            }
        });

        document.addEventListener("keydown", (e) => {
            if (e.key !== "Escape") return;
            const miniMenu = document.getElementById("btn-mini-menu");
            if (!miniMenu || !miniMenu.classList.contains("is-open")) return;

            closeMiniMenu();
            btn.focus();
        });
    }

    document.addEventListener("DOMContentLoaded", async () => {
        await injectMiniMenu();
        initDropdownMode();
        await comprobarSesionActiva();
    });
})();