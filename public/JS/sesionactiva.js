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

        renderMiniMenuPorRol(usuario);
    };

    function renderMiniMenuPorRol(usuario) {
        const menuList = document.querySelector(".gysl-mini-menu__list");
        const miniMenu = document.getElementById("btn-mini-menu");

        if (!menuList || !miniMenu) return;

        const bloquesPrevios = miniMenu.querySelectorAll('[data-dynamic-menu="true"]');
        bloquesPrevios.forEach(el => el.remove());

        const rol = String(usuario?.rol || "").trim().toUpperCase();

        let dynamicItems = '';

        if (rol === "STAFF") {
            dynamicItems += `
            <li role="none" data-dynamic-menu="true">
                <button class="gysl-mini-menu__item" onclick="gyslMiniAction('equipos')">
                    <span>Mis equipos</span>
                </button>
            </li>

            <li role="none" data-dynamic-menu="true">
                <button class="gysl-mini-menu__item" onclick="gyslMiniAction('gestion_jugadores')">
                    <span>Gestión de jugadores</span>
                </button>
            </li>
        `;
        }

        if (rol === "ADMIN") {
            dynamicItems += `
            <li role="none" data-dynamic-menu="true">
                <button class="gysl-mini-menu__item" onclick="gyslMiniAction('panel_admin')">
                    <span>Panel admin</span>
                </button>
            </li>
        `;
        }

        menuList.insertAdjacentHTML("beforeend", dynamicItems);
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