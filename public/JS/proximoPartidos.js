(function () {
    "use strict";

    const API = "/2DO_CURSO_DAW/Desarrollo_web_en_entornos_servidor/Proyecto_intermodular";

    const track = document.getElementById("gysl-carousel-track");
    const dots = document.getElementById("gysl-carousel-dots");

    const btnTodos = document.getElementById("btn-todos-partidos");
    const selectLiga = document.getElementById("filtro-liga-partidos");
    const inputEquipo = document.getElementById("buscar-equipo-partidos");
    const btnFavoritos = document.getElementById("btn-favoritos-partidos");

    let ligasData = [];
    let partidosData = [];
    let partidosFiltrados = [];
    let paginaActual = 0;

    function texto(valor, fallback = "—") {
        return String(valor ?? "").trim() || fallback;
    }

    function normalizarFecha(fecha) {
        if (!fecha) return null;

        const d = new Date(fecha);

        return Number.isNaN(d.getTime()) ? null : d;
    }

    function formatearFecha(fecha) {
        const d = normalizarFecha(fecha);

        if (!d) return "SIN FECHA";

        return d.toLocaleDateString("es-ES", {
            weekday: "short",
            day: "2-digit",
            month: "2-digit",
            year: "numeric"
        }).toUpperCase();
    }

    function formatearHora(fecha) {
        const d = normalizarFecha(fecha);

        if (!d) return "—";

        return d.toLocaleTimeString("es-ES", {
            hour: "2-digit",
            minute: "2-digit"
        });
    }

    function abreviar(nombre) {
        return texto(nombre, "—")
            .split(" ")
            .filter(Boolean)
            .slice(0, 2)
            .map(palabra => palabra.charAt(0))
            .join("")
            .toUpperCase()
            .slice(0, 3);
    }

    function obtenerFormatoLiga(idLiga) {
        const liga = ligasData.find(l => String(l.id_liga ?? l.id) === String(idLiga));

        return (liga?.formato_liga ?? "JORNADAS").toUpperCase();
    }

    function obtenerUrlLiga(partido) {
        const formato = obtenerFormatoLiga(partido.id_liga);

        if (formato === "JORNADAS") {
            return `clasificacion.html?liga=${partido.id_liga}&formato=${formato}`;
        }

        return `resultados.html?liga=${partido.id_liga}&formato=${formato}`;
    }

    function obtenerPartidosFavoritos() {
        /*
            De momento usamos localStorage hasta que exista backend real.
            Estructura esperada:
            localStorage.setItem("equipos_favoritos", JSON.stringify([1, 5, 9]));
        */
        let favoritos = [];

        try {
            favoritos = JSON.parse(localStorage.getItem("equipos_favoritos") || "[]");
        } catch (_) {
            favoritos = [];
        }

        const idsFavoritos = favoritos.map(Number);

        return partidosData.filter(partido =>
            idsFavoritos.includes(Number(partido.id_equipo_local)) ||
            idsFavoritos.includes(Number(partido.id_equipo_visitante))
        );
    }

    function cardPartido(partido) {
        const local = texto(partido.club_local, "Local");
        const visitante = texto(partido.club_visitante, "Visitante");
        const categoria = texto(partido.categoria_liga ?? partido.categoria_local, "Competición");
        const tipoRonda = texto(partido.tipo_ronda, "Partido");
        const lugar = texto(partido.lugar, "GYSL");
        const urlLiga = obtenerUrlLiga(partido);

        return `
            <article class="gysl-match">
                <header class="gysl-match__head">
                    <span class="gysl-match__date">${formatearFecha(partido.fecha)}</span>
                    <span class="gysl-match__time">${formatearHora(partido.fecha)}</span>
                </header>

                <div class="gysl-match__body">
                    <div class="gysl-match__teams">
                        <div class="gysl-team">
                            <span class="gysl-team__abbr">${abreviar(local)}</span>
                            <span class="gysl-team__name">${local}</span>
                        </div>

                        <span class="gysl-match__vs">VS</span>

                        <div class="gysl-team">
                            <span class="gysl-team__abbr">${abreviar(visitante)}</span>
                            <span class="gysl-team__name">${visitante}</span>
                        </div>
                    </div>

                    <div class="gysl-match__meta">
                        <span class="gysl-pill">${categoria}</span>
                        <span class="gysl-pill is-muted">${tipoRonda}</span>
                    </div>

                    <div class="gysl-match__actions">
                        <button class="gysl-match__action" onclick="window.location.href='${urlLiga}'">Liga</button>
                        <button class="gysl-match__action" onclick="window.location.href='resultados.html?liga=${partido.id_liga}&formato=${obtenerFormatoLiga(partido.id_liga)}'">Resultado</button>
                        <button class="gysl-match__action" onclick="window.location.href='equipos.html?liga=${partido.id_liga}&formato=${obtenerFormatoLiga(partido.id_liga)}'">Equipos</button>
                        <button class="gysl-match__action" onclick="window.location.href='calendario.html?liga=${partido.id_liga}&formato=${obtenerFormatoLiga(partido.id_liga)}'">Calendario</button>
                    </div>
                </div>

                <footer class="gysl-match__foot">
                    <span class="gysl-channel">${lugar}</span>
                </footer>
            </article>
        `;
    }

    function dividirEnPaginas(partidos, porPagina = 4) {
        const paginas = [];

        for (let i = 0; i < partidos.length; i += porPagina) {
            paginas.push(partidos.slice(i, i + porPagina));
        }

        return paginas;
    }

    function renderDots(totalPaginas) {
        if (!dots) return;

        dots.innerHTML = "";

        if (totalPaginas <= 1) return;

        for (let i = 0; i < totalPaginas; i++) {
            const dot = document.createElement("button");
            dot.className = "gysl-dot" + (i === paginaActual ? " is-active" : "");
            dot.type = "button";
            dot.addEventListener("click", () => {
                paginaActual = i;
                moverCarrusel();
            });

            dots.appendChild(dot);
        }
    }

    function moverCarrusel() {
        if (!track) return;

        track.style.transform = `translateX(-${paginaActual * 100}%)`;

        document.querySelectorAll(".gysl-dot").forEach((dot, index) => {
            dot.classList.toggle("is-active", index === paginaActual);
        });
    }

    function renderPartidos(partidos) {
        if (!track) return;

        paginaActual = 0;

        if (!partidos.length) {
            track.innerHTML = `
                <div class="gysl-carousel__empty">
                    No hay próximos partidos para este filtro.
                </div>
            `;

            if (dots) dots.innerHTML = "";
            return;
        }

        const paginas = dividirEnPaginas(partidos.slice(0, 16), 4);

        track.innerHTML = paginas.map((pagina, index) => `
            <div class="gysl-carousel__page" data-page="${index}">
                ${pagina.map(cardPartido).join("")}
            </div>
        `).join("");

        renderDots(paginas.length);
        moverCarrusel();
    }

    function aplicarFiltroTodos() {
        btnTodos?.classList.add("is-active");
        btnFavoritos?.classList.remove("is-active");

        if (selectLiga) selectLiga.value = "";
        if (inputEquipo) inputEquipo.value = "";

        partidosFiltrados = [...partidosData];

        renderPartidos(partidosFiltrados);
    }

    function aplicarFiltroLiga() {
        btnTodos?.classList.remove("is-active");
        btnFavoritos?.classList.remove("is-active");

        if (inputEquipo) inputEquipo.value = "";

        const idLiga = selectLiga.value;

        partidosFiltrados = idLiga
            ? partidosData.filter(p => String(p.id_liga) === String(idLiga))
            : [...partidosData];

        renderPartidos(partidosFiltrados);
    }

    function aplicarFiltroEquipo() {
        btnTodos?.classList.remove("is-active");
        btnFavoritos?.classList.remove("is-active");

        if (selectLiga) selectLiga.value = "";

        const busqueda = inputEquipo.value.trim().toLowerCase();

        partidosFiltrados = partidosData.filter(p => {
            const local = texto(p.club_local, "").toLowerCase();
            const visitante = texto(p.club_visitante, "").toLowerCase();

            return local.includes(busqueda) || visitante.includes(busqueda);
        });

        renderPartidos(partidosFiltrados);
    }

    function aplicarFiltroFavoritos() {
        btnTodos?.classList.remove("is-active");
        btnFavoritos?.classList.add("is-active");

        if (selectLiga) selectLiga.value = "";
        if (inputEquipo) inputEquipo.value = "";

        partidosFiltrados = obtenerPartidosFavoritos();

        renderPartidos(partidosFiltrados);
    }

    function cargarSelectLigas() {
        if (!selectLiga) return;

        const ligasEnCurso = ligasData.filter(liga => liga.estado_liga === "EN_CURSO");

        selectLiga.innerHTML = `<option value="">Ligas en curso</option>`;

        ligasEnCurso.forEach(liga => {
            const id = liga.id_liga ?? liga.id;
            const nombre = texto(liga.nombre_liga ?? liga.nom ?? liga.nombre, "Liga sin nombre");
            const categoria = texto(liga.categoria ?? liga.categ, "");

            selectLiga.innerHTML += `
                <option value="${id}">
                    ${categoria ? `${nombre} · ${categoria}` : nombre}
                </option>
            `;
        });
    }

    async function cargarDatos() {
        try {
            const [resLigas, resPartidos] = await Promise.all([
                fetch(API + "/ligas", { credentials: "include" }),
                fetch(API + "/partidos", { credentials: "include" })
            ]);

            const dataLigas = await resLigas.json();
            const dataPartidos = await resPartidos.json();

            ligasData = Array.isArray(dataLigas) ? dataLigas : (dataLigas.data || []);
            const partidos = Array.isArray(dataPartidos) ? dataPartidos : (dataPartidos.data || []);

            const ahora = new Date();

            partidosData = partidos
                .filter(p => {
                    const fecha = normalizarFecha(p.fecha);
                    const estado = String(p.estado ?? "").toLowerCase();

                    return fecha && fecha >= ahora && estado !== "jugado" && estado !== "cancelado";
                })
                .sort((a, b) => normalizarFecha(a.fecha) - normalizarFecha(b.fecha));

            cargarSelectLigas();
            aplicarFiltroTodos();
        } catch (error) {
            console.error("[Próximos partidos]", error);

            if (track) {
                track.innerHTML = `
                    <div class="gysl-carousel__empty">
                        No se pudieron cargar los próximos partidos.
                    </div>
                `;
            }

            if (dots) dots.innerHTML = "";
        }
    }

    btnTodos?.addEventListener("click", aplicarFiltroTodos);
    selectLiga?.addEventListener("change", aplicarFiltroLiga);
    inputEquipo?.addEventListener("input", aplicarFiltroEquipo);
    btnFavoritos?.addEventListener("click", aplicarFiltroFavoritos);

    cargarDatos();
})();