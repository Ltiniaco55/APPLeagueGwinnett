(function () {
    "use strict";
    console.log("🚀 forgotPassword.js cargado");

    const BASE = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular';

    window.initForgotPasswordFlow = function () {
        const loginForm = document.getElementById("login-form");
        const forgotPwdLink = document.getElementById("forgot-password-link");
        const forgotFlow = document.getElementById("forgot-password-flow");
        const backToLoginBtn = document.getElementById("back-to-login-btn");

        const step1 = document.getElementById("forgot-step-1");
        const step2 = document.getElementById("forgot-step-2");
        const step3 = document.getElementById("forgot-step-3");

        const btnSendCode = document.getElementById("btn-send-code");
        const btnVerify = document.getElementById("btn-verify-code");
        const btnResetPwd = document.getElementById("btn-reset-password");

        if (!forgotPwdLink || forgotPwdLink.dataset.wired) return;
        forgotPwdLink.dataset.wired = "true";

        let forgotEmail = "";

        function showStepMsg(step, msg, isError = true) {
            let el = step.querySelector(".forgot-step-msg");
            if (!el) {
                el = document.createElement("p");
                el.className = "forgot-step-msg";
                el.style.cssText = "font-size:12px; margin:8px 0 0; text-align:center;";
                step.appendChild(el);
            }
            el.textContent = msg;
            el.style.color = isError ? "#e05260" : "#22c55e";
            el.style.display = "block";
        }
        function hideStepMsg(step) {
            const el = step.querySelector(".forgot-step-msg");
            if (el) el.style.display = "none";
        }

        function setLoading(btn, loading) {
            const textEl = btn.querySelector(".gysl-btn-ui-login__text") || btn;
            if (loading) {
                btn.disabled = true;
                textEl.textContent = "Cargando…";
            } else {
                btn.disabled = false;
            }
        }

        forgotPwdLink.addEventListener('click', (e) => {
            e.preventDefault();
            if (loginForm) loginForm.style.display = "none";
            if (forgotFlow) forgotFlow.style.display = "flex";
            if (step1) { step1.style.display = "flex"; hideStepMsg(step1); }
            if (step2) step2.style.display = "none";
            if (step3) step3.style.display = "none";
        });

        if (backToLoginBtn) {
            backToLoginBtn.addEventListener('click', () => {
                if (forgotFlow) forgotFlow.style.display = "none";
                if (loginForm) loginForm.style.display = "flex";
                forgotEmail = "";
            });
        }

        if (btnSendCode) {
            btnSendCode.addEventListener('click', async () => {
                const email = (document.getElementById("forgot-email")?.value || "").trim();
                if (!email) {
                    showStepMsg(step1, "Por favor, introduce un email válido.");
                    return;
                }

                hideStepMsg(step1);
                setLoading(btnSendCode, true);
                const origText = btnSendCode.querySelector(".gysl-btn-ui-login__text")?.textContent || "";

                try {
                    const res = await fetch(`${BASE}/auth/password/solicitar-codigo`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email })
                    });
                    const data = await res.json();

                    if (res.ok && data.success) {
                        forgotEmail = email;
                        if (step1) step1.style.display = "none";
                        if (step2) { step2.style.display = "flex"; hideStepMsg(step2); }
                    } else {
                        showStepMsg(step1, data.message || "No se pudo enviar el código. ¿Existe ese email?");
                    }
                } catch (_) {
                    showStepMsg(step1, "Error de red. Inténtalo de nuevo.");
                } finally {
                    btnSendCode.disabled = false;
                    const t = btnSendCode.querySelector(".gysl-btn-ui-login__text");
                    if (t) t.textContent = origText;
                }
            });
        }

        if (btnVerify) {
            btnVerify.addEventListener('click', async () => {
                const code = (document.getElementById("forgot-code")?.value || "").trim();
                if (!code) {
                    showStepMsg(step2, "Introduce el código de 6 dígitos.");
                    return;
                }

                hideStepMsg(step2);
                setLoading(btnVerify, true);
                const origText = btnVerify.querySelector(".gysl-btn-ui-login__text")?.textContent || "";

                try {
                    const res = await fetch(`${BASE}/auth/password/verificar-codigo`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: forgotEmail, codigo: code })
                    });
                    const data = await res.json();

                    if (res.ok && data.success) {
                        if (step2) step2.style.display = "none";
                        if (step3) { step3.style.display = "flex"; hideStepMsg(step3); }
                    } else {
                        showStepMsg(step2, data.message || "Código incorrecto o expirado.");
                    }
                } catch (_) {
                    showStepMsg(step2, "Error de red. Inténtalo de nuevo.");
                } finally {
                    btnVerify.disabled = false;
                    const t = btnVerify.querySelector(".gysl-btn-ui-login__text");
                    if (t) t.textContent = origText;
                }
            });
        }

        if (btnResetPwd) {
            btnResetPwd.addEventListener('click', async () => {
                const p1 = (document.getElementById("forgot-new-pwd")?.value || "");
                const p2 = (document.getElementById("forgot-confirm-pwd")?.value || "");

                if (!p1 || !p2) {
                    showStepMsg(step3, "Rellena ambos campos de contraseña.");
                    return;
                }
                if (p1 !== p2) {
                    showStepMsg(step3, "Las contraseñas no coinciden.");
                    return;
                }
                if (p1.length < 8) {
                    showStepMsg(step3, "La contraseña debe tener al menos 8 caracteres.");
                    return;
                }

                hideStepMsg(step3);
                setLoading(btnResetPwd, true);
                const origText = btnResetPwd.querySelector(".gysl-btn-ui-login__text")?.textContent || "";

                try {
                    const res = await fetch(`${BASE}/auth/password/reset`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: forgotEmail, nueva_pwd: p1 })
                    });
                    const data = await res.json();

                    if (res.ok && data.success) {
                        showStepMsg(step3, "✅ ¡Contraseña cambiada! Iniciando sesión…", false);

                        document.getElementById("forgot-new-pwd").value = "";
                        document.getElementById("forgot-confirm-pwd").value = "";

                        setTimeout(() => {
                            forgotEmail = "";
                            if (forgotFlow) forgotFlow.style.display = "none";
                            if (loginForm) loginForm.style.display = "flex";
                            if (step1) step1.style.display = "flex";
                            if (step2) step2.style.display = "none";
                            if (step3) step3.style.display = "none";
                        }, 2000);
                    } else {
                        showStepMsg(step3, data.message || "No se pudo actualizar la contraseña.");
                    }
                } catch (_) {
                    showStepMsg(step3, "Error de red. Inténtalo de nuevo.");
                } finally {
                    btnResetPwd.disabled = false;
                    const t = btnResetPwd.querySelector(".gysl-btn-ui-login__text");
                    if (t) t.textContent = origText;
                }
            });
        }
    };
})();
