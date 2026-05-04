(function () {
    "use strict";



    const BASE_PATH = "/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/public";
    const LOGIN_URL = BASE_PATH + "/layouts/login.html";


    /* ───────── fetch html ───────── */
    async function fetchHTML(url) {
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error("HTTP " + res.status);
            return await res.text();
        } catch (err) {
            console.error("❌ error cargando modal de login:", err);
            return "";
        }
    }

    /* ───────── trigger globally available ───────── */
    window.findLoginTrigger = function () {
        attemptOpenModal();
    };

    let isModalInjected = false;

    // Lógica de sesión movida a sesionactiva.js

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) return resolve();
            const script = document.createElement("script");
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    async function ensureModalInjected() {
        if (document.getElementById("login-modal-overlay")) return true;

        const html = await fetchHTML(LOGIN_URL);
        if (!html) return false;

        document.body.insertAdjacentHTML("beforeend", html);

        const noCache = "?v=" + new Date().getTime();
        await Promise.all([
            loadScript(BASE_PATH + "/JS/register.js" + noCache),
            loadScript(BASE_PATH + "/JS/forgotPassword.js" + noCache)
        ]);

        return true;
    }

    async function attemptOpenModal() {
        if (!isModalInjected) {
            const injected = await ensureModalInjected();
            if (!injected) return;
            isModalInjected = true;
            wireEvents();
        }
        openModal();
    }

    function openModal() {
        const overlay = document.getElementById("login-modal-overlay");
        const wrapper = document.getElementById("login-wrapper");

        // Reset states just in case
        if (wrapper) wrapper.classList.remove("right-panel-active");

        // Reset forgot password flow
        document.getElementById("login-form") && (document.getElementById("login-form").style.display = "flex");
        document.getElementById("forgot-password-flow") && (document.getElementById("forgot-password-flow").style.display = "none");
        document.getElementById("forgot-step-1") && (document.getElementById("forgot-step-1").style.display = "flex");
        document.getElementById("forgot-step-2") && (document.getElementById("forgot-step-2").style.display = "none");
        document.getElementById("forgot-step-3") && (document.getElementById("forgot-step-3").style.display = "none");

        if (overlay) overlay.classList.add("active");
    }

    function closeModal() {
        const overlay = document.getElementById("login-modal-overlay");

        // Si hay un registro pendiente de verificar, cancelarlo y borrar el usuario
        if (typeof window.cancelarRegistroPendiente === "function") {
            window.cancelarRegistroPendiente();
        }

        if (overlay) overlay.classList.remove("active");
    }


    /* ───────── Wire Events for Modal ───────── */
    function wireEvents() {
        const overlay = document.getElementById("login-modal-overlay");
        const wrapper = document.getElementById("login-wrapper");
        const closeBtn = document.getElementById("login-close-btn");

        // EVENT DELEGATION: Aseguramos que los clicks siempre reaccionen incluso tras inyectar HTML
        if (wrapper && !wrapper.dataset.animationWired) {
            wrapper.dataset.animationWired = "true";

            wrapper.addEventListener('click', (e) => {
                if (e.target.closest('#overlay-signUp')) {
                    console.log("👉 Register panel animation activated");
                    e.preventDefault();
                    wrapper.classList.add("right-panel-active");
                }

                if (e.target.closest('#overlay-signIn')) {
                    console.log("👉 Login panel animation activated");
                    e.preventDefault();
                    wrapper.classList.remove("right-panel-active");
                }
            });
        }

        // CLOSE
        if (closeBtn && !closeBtn.dataset.wired) {
            closeBtn.dataset.wired = "true";
            closeBtn.addEventListener("click", closeModal);
        }

        if (overlay && !overlay.dataset.wired) {
            overlay.dataset.wired = "true";
            overlay.addEventListener("click", e => {
                if (e.target === overlay) closeModal();
            });
        }

        // INIT MODULARIZED LOGIC
        // Initializamos el registro y la recuperación de clave
        if (window.initForgotPasswordFlow) window.initForgotPasswordFlow();
        if (window.initRegisterForm) window.initRegisterForm();

        // ============================================
        // FORMS SUBMISSION (Fetch real) -> LOGIN
        // ============================================
        const loginForm = document.getElementById("login-form");
        if (loginForm && !loginForm.dataset.wired) {
            loginForm.dataset.wired = "true";

            loginForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                const formData = new FormData(loginForm);

                try {
                    const exactUrl = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/login';

                    const res = await fetch(exactUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'include' // importante para sesión/cookies
                    });

                    const data = await res.json();

                    if (res.ok && data.success) {
                        loginForm.reset();

                        // Actualizar el header con nombre + apellido llamando a la función global
                        if (data.data && typeof window.actualizarHeaderUsuario === 'function') {
                            window.actualizarHeaderUsuario(data.data);
                        }

                        // Comprobar e inyectar nav de admin si corresponde
                        if (typeof window.checkAdminNav === 'function') {
                            window.checkAdminNav();
                        }

                        closeModal();
                    } else {
                        showToast(data.message || "Credenciales inválidas", "error");
                    }
                } catch (error) {
                    console.error("❌ Error de red:", error);
                    showToast("Falló la conexión al servidor. Inténtalo de nuevo.", "error");
                }
            });
        }

        // La comprobación de sesión activa al montar el modal ya la hace sesionactiva.js
        // comprobarSesionActiva();
    }

    /* ───────── Global Password Toggle ───────── */
    document.addEventListener("click", (e) => {
        const btn = e.target.closest(".pwd-toggle-btn");
        if (!btn) return;

        const targetId = btn.dataset.target;
        const input = document.getElementById(targetId);

        if (!input) return;

        // Toggle the type attribute
        input.type = input.type === "password" ? "text" : "password";
    });

})();