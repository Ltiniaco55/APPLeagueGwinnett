(function () {
    "use strict";

    function crearToastContainer() {
        let container = document.getElementById("gysl-toast-container-global");

        if (!container) {
            container = document.createElement("div");
            container.id = "gysl-toast-container-global";
            container.className = "gysl-toast-container";
            document.body.appendChild(container);
        }

        return container;
    }

    function show(message, type = "success", duration = 3500) {
        const container = crearToastContainer();

        const toast = document.createElement("div");
        toast.className = `gysl-toast-item gysl-toast-item--${type}`;
        toast.textContent = message;

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add("is-visible");
        });

        setTimeout(() => {
            toast.classList.remove("is-visible");

            setTimeout(() => {
                toast.remove();
            }, 250);
        }, duration);
    }

    function crearConfirmOverlay() {
        let overlay = document.getElementById("gysl-toast-confirm-overlay");

        if (!overlay) {
            overlay = document.createElement("div");
            overlay.id = "gysl-toast-confirm-overlay";
            overlay.className = "gysl-toast-confirm-overlay";

            overlay.innerHTML = `
                <div class="gysl-toast-confirm-card">
                    <h3 id="gysl-toast-confirm-title">¿Confirmar acción?</h3>
                    <p id="gysl-toast-confirm-message">Esta acción necesita confirmación.</p>

                    <div class="gysl-toast-confirm-actions">
                        <button type="button"
                            class="gysl-admin-btn gysl-admin-btn--secondary"
                            id="gysl-toast-confirm-cancel">
                            Cancelar
                        </button>

                        <button type="button"
                            class="gysl-admin-btn gysl-admin-btn--primary"
                            id="gysl-toast-confirm-ok">
                            Confirmar
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
        }

        return overlay;
    }

    function confirm(title, message, options = {}) {
        return new Promise((resolve) => {
            const overlay = crearConfirmOverlay();

            const titleEl = document.getElementById("gysl-toast-confirm-title");
            const messageEl = document.getElementById("gysl-toast-confirm-message");
            const btnCancel = document.getElementById("gysl-toast-confirm-cancel");
            const btnOk = document.getElementById("gysl-toast-confirm-ok");

            titleEl.textContent = title || "¿Confirmar acción?";
            messageEl.textContent = message || "Esta acción necesita confirmación.";

            btnCancel.textContent = options.cancelText || "Cancelar";
            btnOk.textContent = options.okText || "Confirmar";

            overlay.classList.add("is-open");

            function cerrar(resultado) {
                overlay.classList.remove("is-open");

                btnCancel.removeEventListener("click", cancelar);
                btnOk.removeEventListener("click", aceptar);
                overlay.removeEventListener("click", clickFuera);

                resolve(resultado);
            }

            function cancelar() {
                cerrar(false);
            }

            function aceptar() {
                cerrar(true);
            }

            function clickFuera(e) {
                if (e.target === overlay) {
                    cerrar(false);
                }
            }

            btnCancel.addEventListener("click", cancelar);
            btnOk.addEventListener("click", aceptar);
            overlay.addEventListener("click", clickFuera);
        });
    }

    window.GYSLToast = {
        show,
        confirm
    };

    window.showToast = function (message, type = "success", duration = 3500) {
        return window.GYSLToast.show(message, type, duration);
    };

    window.showConfirm = function (
        title,
        message,
        type = "warning",
        confirmText = "Confirmar",
        cancelText = "Cancelar"
    ) {
        return window.GYSLToast.confirm(title, message, {
            type,
            okText: confirmText,
            cancelText
        });
    };
})();