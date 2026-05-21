(function () {
    "use strict";
    console.log("🚀 register.js cargado");

    window.initRegisterForm = function () {
        const registerForm = document.getElementById("register-form");
        const registerVerifyFlow = document.getElementById("register-verify-flow");
        const wrapper = document.getElementById("login-wrapper");
        const errorMsg = document.getElementById("register-error-msg");

        const btnVerify = document.getElementById("btn-submit-register-verify");
        const inputVerifyCode = document.getElementById("register-verify-code");
        const errorVerify = document.getElementById("register-verify-error");
        const btnResend = document.getElementById("btn-resend-register-code");


        let countdownEl = document.getElementById("register-verify-countdown");
        if (!countdownEl) {
            countdownEl = document.createElement("p");
            countdownEl.id = "register-verify-countdown";
            countdownEl.style.cssText = "font-size: 12px; color: #888; margin-top: 8px;";

            if (btnResend && btnResend.parentNode) {
                btnResend.parentNode.insertBefore(countdownEl, btnResend.nextSibling);
            }
        }

        let pendingEmail = "";
        let verifyTimer = null;       
        let verifyTimeout = null;     
        const VERIFY_TIMEOUT_MS = 2 * 60 * 1000; 

        window.cancelarRegistroPendiente = async function () {
            if (!pendingEmail) return; 
            const emailACancelar = pendingEmail;
            clearVerifyTimer();
            resetToRegisterForm();

            try {
                const url = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/email/eliminar-no-verificado';
                await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: emailACancelar })
                });
                console.log("🗑️ Registro cancelado, usuario eliminado:", emailACancelar);
            } catch (err) {
                console.warn("No se pudo notificar al backend al cancelar registro:", err);
            }
        };

        function clearVerifyTimer() {
            if (verifyTimer) { clearInterval(verifyTimer); verifyTimer = null; }
            if (verifyTimeout) { clearTimeout(verifyTimeout); verifyTimeout = null; }
            countdownEl.textContent = "";
        }

        function startVerifyTimer() {
            clearVerifyTimer();

            const deadline = Date.now() + VERIFY_TIMEOUT_MS;

            verifyTimer = setInterval(function () {
                const remaining = deadline - Date.now();
                if (remaining <= 0) {
                    clearInterval(verifyTimer);
                    verifyTimer = null;
                    countdownEl.textContent = "";
                    return;
                }
                const mins = Math.floor(remaining / 60000);
                const secs = Math.floor((remaining % 60000) / 1000);
                countdownEl.textContent =
                    `⏱ El código expira en ${mins}:${secs.toString().padStart(2, "0")}`;
            }, 1000);

            verifyTimeout = setTimeout(async function () {
                clearInterval(verifyTimer);
                verifyTimer = null;
                countdownEl.textContent = "";

                if (pendingEmail) {
                    try {
                        const url = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/email/eliminar-no-verificado';
                        await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email: pendingEmail })
                        });
                        console.log("🗑️ Usuario no verificado eliminado:", pendingEmail);
                    } catch (err) {
                        console.warn("No se pudo notificar al backend para eliminar el usuario:", err);
                    }
                }

                resetToRegisterForm();
                errorVerify.textContent = "El tiempo de verificación expiró. Por favor, regístrate de nuevo.";
                errorVerify.style.display = "block";
            }, VERIFY_TIMEOUT_MS);
        }

        function resetToRegisterForm() {
            pendingEmail = "";
            inputVerifyCode.value = "";
            errorVerify.style.display = "none";
            registerVerifyFlow.style.display = "none";
            registerForm.style.display = "";   
            registerForm.reset();
        }

        if (registerForm && !registerForm.dataset.wired) {
            registerForm.dataset.wired = "true";

            registerForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                errorMsg.style.display = 'none';

                const formData = new FormData(registerForm);
                const payload = Object.fromEntries(formData.entries());

                try {
                    const exactUrl = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/register';
                    const res = await fetch(exactUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();

                    if (res.ok && data.success) {

                        pendingEmail = payload.email;

                        registerForm.style.transition = "none";
                        registerForm.style.display = "none";
                        registerVerifyFlow.style.transition = "none";
                        registerVerifyFlow.style.display = "flex";


                        startVerifyTimer();

                    } else {

                        errorMsg.textContent = "Error: " + (data.message || "Hubo un error al crear la cuenta");
                        errorMsg.style.display = "block";
                    }
                } catch (error) {
                    console.error("❌ Error registrando:", error);
                    errorMsg.textContent = "Falló la conexión. Intenta de nuevo.";
                    errorMsg.style.display = "block";
                }
            });

            if (btnVerify) {
                btnVerify.addEventListener("click", async function () {
                    const code = inputVerifyCode.value.trim();
                    errorVerify.style.display = "none";
                    if (!code) {
                        errorVerify.textContent = "Por favor, introduce el código";
                        errorVerify.style.display = "block";
                        return;
                    }

                    try {
                        const url = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/email/verificar-codigo';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email: pendingEmail, codigo: code })
                        });
                        const data = await res.json();

                        if (res.ok && data.success) {

                            clearVerifyTimer();

                            errorVerify.textContent = "✅ ¡Registro exitoso! Ya puedes iniciar sesión.";
                            errorVerify.style.color = "#22c55e";
                            errorVerify.style.display = "block";

                            setTimeout(() => {
                                resetToRegisterForm();
                                if (wrapper) wrapper.classList.remove("right-panel-active");
                            }, 2000);
                        } else {
                            errorVerify.textContent = "Código incorrecto o expirado.";
                            errorVerify.style.display = "block";
                        }
                    } catch (err) {
                        errorVerify.textContent = "Error de red. Intenta de nuevo.";
                        errorVerify.style.display = "block";
                    }
                });
            }

            if (btnResend) {
                btnResend.addEventListener("click", async function () {
                    btnResend.disabled = true;
                    btnResend.textContent = "Enviando...";
                    try {
                        const url = '/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular/auth/email/solicitar-codigo';
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email: pendingEmail })
                        });
                        if (res.ok) {
                            startVerifyTimer();
                            errorVerify.textContent = "✅ Nuevo código enviado. Tienes 10 minutos para verificar.";
                            errorVerify.style.color = "green";
                            errorVerify.style.display = "block";
                            setTimeout(() => {
                                errorVerify.style.display = "none";
                                errorVerify.style.color = "red"; 
                            }, 4000);
                        } else {
                            showToast("Hubo un error al reenviar el código.", "error");
                        }
                    } catch (err) {
                        showToast("Error de red.", "error");
                    } finally {
                        btnResend.disabled = false;
                        btnResend.textContent = "Reenviar código";
                    }
                });
            }
        }
    };
})();
