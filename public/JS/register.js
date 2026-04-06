(function () {
    "use strict";
    console.log("🚀 register.js cargado");

    window.initRegisterForm = function () {
        const registerForm = document.getElementById("register-form");
        const registerVerifyFlow = document.getElementById("register-verify-flow");
        const wrapper = document.getElementById("login-wrapper");
        const errorMsg = document.getElementById("register-error-msg");

        // Verificación
        const btnVerify = document.getElementById("btn-submit-register-verify");
        const inputVerifyCode = document.getElementById("register-verify-code");
        const errorVerify = document.getElementById("register-verify-error");
        const btnResend = document.getElementById("btn-resend-register-code");

        // Countdown display (lo inyectamos dinámicamente)
        let countdownEl = document.getElementById("register-verify-countdown");
        if (!countdownEl) {
            countdownEl = document.createElement("p");
            countdownEl.id = "register-verify-countdown";
            countdownEl.style.cssText = "font-size: 12px; color: #888; margin-top: 8px;";
            // Insertamos debajo del botón de reenviar
            if (btnResend && btnResend.parentNode) {
                btnResend.parentNode.insertBefore(countdownEl, btnResend.nextSibling);
            }
        }

        let pendingEmail = "";
        let verifyTimer = null;       // setInterval del countdown visual
        let verifyTimeout = null;     // setTimeout de los 2 minutos
        const VERIFY_TIMEOUT_MS = 2 * 60 * 1000; // 2 minutos en ms

        // ─── Exponer función de cancelación para uso externo (ej: cerrar modal) ─
        window.cancelarRegistroPendiente = async function () {
            if (!pendingEmail) return; // Nada pendiente, no hacer nada
            const emailACancelar = pendingEmail;
            clearVerifyTimer();
            resetToRegisterForm();
            // Borrar el usuario no verificado del backend
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

        // ─── Helpers del timer ────────────────────────────────────────────────────

        function clearVerifyTimer() {
            if (verifyTimer) { clearInterval(verifyTimer); verifyTimer = null; }
            if (verifyTimeout) { clearTimeout(verifyTimeout); verifyTimeout = null; }
            countdownEl.textContent = "";
        }

        /**
         * Inicia (o reinicia) el temporizador de 10 minutos.
         * Al expirar, elimina el usuario no verificado y vuelve al formulario.
         */
        function startVerifyTimer() {
            clearVerifyTimer();

            const deadline = Date.now() + VERIFY_TIMEOUT_MS;

            // Countdown visual cada segundo
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

            // Expiración real
            verifyTimeout = setTimeout(async function () {
                clearInterval(verifyTimer);
                verifyTimer = null;
                countdownEl.textContent = "";

                // Llamada al backend para borrar el usuario no verificado
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

                // Volver al formulario de registro limpio
                resetToRegisterForm();
                errorVerify.textContent = "El tiempo de verificación expiró. Por favor, regístrate de nuevo.";
                errorVerify.style.display = "block";
            }, VERIFY_TIMEOUT_MS);
        }

        /** Restablece la vista al formulario de registro */
        function resetToRegisterForm() {
            pendingEmail = "";
            inputVerifyCode.value = "";
            errorVerify.style.display = "none";
            registerVerifyFlow.style.display = "none";
            registerForm.style.display = "";   // quita el inline style para que CSS mande
            registerForm.reset();
        }

        // ─── Eventos ──────────────────────────────────────────────────────────────

        if (registerForm && !registerForm.dataset.wired) {
            registerForm.dataset.wired = "true";

            // 1. Submit del registro
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
                        // Guardamos email para el siguiente paso
                        pendingEmail = payload.email;

                        // Ocultamos Form INSTANTÁNEAMENTE y mostramos Verify
                        registerForm.style.transition = "none";
                        registerForm.style.display = "none";
                        registerVerifyFlow.style.transition = "none";
                        registerVerifyFlow.style.display = "flex";

                        // ⏱ Iniciamos el temporizador de 2 minutos
                        startVerifyTimer();

                    } else {
                        // Mostramos el mensaje de error especificado por el backend (password, duplicado, etc)
                        errorMsg.textContent = "Error: " + (data.message || "Hubo un error al crear la cuenta");
                        errorMsg.style.display = "block";
                    }
                } catch (error) {
                    console.error("❌ Error registrando:", error);
                    errorMsg.textContent = "Falló la conexión. Intenta de nuevo.";
                    errorMsg.style.display = "block";
                }
            });

            // 2. Submit de la verificación
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
                            // Verificación exitosa → cancelamos el timer y limpiamos
                            clearVerifyTimer();

                            // Mostrar mensaje de éxito en la misma card
                            errorVerify.textContent = "✅ ¡Registro exitoso! Ya puedes iniciar sesión.";
                            errorVerify.style.color = "#22c55e";
                            errorVerify.style.display = "block";

                            // Después de 2s volvemos al login
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

            // 3. Reenviar Código → reinicia el temporizador
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
                            // ⏱ Reiniciamos el temporizador al reenviar
                            startVerifyTimer();
                            errorVerify.textContent = "✅ Nuevo código enviado. Tienes 10 minutos para verificar.";
                            errorVerify.style.color = "green";
                            errorVerify.style.display = "block";
                            setTimeout(() => {
                                errorVerify.style.display = "none";
                                errorVerify.style.color = "red"; // restaurar para futuros errores
                            }, 4000);
                        } else {
                            alert("Hubo un error al reenviar el código.");
                        }
                    } catch (err) {
                        alert("Error de red.");
                    } finally {
                        btnResend.disabled = false;
                        btnResend.textContent = "Reenviar código";
                    }
                });
            }
        }
    };
})();
