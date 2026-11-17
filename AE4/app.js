const PRECIO_ASIENTO = 4800;
let descuentoCupon = 0;
let cuponAplicado = '';

document.addEventListener('DOMContentLoaded', () => {

    const menuToggle = document.querySelector('.menu-toggle');
    const menuPrincipal = document.querySelector('.nav-links');
    const navegacion = menuToggle ? menuToggle.closest('.topnav') : null;
    if (menuToggle && menuPrincipal && navegacion) {
        const cerrarMenu = () => {
            navegacion.classList.remove('menu-abierto');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Abrir menú');
        };

        menuToggle.addEventListener('click', () => {
            const abierto = navegacion.classList.toggle('menu-abierto');
            menuToggle.setAttribute('aria-expanded', String(abierto));
            menuToggle.setAttribute('aria-label', abierto ? 'Cerrar menú' : 'Abrir menú');
        });

        menuPrincipal.addEventListener('click', (evento) => {
            if (evento.target.closest('a')) cerrarMenu();
        });

        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape') cerrarMenu();
        });
    }

    function toggleForms() {
        const login = document.querySelector('[data-role="login-panel"]');
        const register = document.querySelector('[data-role="register-panel"]');
        if (!login || !register) return;

        const loginOculto = login.style.display === 'none';
        login.style.display = loginOculto ? 'block' : 'none';
        register.style.display = loginOculto ? 'none' : 'block';
    }

    document.querySelectorAll('[data-action="toggle-register"], [data-action="toggle-login"]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            toggleForms();
        });
    });

    // ========== AUTENTICACIÓN Y SESIÓN (BCRYPT + session_start) ==========
    // Estado de sesión del usuario: null = invitado, objeto = { cliente: {...}, compras: [...] }
    let sesion = null;

    const formLogin = document.querySelector('[data-role="form-login"]');
    const formRegistro = document.querySelector('[data-role="form-registro"]');
    const msgLogin = document.querySelector('[data-role="msg-login"]');
    const msgRegistro = document.querySelector('[data-role="msg-registro"]');
    const listaCompras = document.querySelector('#lista-compras');

    // Muestra un mensaje SIEMPRE con textContent (previene XSS)
    function mostrarMensaje(elemento, texto, tipo) {
        if (!elemento) return;
        elemento.textContent = texto;
        elemento.className = 'msg ' + tipo;
    }

    // Helper para llamar a la API devolviendo siempre { ok, status, data }
    async function llamarAPI(url, opciones = {}) {
        const res = await fetch(url, { credentials: 'same-origin', ...opciones });
        let data = {};
        try { data = await res.json(); } catch (e) { /* respuesta sin JSON */ }
        return { ok: res.ok, status: res.status, data };
    }

    // Consulta al endpoint privado: si responde 200 hay sesión, si responde 401 es invitado
    async function comprobarSesion() {
        try {
            const { ok, data } = await llamarAPI('api/mis_compras.php');
            sesion = (ok && data.cliente) ? data : null;
        } catch (error) {
            sesion = null;
        }
        actualizarInterfazSesion();
        return sesion;
    }

    // Alterna el DOM según la sesión (botones, enlaces y nombre de usuario)
    function actualizarInterfazSesion() {
        // Mostrar/ocultar elementos según si hay sesión
        document.querySelectorAll('[data-solo-sesion]').forEach(el => {
            el.classList.toggle('oculto', !sesion);
        });
        document.querySelectorAll('[data-solo-invitado]').forEach(el => {
            el.classList.toggle('oculto', !!sesion);
        });

        // Poner el nombre del usuario
        document.querySelectorAll('[data-usuario-nombre]').forEach(el => {
            el.textContent = sesion ? sesion.cliente.nombre : '';
        });

        // Detectar si es admin
        const esAdmin = sesion && sesion.cliente && sesion.cliente.rol === 'administrador';

        // Solo los admin ven "Métricas"
        document.querySelectorAll('[data-solo-admin]').forEach(el => {
            el.classList.toggle('oculto', !esAdmin);
        });

        // Panel de métricas: quien no es administrador se redirige (el control real está en api/metricas.php)
        if (window.location.pathname.includes('metricas.html') && !esAdmin) {
            window.location.assign(sesion ? 'index.html' : 'login.html');
            return;
        }

        // Si estamos en la página de login y ya hay sesión → redirigir
        if (formLogin && sesion) {
            if (esAdmin) {
                window.location.assign('metricas.html');
            } else {
                window.location.assign('index.html');
            }
            return;
        }

        precargarDatosCompra();
        if (listaCompras) renderizarMisCompras();
    }

    // En comprar.html, si hay sesión se completan nombre y email (sin pisar lo que ya escribió)
    function precargarDatosCompra() {
        if (!sesion) return;
        const nombreEl = document.querySelector('[data-role="campo-nombre"]');
        const emailEl = document.querySelector('[data-role="campo-email"]');
        if (nombreEl && !nombreEl.value) nombreEl.value = sesion.cliente.nombre || '';
        if (emailEl && !emailEl.value) emailEl.value = sesion.cliente.email || '';
    }

    // Crea un elemento con texto seguro (textContent)
    function crearEl(tag, texto, clase) {
        const el = document.createElement(tag);
        if (texto !== undefined && texto !== null) el.textContent = texto;
        if (clase) el.className = clase;
        return el;
    }

    // ========== CARTELERA (listado_tabla / listado_box / producto, desde api/peliculas.php) ==========
    // Trae las peliculas una sola vez por carga de pagina y las guarda en cache.
    let _peliculasCache = null;
    async function cargarPeliculas() {
        if (_peliculasCache) return _peliculasCache;
        try {
            const res = await fetch('api/peliculas.php');
            if (!res.ok) throw new Error('No se pudo cargar la cartelera');
            _peliculasCache = await res.json();
        } catch (error) {
            console.error('Error al cargar peliculas:', error);
            _peliculasCache = [];
        }
        return _peliculasCache;
    }

    // Trae UNA pelicula por slug (no usa la cache: pisa descripcion/imagen mas frescas si hiciera falta)
    async function cargarPeliculaPorSlug(slug) {
        try {
            const res = await fetch('api/peliculas.php?slug=' + encodeURIComponent(slug));
            if (!res.ok) return null;
            return await res.json();
        } catch (error) {
            console.error('Error al cargar la pelicula:', error);
            return null;
        }
    }

    const DIAS_CARTELERA = 14;

    // ----- listado_tabla.html (con selector de día, igual que la vista de tarjetas) -----
    const tablaPeliculas = document.querySelector('[data-role="lista-peliculas-tabla"]');
    if (tablaPeliculas) {
        const listaDiasTabla = document.querySelector('[data-role="dias-lista"]');
        const resumenDiaTabla = document.querySelector('[data-role="dias-resumen"]');

        function filaPelicula(p, indice, iso) {
            const fila = document.createElement('tr');

            fila.appendChild(crearEl('td', '#' + String(indice + 1).padStart(3, '0'), 'codigo'));
            fila.appendChild(crearEl('td', p.titulo));
            fila.appendChild(crearEl('td', p.clasificacion || '—'));
            fila.appendChild(crearEl('td', p.generos || '—'));
            fila.appendChild(crearEl('td', '$' + PRECIO_ASIENTO.toLocaleString('es-AR')));

            const tdAccion = document.createElement('td');
            const link = crearEl('a', 'Ver ficha', 'link-detalle');
            link.href = 'producto.html?peli=' + encodeURIComponent(p.slug) + (iso ? '&fecha=' + iso : '');
            tdAccion.appendChild(link);
            fila.appendChild(tdAccion);
            return fila;
        }

        function mensajeTabla(texto) {
            const fila = document.createElement('tr');
            const td = crearEl('td', texto);
            td.colSpan = 6;
            fila.appendChild(td);
            tablaPeliculas.appendChild(fila);
        }

        (async () => {
            const [peliculas, funciones] = await Promise.all([cargarPeliculas(), cargarFunciones()]);
            tablaPeliculas.textContent = '';

            if (!peliculas || peliculas.length === 0) {
                mensajeTabla('No hay películas cargadas.');
                return;
            }

            const renderTodas = () => peliculas.forEach((p, i) => tablaPeliculas.appendChild(filaPelicula(p, i)));

            // Sin selector en la página: se muestra toda la cartelera
            if (!listaDiasTabla) { renderTodas(); return; }

            const porFecha = {};
            funciones.forEach(f => { (porFecha[f.fecha] = porFecha[f.fecha] || []).push(f); });

            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const dias = [];
            for (let i = 0; i < DIAS_CARTELERA; i++) {
                const d = new Date(hoy);
                d.setDate(hoy.getDate() + i);
                dias.push(d);
            }

            const botones = {};

            function mostrarDia(iso) {
                Object.keys(botones).forEach(k => botones[k].classList.toggle('activo', k === iso));
                tablaPeliculas.textContent = '';

                const delDia = porFecha[iso] || [];
                if (delDia.length === 0) {
                    if (funciones.length === 0) {
                        if (resumenDiaTabla) resumenDiaTabla.textContent = 'Todavía no hay funciones programadas. Estas son las películas en cartelera:';
                        renderTodas();
                    } else {
                        if (resumenDiaTabla) resumenDiaTabla.textContent = '';
                        mensajeTabla('No hay funciones programadas para este día.');
                    }
                    return;
                }

                // Películas con funciones ese día (el código #00N respeta el orden de la cartelera completa)
                const grupos = new Map();
                delDia.forEach(f => {
                    if (!grupos.has(f.id_pelicula)) grupos.set(f.id_pelicula, []);
                    grupos.get(f.id_pelicula).push(f);
                });

                if (resumenDiaTabla) {
                    resumenDiaTabla.textContent = grupos.size + (grupos.size === 1 ? ' película' : ' películas') +
                        ' · ' + delDia.length + (delDia.length === 1 ? ' función' : ' funciones');
                }

                let posicion = 0;
                grupos.forEach((fs, idPelicula) => {
                    const indice = peliculas.findIndex(x => Number(x.id_pelicula) === Number(idPelicula));
                    const p = indice >= 0 ? peliculas[indice] :
                        { titulo: fs[0].titulo, slug: fs[0].slug, clasificacion: fs[0].clasificacion };
                    tablaPeliculas.appendChild(filaPelicula(p, indice >= 0 ? indice : posicion, iso));
                    posicion++;
                });
            }

            dias.forEach(d => {
                const iso = fechaLocalISO(d);
                const b = crearEl('button', null, 'dias-card' + (porFecha[iso] ? '' : ' sin-funciones'));
                b.type = 'button';
                b.appendChild(crearEl('span', nombreDia(d), 'dias-nombre'));
                b.appendChild(crearEl('span', String(d.getDate()), 'dias-numero'));
                b.appendChild(crearEl('span', nombreMes(d), 'dias-mes'));
                b.addEventListener('click', () => mostrarDia(iso));
                botones[iso] = b;
                listaDiasTabla.appendChild(b);
            });

            document.querySelectorAll('[data-dias-nav]').forEach(btn => {
                btn.addEventListener('click', () => {
                    listaDiasTabla.scrollBy({ left: Number(btn.dataset.diasNav) * listaDiasTabla.clientWidth, behavior: 'smooth' });
                });
            });

            const pedido = new URLSearchParams(window.location.search).get('fecha');
            const primero = (pedido && botones[pedido]) ? pedido
                : (dias.map(fechaLocalISO).find(iso => porFecha[iso]) || fechaLocalISO(dias[0]));
            mostrarDia(primero);
            if (botones[primero]) botones[primero].scrollIntoView({ block: 'nearest', inline: 'center' });
        })();
    }

    // ========== FUNCIONES POR DIA (listado_box / producto, desde api/funciones.php) ==========
    function fechaLocalISO(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function fechaDesdeISO(iso) {
        const [y, m, d] = iso.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    function capitalizar(t) {
        return t.charAt(0).toUpperCase() + t.slice(1);
    }
    function nombreDia(d) { return capitalizar(d.toLocaleDateString('es-AR', { weekday: 'long' })); }
    function nombreMes(d) { return capitalizar(d.toLocaleDateString('es-AR', { month: 'long' })); }

    async function cargarFunciones(slug) {
        try {
            let url = 'api/funciones.php?desde=' + fechaLocalISO(new Date()) + '&dias=' + DIAS_CARTELERA;
            if (slug) url += '&slug=' + encodeURIComponent(slug);
            const res = await fetch(url);
            if (!res.ok) throw new Error('No se pudieron cargar las funciones');
            return await res.json();
        } catch (error) {
            console.error('Error al cargar funciones:', error);
            return [];
        }
    }

    function funcionYaPaso(f) {
        const [hh, mm] = f.hora.split(':').map(Number);
        const d = fechaDesdeISO(f.fecha);
        d.setHours(hh, mm, 0, 0);
        return d < new Date();
    }

    // Boton de horario: link a asientos.html, o texto apagado si la funcion ya empezo
    function crearBotonFuncion(f) {
        const pasada = funcionYaPaso(f);
        const el = document.createElement(pasada ? 'span' : 'a');
        el.className = 'showtime-btn' + (pasada ? ' pasada' : '');
        if (!pasada) {
            const q = new URLSearchParams({
                peli: f.slug, hora: f.hora, sala: String(f.id_sala),
                fecha: f.fecha, funcion: String(f.id_itinerario)
            });
            el.href = 'asientos.html?' + q.toString();
        }
        el.appendChild(crearEl('span', f.hora, 'time'));
        el.appendChild(crearEl('span', f.nombre_sala + (f.subtitulada ? ' · Subtitulada' : ''), 'sala'));
        return el;
    }

    // ----- listado_box.html -----
    const gridPeliculas = document.querySelector('[data-role="lista-peliculas-box"]');
    if (gridPeliculas) {
        const listaDias = document.querySelector('[data-role="dias-lista"]');
        const resumenDia = document.querySelector('[data-role="dias-resumen"]');

        function tarjetaPelicula(p, funcionesDelDia, iso) {
            const card = crearEl('div', null, 'product-card');

            const img = document.createElement('img');
            img.src = p.imagen || '';
            img.alt = p.titulo;
            card.appendChild(img);

            const body = crearEl('div', null, 'card-body');
            body.appendChild(crearEl('h2', p.titulo));
            body.appendChild(crearEl('p', [p.clasificacion, p.generos].filter(Boolean).join(' · '), 'meta'));

            if (funcionesDelDia) {
                const horarios = crearEl('div', null, 'showtimes');
                funcionesDelDia.forEach(f => horarios.appendChild(crearBotonFuncion(f)));
                body.appendChild(horarios);
            } else {
                body.appendChild(crearEl('p', '$' + PRECIO_ASIENTO.toLocaleString('es-AR'), 'price'));
                body.appendChild(crearEl('p', p.descripcion || '', 'desc'));
            }

            const link = crearEl('a', funcionesDelDia ? 'Ver ficha' : 'Ver funciones', 'btn-card');
            link.href = 'producto.html?peli=' + encodeURIComponent(p.slug) + (iso ? '&fecha=' + iso : '');
            body.appendChild(link);

            card.appendChild(body);
            return card;
        }

        (async () => {
            const [peliculas, funciones] = await Promise.all([cargarPeliculas(), cargarFunciones()]);
            gridPeliculas.textContent = '';

            if (!peliculas || peliculas.length === 0) {
                gridPeliculas.appendChild(crearEl('p', 'No hay películas cargadas.', 'placeholder'));
                return;
            }

            const renderTodas = () => peliculas.forEach(p => gridPeliculas.appendChild(tarjetaPelicula(p, null)));

            // Sin selector en la pagina: se muestra la cartelera completa como siempre
            if (!listaDias) { renderTodas(); return; }

            const porFecha = {};
            funciones.forEach(f => { (porFecha[f.fecha] = porFecha[f.fecha] || []).push(f); });

            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const dias = [];
            for (let i = 0; i < DIAS_CARTELERA; i++) {
                const d = new Date(hoy);
                d.setDate(hoy.getDate() + i);
                dias.push(d);
            }

            const botones = {};

            function mostrarDia(iso) {
                Object.keys(botones).forEach(k => botones[k].classList.toggle('activo', k === iso));
                gridPeliculas.textContent = '';

                const delDia = porFecha[iso] || [];
                if (delDia.length === 0) {
                    if (funciones.length === 0) {
                        if (resumenDia) resumenDia.textContent = 'Todavía no hay funciones programadas. Estas son las películas en cartelera:';
                        renderTodas();
                    } else {
                        if (resumenDia) resumenDia.textContent = '';
                        gridPeliculas.appendChild(crearEl('p', 'No hay funciones programadas para este día.', 'placeholder'));
                    }
                    return;
                }

                // Agrupa las funciones del dia por pelicula (ya vienen ordenadas por titulo y hora)
                const grupos = new Map();
                delDia.forEach(f => {
                    if (!grupos.has(f.id_pelicula)) grupos.set(f.id_pelicula, []);
                    grupos.get(f.id_pelicula).push(f);
                });

                if (resumenDia) {
                    resumenDia.textContent = grupos.size + (grupos.size === 1 ? ' película' : ' películas') +
                        ' · ' + delDia.length + (delDia.length === 1 ? ' función' : ' funciones');
                }

                grupos.forEach((fs, idPelicula) => {
                    const p = peliculas.find(x => x.id_pelicula === idPelicula) ||
                        { titulo: fs[0].titulo, slug: fs[0].slug, imagen: fs[0].imagen, clasificacion: fs[0].clasificacion };
                    gridPeliculas.appendChild(tarjetaPelicula(p, fs, iso));
                });
            }

            dias.forEach(d => {
                const iso = fechaLocalISO(d);
                const b = crearEl('button', null, 'dias-card' + (porFecha[iso] ? '' : ' sin-funciones'));
                b.type = 'button';
                b.appendChild(crearEl('span', nombreDia(d), 'dias-nombre'));
                b.appendChild(crearEl('span', String(d.getDate()), 'dias-numero'));
                b.appendChild(crearEl('span', nombreMes(d), 'dias-mes'));
                b.addEventListener('click', () => mostrarDia(iso));
                botones[iso] = b;
                listaDias.appendChild(b);
            });

            document.querySelectorAll('[data-dias-nav]').forEach(btn => {
                btn.addEventListener('click', () => {
                    listaDias.scrollBy({ left: Number(btn.dataset.diasNav) * listaDias.clientWidth, behavior: 'smooth' });
                });
            });

            // Arranca en el primer dia que tenga funciones (o en hoy si no hay ninguna)
            const pedido = new URLSearchParams(window.location.search).get('fecha');
            const primero = (pedido && botones[pedido]) ? pedido
                : (dias.map(fechaLocalISO).find(iso => porFecha[iso]) || fechaLocalISO(dias[0]));
            mostrarDia(primero);
            if (botones[primero]) botones[primero].scrollIntoView({ block: 'nearest', inline: 'center' });
        })();
    }

    // ----- producto.html -----
    const detalleTitulo = document.querySelector('[data-role="detalle-titulo"]');
    if (detalleTitulo) {
        const params = new URLSearchParams(window.location.search);
        const slugActual = params.get('peli');

        (async () => {
            const pelicula = slugActual ? await cargarPeliculaPorSlug(slugActual) : null;

            if (!pelicula) {
                detalleTitulo.textContent = 'Película no encontrada';
                return;
            }

            const img = document.querySelector('[data-role="detalle-img"]');
            if (img) {
                img.src = pelicula.imagen || '';
                img.alt = pelicula.titulo;
            }

            detalleTitulo.textContent = pelicula.titulo;

            const badges = document.querySelector('[data-role="detalle-badges"]');
            if (badges) {
                badges.textContent = '';
                if (pelicula.clasificacion) badges.appendChild(crearEl('span', pelicula.clasificacion, 'badge'));
                if (pelicula.generos) badges.appendChild(crearEl('span', pelicula.generos, 'badge'));
                if (pelicula.duracion) {
                    const horas = Math.floor(pelicula.duracion / 60);
                    const minutos = pelicula.duracion % 60;
                    badges.appendChild(crearEl('span', horas + 'h ' + minutos + 'min', 'badge'));
                }
            }

            const desc = document.querySelector('[data-role="detalle-desc"]');
            if (desc) desc.textContent = pelicula.descripcion || '';

            // Funciones reales de la base (api/funciones.php), agrupadas por día
            const contFunciones = document.querySelector('[data-role="detalle-showtimes"]');
            if (contFunciones) {
                contFunciones.textContent = '';
                const funcionesPeli = await cargarFunciones(pelicula.slug);
                if (funcionesPeli.length === 0) {
                    contFunciones.appendChild(crearEl('p', 'Esta película todavía no tiene funciones programadas.', 'placeholder'));
                } else {
                    // Solo se muestran las funciones del día elegido en la cartelera (?fecha=)
                    const disponibles = [...new Set(funcionesPeli.map(f => f.fecha))];
                    const pedida = params.get('fecha');
                    const fechaElegida = (pedida && disponibles.includes(pedida)) ? pedida : disponibles[0];
                    const d = fechaDesdeISO(fechaElegida);

                    contFunciones.appendChild(crearEl('h3',
                        'Funciones del ' + nombreDia(d).toLowerCase() + ' ' + d.getDate() + ' de ' + nombreMes(d).toLowerCase(),
                        'dia-bloque-titulo'));

                    if (pedida && !disponibles.includes(pedida)) {
                        contFunciones.appendChild(crearEl('p',
                            'Esta película no tiene funciones el día que elegiste; te mostramos el primer día disponible.', 'aviso-dia'));
                    }

                    const fila = crearEl('div', null, 'showtimes');
                    funcionesPeli.filter(f => f.fecha === fechaElegida)
                        .forEach(f => fila.appendChild(crearBotonFuncion(f)));
                    contFunciones.appendChild(fila);

                    const otroDia = crearEl('a', '← Elegir otro día', 'cambiar-dia');
                    otroDia.href = 'listado_box.html?fecha=' + fechaElegida;
                    contFunciones.appendChild(otroDia);
                }
            }
        })();
    }

    // Página "Mis compras": dibuja las compras del usuario logueado
    function renderizarMisCompras() {
        const subtitulo = document.querySelector('#mc-subtitulo');
        const mensaje = document.querySelector('#mensaje');
        listaCompras.textContent = '';

        if (!sesion) {
            if (subtitulo) subtitulo.textContent = 'Esta sección es privada.';
            mostrarMensaje(mensaje, 'Necesitás iniciar sesión para ver tus compras.', 'error');
            const enlace = crearEl('a', 'Ir a iniciar sesión', 'btn-submit');
            enlace.href = 'login.html';
            enlace.style.display = 'inline-block';
            enlace.style.width = 'auto';
            enlace.style.textDecoration = 'none';
            listaCompras.appendChild(enlace);
            return;
        }

        const compras = sesion.compras || [];
        if (subtitulo) {
            subtitulo.textContent = compras.length === 0
                ? 'Cuenta de ' + sesion.cliente.nombre
                : compras.length + (compras.length === 1 ? ' compra' : ' compras') + ' de ' + sesion.cliente.nombre;
        }
        mostrarMensaje(mensaje, '', 'info');

        if (compras.length === 0) {
            listaCompras.appendChild(crearEl('p', 'Todavía no tenés compras registradas.', 'compra-vacia'));
            return;
        }

        compras.forEach(c => {
            const tarjeta = crearEl('div', null, 'compra-card');

            const cabecera = crearEl('div', null, 'compra-cabecera');
            cabecera.appendChild(crearEl('h3', c.pelicula));
            cabecera.appendChild(crearEl('span', c.situacion_boleto, 'compra-estado estado-' + String(c.situacion_boleto).replace(/[^a-z]/gi, '')));
            tarjeta.appendChild(cabecera);

            const detalle = (etiqueta, valor) => {
                const p = crearEl('p');
                p.appendChild(crearEl('strong', etiqueta + ': '));
                p.appendChild(document.createTextNode(valor));
                tarjeta.appendChild(p);
            };

            detalle('Ticket', '#' + c.id_ticket);
            detalle('Función', c.fecha_funcion + ' · ' + String(c.horario_inicio).substring(0, 5) + ' · ' + c.sala);
            detalle('Butacas', (c.butacas || []).join(', '));
            if ((c.combos || []).length > 0) {
                detalle('Combos', c.combos.map(x => x.cantidad + 'x ' + x.nombre).join(', '));
            }
            detalle('Medio de pago', c.medio_de_pago);
            detalle('Fecha de compra', c.fecha_boleto);

            tarjeta.appendChild(crearEl('p', 'Total: $' + Number(c.total).toLocaleString('es-AR'), 'compra-total'));

            const validar = crearEl('a', 'Validar QR', 'btn-submit');
            validar.href = 'validar.html?' + new URLSearchParams({ r: c.codigo, f: c.firma }).toString();
            validar.style.display = 'inline-block';
            validar.style.width = 'auto';
            validar.style.textDecoration = 'none';
            validar.style.marginTop = '8px';
            tarjeta.appendChild(validar);

            listaCompras.appendChild(tarjeta);
        });
    }

    // ----- Login -----
    if (formLogin) {
        formLogin.addEventListener('submit', async (e) => {
            e.preventDefault();
            mostrarMensaje(msgLogin, 'Autenticando con el servidor...', 'info');

            const payload = {
                email: formLogin.elements['email'].value.trim(),
                password: formLogin.elements['password'].value
            };

            try {
                const { ok, data } = await llamarAPI('api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (ok && data.success) {
                    formLogin.reset();
                    // Admin va a métricas, cliente a la cartelera
                    if (data.rol === 'administrador') {
                        window.location.assign('metricas.html');
                    } else {
                        window.location.assign('index.html');
                    }
                } else {
                    mostrarMensaje(msgLogin, data.error || 'Credenciales inválidas.', 'error');
                }
            } catch (error) {
                mostrarMensaje(msgLogin, 'Error de conexión con la API de autenticación.', 'error');
            }
        });
    }

    // ----- Registro -----
    if (formRegistro) {
        formRegistro.addEventListener('submit', async (e) => {
            e.preventDefault();
            mostrarMensaje(msgRegistro, 'Guardando usuario...', 'info');

            const payload = {
                nombre: formRegistro.elements['nombre'].value.trim(),
                email: formRegistro.elements['email'].value.trim(),
                password: formRegistro.elements['password'].value
            };

            try {
                const { ok, data } = await llamarAPI('api/registro.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (ok && data.success) {
                    formRegistro.reset();
                    mostrarMensaje(msgRegistro, '', 'info');
                    toggleForms(); // vuelve al panel de login
                    if (formLogin) formLogin.elements['email'].value = payload.email;
                    mostrarMensaje(msgLogin, data.mensaje || 'Registro exitoso. Ya podés iniciar sesión.', 'exito');
                } else {
                    mostrarMensaje(msgRegistro, data.error || 'Error al registrar el usuario.', 'error');
                }
            } catch (error) {
                mostrarMensaje(msgRegistro, 'Error al conectar con la API de registro.', 'error');
            }
        });
    }

    // ----- Logout -----
    document.querySelectorAll('[data-accion="logout"]').forEach(el => {
        el.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await llamarAPI('api/logout.php', { method: 'POST' });
            } catch (error) {
                console.error('Error al cerrar sesión:', error);
            }
            sesion = null;
            if (listaCompras || window.location.pathname.includes('metricas.html')) {
                window.location.assign('login.html');  // página privada: se sale
            } else {
                actualizarInterfazSesion();
            }
        });
    });

    // Al cargar CUALQUIER página se verifica la sesión contra el endpoint privado
    comprobarSesion();

    // Contador de visitas: una vez por sesión del navegador y por día (no cuenta el panel de métricas)
    (function registrarVisita() {
        if (window.location.pathname.includes('metricas.html')) return;
        try {
            const hoy = fechaLocalISO(new Date());
            if (sessionStorage.getItem('visita_registrada') === hoy) return;
            sessionStorage.setItem('visita_registrada', hoy);
        } catch (e) { return; }
        fetch('api/visita.php', { method: 'POST', credentials: 'same-origin' }).catch(() => { });
    })();

    // ========== ASIENTOS (se dibujan desde api/asientos.php) ==========
    const gridAsientos = document.querySelector('[data-role="asientos-grid"]');
    const totalPriceAsientos = document.querySelector('[data-role="total-price"]');
    const botonConfirmar = document.querySelector('[data-action="confirmar-asientos"]');

    if (gridAsientos && botonConfirmar) {
        const infoFuncion = document.querySelector('[data-role="info-funcion"]');
        const idFuncion = Number(new URLSearchParams(window.location.search).get('funcion'));

        function calcularTotalAsientos() {
            let total = 0;
            gridAsientos.querySelectorAll('.asiento.seleccionado').forEach(asiento => {
                total += parseFloat(asiento.getAttribute('data-price')) || 0;
            });
            if (totalPriceAsientos) {
                totalPriceAsientos.textContent = '$' + Math.round(total).toLocaleString('es-AR');
            }
            return Math.round(total);
        }

        function mensajeGrid(texto) {
            gridAsientos.textContent = '';
            gridAsientos.style.display = 'grid';
            gridAsientos.style.gridTemplateColumns = '1fr';
            gridAsientos.appendChild(crearEl('p', texto, 'placeholder'));
            botonConfirmar.disabled = true;
        }

        function renderizarAsientos(data) {
            const funcion = data.funcion;
            const lista = data.asientos || [];

            if (infoFuncion && funcion) {
                infoFuncion.textContent = funcion.pelicula + ' · ' + funcion.fecha + ' · ' +
                    String(funcion.hora).substring(0, 5) + ' · ' + funcion.sala;
            }
            if (lista.length === 0) {
                mensajeGrid('Esta función todavía no tiene butacas cargadas.');
                return;
            }

            // Agrupa por fila (primera letra) para alinear la grilla según la sala
            const filas = new Map();
            lista.forEach(a => {
                const letra = a.numero.charAt(0);
                if (!filas.has(letra)) filas.set(letra, []);
                filas.get(letra).push(a);
            });
            gridAsientos.textContent = '';
            gridAsientos.style.display = 'flex';
            gridAsientos.style.width = '100%';
            gridAsientos.style.maxWidth = '100%';
            gridAsientos.style.margin = '0 auto';

            filas.forEach(fs => {
                const fila = crearEl('div', null, 'asiento-fila');
                fila.style.gridTemplateColumns = 'repeat(' + fs.length + ', 40px)';
                fs.forEach(a => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.setAttribute('data-role', 'asiento');
                    b.setAttribute('data-name', a.numero);
                    b.setAttribute('data-price', String(a.precio));
                    b.textContent = a.numero;

                    if (a.estado === 'disponible') {
                        b.className = 'asiento libre';
                        b.title = '$' + Math.round(a.precio).toLocaleString('es-AR');
                        b.addEventListener('click', () => {
                            b.classList.toggle('seleccionado');
                            calcularTotalAsientos();
                        });
                    } else {
                        b.className = 'asiento ocupado';
                        b.disabled = true;
                        b.title = 'No disponible';
                    }
                    fila.appendChild(b);
                });
                gridAsientos.appendChild(fila);
            });
        }

        async function cargarAsientos() {
            if (!idFuncion) {
                mensajeGrid('Elegí una función desde la cartelera para ver sus butacas.');
                return;
            }
            try {
                const { ok, data } = await llamarAPI('api/asientos.php?funcion=' + idFuncion);
                if (!ok) {
                    mensajeGrid(data.error || 'No se pudieron cargar las butacas.');
                    return;
                }
                renderizarAsientos(data);
            } catch (error) {
                mensajeGrid('Error de conexión con el servidor.');
            }
        }

        botonConfirmar.addEventListener('click', () => {
            const seleccionados = gridAsientos.querySelectorAll('.asiento.seleccionado');
            if (seleccionados.length === 0) {
                alert('Por favor, elegí al menos un asiento antes de continuar.');
                return;
            }

            const params = new URLSearchParams(window.location.search);
            const codigos = Array.from(seleccionados).map(a => a.getAttribute('data-name'));

            params.set('asientos', codigos.join(','));
            params.set('total_asientos', String(calcularTotalAsientos()));  // suma real de las butacas elegidas
            window.location.href = 'combos.html?' + params.toString();
        });

        cargarAsientos();
    }

    const candyForm = document.querySelector('[data-role="candy-form"]');
    const listaCombos = document.querySelector('[data-role="lista-combos"]');
    const contenedorCombos = document.querySelector('[data-role="candy-shop"]') || listaCombos;

    if (candyForm || contenedorCombos) {

        async function cargarCombos() {
            try {
                const respuesta = await fetch('api/combos.php');
                if (!respuesta.ok) {
                    throw new Error('No se pudo cargar el archivo de combos');
                }
                const combos = await respuesta.json();
                renderizarCombos(combos);
            } catch (error) {
                console.error('Error al cargar combos:', error);
                if (contenedorCombos) {
                    const msg = document.createElement('p');
                    msg.style.color = 'red';
                    msg.textContent = 'Error al cargar los combos. Intentá de nuevo más tarde.';
                    contenedorCombos.appendChild(msg);
                }
            }
        }

        function renderizarCombos(combos) {
            const destino = listaCombos || candyForm;
            if (!destino) return;

            destino.querySelectorAll('[data-role="product-combo"]').forEach(el => el.remove());

            combos.forEach(combo => {
                const nombre = combo.titulo || combo.nombre || 'Combo';
                const precio = Number(combo.precio) || 0;
                const descripcion = combo.descripcion || '';

                const div = document.createElement('div');
                div.className = 'product-combo';
                div.setAttribute('data-role', 'product-combo');
                div.setAttribute('data-name', nombre);
                div.setAttribute('data-price', precio);

                // Se arma con createElement/textContent: el navegador nunca interpreta HTML de los datos
                const info = crearEl('div', null, 'product-info');
                if (combo.imagen) {
                    const img = document.createElement('img');
                    img.src = combo.imagen;
                    img.alt = nombre;
                    img.style.cssText = 'width:50px;height:50px;object-fit:contain;flex-shrink:0;';
                    info.appendChild(img);
                } else if (combo.icono) {
                    info.appendChild(crearEl('span', combo.icono, 'product-icon'));
                }

                const texto = document.createElement('div');
                texto.style.flex = '1';
                texto.appendChild(crearEl('h2', nombre));
                texto.appendChild(crearEl('p', descripcion));
                texto.appendChild(crearEl('span', '$ ' + precio.toLocaleString('es-AR'), 'price'));
                info.appendChild(texto);
                div.appendChild(info);

                const controles = crearEl('div', null, 'quantity-controls');
                const cantidad = document.createElement('input');
                cantidad.type = 'number';
                cantidad.setAttribute('aria-label', 'Cantidad de ' + nombre + (descripcion ? ': ' + descripcion : ''));
                cantidad.setAttribute('data-role', 'quantity-input');
                cantidad.className = 'quantity-input';
                cantidad.setAttribute('data-id', combo.id ?? '');
                cantidad.value = '0';
                cantidad.min = '0';
                controles.appendChild(cantidad);
                div.appendChild(controles);

                const cupon = destino.querySelector('[data-role="cupon-container"]');
                if (cupon) {
                    destino.insertBefore(div, cupon);
                } else {
                    destino.appendChild(div);
                }
            });

            document.querySelectorAll('[data-role="quantity-input"]').forEach(input => {
                input.addEventListener('input', calcularTotalCombos);
                input.addEventListener('change', calcularTotalCombos);
            });

            calcularTotalCombos();
        }

        function getCantidadAsientos() {
            const params = new URLSearchParams(window.location.search);
            const asientosParam = params.get('asientos');
            if (!asientosParam) return 0;
            return asientosParam.split(',').filter(a => a.trim() !== '').length;
        }

        function calcularTotalCombos() {
            const totalPriceEl = document.querySelector('[data-role="total-price"]');
            if (!totalPriceEl) return;

            const totalAsientosParam = Number(new URLSearchParams(window.location.search).get('total_asientos'));
            const totalAsientos = totalAsientosParam > 0 ? totalAsientosParam : getCantidadAsientos() * PRECIO_ASIENTO;

            let totalCombos = 0;
            document.querySelectorAll('[data-role="product-combo"]').forEach(combo => {
                const precio = parseInt(combo.getAttribute('data-price')) || 0;
                const input = combo.querySelector('[data-role="quantity-input"]');
                const cantidad = input ? (parseInt(input.value) || 0) : 0;
                totalCombos += precio * cantidad;
            });

            let total = totalAsientos + totalCombos;
            if (descuentoCupon > 0) {
                total = Math.round(total - total * descuentoCupon);
            }

            totalPriceEl.textContent = '$' + total.toLocaleString('es-AR');
        }

        function validarCupon() {
            const cuponInput = document.querySelector('[data-role="cupon-input"]');
            const mensajeEl = document.querySelector('[data-role="mensaje-cupon"]');
            if (!cuponInput || !mensajeEl) return;

            const codigo = cuponInput.value.trim().toUpperCase();
            mensajeEl.classList.remove('mensaje-error', 'mensaje-exito');
            cuponAplicado = '';

            if (codigo === '') {
                mensajeEl.textContent = 'Por favor, ingrese un código';
                mensajeEl.classList.add('mensaje-error');
                descuentoCupon = 0;
            } else if (codigo === 'UCP10') {
                mensajeEl.textContent = '¡Cupón aplicado! Tenés un 10% de descuento';
                mensajeEl.classList.add('mensaje-exito');
                descuentoCupon = 0.10;
                cuponAplicado = codigo;
            } else {
                mensajeEl.textContent = 'Código inválido o vencido';
                mensajeEl.classList.add('mensaje-error');
                descuentoCupon = 0;
            }

            calcularTotalCombos();
        }

        function irAPagar(incluirCombos = true) {
            const params = new URLSearchParams(window.location.search);
            const totalPriceEl = document.querySelector('[data-role="total-price"]');

            if (incluirCombos) {
                const combosElegidos = [];
                const combosIds = [];
                document.querySelectorAll('[data-role="product-combo"]').forEach(combo => {
                    const input = combo.querySelector('[data-role="quantity-input"]');
                    const cant = input ? (parseInt(input.value) || 0) : 0;
                    if (cant > 0) {
                        const nombre = combo.getAttribute('data-name') || 'Combo';
                        combosElegidos.push(`${cant}x ${nombre}`);
                        combosIds.push(`${input.getAttribute('data-id')}:${cant}`);
                    }
                });

                if (combosElegidos.length > 0) {
                    params.set('combos', combosElegidos.join(' | '));
                    params.set('combos_ids', combosIds.join(','));
                } else {
                    params.delete('combos');
                    params.delete('combos_ids');
                }
            } else {
                params.delete('combos');
                params.delete('combos_ids');
            }

            // El cupón viaja solo como código: el servidor decide si es válido y calcula el descuento
            if (cuponAplicado) params.set('cupon', cuponAplicado);
            else params.delete('cupon');

            if (totalPriceEl) {
                params.set('total', totalPriceEl.textContent.replace(/[^\d]/g, ''));
            }

            window.location.href = 'comprar.html?' + params.toString();
        }

        const btnCupon = document.querySelector('[data-action="aplicar-cupon"]');
        if (btnCupon) btnCupon.addEventListener('click', validarCupon);

        const btnConfirmarCombos = document.querySelector('[data-action="confirmar-combos"]');
        if (btnConfirmarCombos) {
            btnConfirmarCombos.addEventListener('click', (e) => {
                e.preventDefault();
                irAPagar(true);
            });
        }

        if (candyForm) {
            candyForm.addEventListener('submit', (e) => {
                e.preventDefault();
                irAPagar(true);
            });
        }

        const btnOmitir = document.querySelector('[data-action="omitir-combos"]');
        if (btnOmitir) {
            btnOmitir.addEventListener('click', (e) => {
                e.preventDefault();
                irAPagar(false);
            });
        }

        cargarCombos();
    }

    const formCompra = document.querySelector('[data-role="compra-form"]');

    if (formCompra) {
        const params = new URLSearchParams(window.location.search);

        const peliSlug = params.get('peli');
        const hora = params.get('hora') || '18:30';
        const sala = params.get('sala') || '2';
        const asientosParam = params.get('asientos');
        const combos = params.get('combos');
        const total = params.get('total');

        const peliEl = document.querySelector('[data-role="peli-sel"]');
        if (peliEl) {
            peliEl.textContent = 'Película seleccionada';
            if (peliSlug) {
                cargarPeliculaPorSlug(peliSlug).then(pelicula => {
                    peliEl.textContent = pelicula ? pelicula.titulo : 'Película seleccionada';
                });
            }
        }

        const horaEl = document.querySelector('[data-role="hora-sel"]');
        if (horaEl) horaEl.textContent = hora;

        const salaEl = document.querySelector('[data-role="sala-sel"]');
        if (salaEl) salaEl.textContent = sala;

        const asientosEl = document.querySelector('[data-role="asientos-sel"]');
        if (asientosEl) {
            asientosEl.textContent = asientosParam
                ? asientosParam.split(',').join(', ')
                : 'Sin asignar';
        }

        if (combos) {
            const combosWrap = document.querySelector('[data-role="combos-sel-wrap"]');
            const combosEl = document.querySelector('[data-role="combos-sel"]');
            if (combosWrap && combosEl) {
                combosEl.textContent = combos;
                combosWrap.style.display = 'block';
            } else if (combosEl) {
                combosEl.textContent = combos;
            }
        }

        const totalEl = document.querySelector('[data-role="total-sel"]');
        if (totalEl) {
            const numero = total ? (parseInt(total.replace(/\D/g, '')) || 0) : 0;
            totalEl.textContent = '$' + numero.toLocaleString('es-AR');
        }

        const formSection = document.querySelector('[data-role="form-section"]');
        const successSection = document.querySelector('[data-role="success-section"]');

        function mostrarExito(data) {
            // Código de reserva
            const codigoEl = document.querySelector('[data-role="codigo-reserva"]');
            if (codigoEl) {
                codigoEl.textContent = data.codigo || ('CL-' + String(data.id_ticket).padStart(5, '0'));
            }

            // QR real
            const qrDiv = document.querySelector('.qr-placeholder');
            if (qrDiv && data.qr_base64) {
                qrDiv.style.cssText = 'display:flex; justify-content:center; align-items:center; padding:16px; background:#fff; width:252px; height:252px;';
                qrDiv.innerHTML = '';
                const img = document.createElement('img');
                img.src = 'data:image/png;base64,' + data.qr_base64;
                img.alt = 'Código QR de la reserva';
                img.style.cssText = 'width:220px; height:220px; display:block;';
                qrDiv.appendChild(img);
            }

            // Mensaje del correo
            const pCorreo = successSection ? successSection.querySelector('p') : null;
            if (pCorreo && data.correo) {
                if (data.correo.enviado) {
                    pCorreo.textContent = 'Te enviamos el código y el QR a ' + (data.correo.destino || 'tu correo') + '.';
                } else {
                    pCorreo.textContent = 'La compra quedó registrada, pero no se pudo enviar el correo'
                        + (data.correo.motivo === 'sin_configurar' ? ' (SMTP no configurado).' : '.');
                }
            }

            if (formSection) formSection.style.display = 'none';
            if (successSection) {
                successSection.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        formCompra.addEventListener('submit', async (e) => {
            e.preventDefault();

            const idItinerario = Number(params.get('funcion'));
            const asientosElegidos = asientosParam
                ? asientosParam.split(',').map(s => s.trim()).filter(Boolean)
                : [];

            if (!idItinerario || asientosElegidos.length === 0) {
                alert('Faltan datos de la función o de los asientos. Volvé a elegir tu función.');
                return;
            }

            const pagoEl = document.querySelector('[data-role="campo-pago"]');
            const combosElegidos = (params.get('combos_ids') || '').split(',').filter(Boolean).map(par => {
                const [id, cantidad] = par.split(':');
                return { id: Number(id), cantidad: Number(cantidad) };
            });
            const btn = formCompra.querySelector('[type="submit"]');
            if (btn) btn.disabled = true;

            try {
                const { ok, status, data } = await llamarAPI('api/comprar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_itinerario: idItinerario,
                        asientos: asientosElegidos,
                        medio_de_pago: pagoEl ? pagoEl.value : '',
                        combos: combosElegidos,
                        cupon: params.get('cupon') || ''
                    })
                });

                if (status === 401) {
                    window.location.assign('login.html');
                    return;
                }
                if (!ok) {
                    alert(data.error || 'No se pudo completar la compra.');
                    return;
                }
                mostrarExito(data);
            } catch (error) {
                alert('Error de conexión con el servidor.');
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }

    const selectorMetricas = document.querySelector('[data-role="selector-metricas"]');
    const btnActualizarMetricas = document.querySelector('[data-action="actualizar-metricas"]');
    const resultadoMetricas = document.querySelector('[data-role="resultado-metricas"]');

    if (selectorMetricas && resultadoMetricas) {

        let datosMetricas = null;

        async function obtenerMetricas() {
            try {
                // Endpoint protegido: 401 sin sesión, 403 si no es administrador
                const respuesta = await fetch('api/metricas.php?t=' + Date.now(), { credentials: 'same-origin' });
                if (respuesta.status === 401 || respuesta.status === 403) {
                    window.location.assign(respuesta.status === 401 ? 'login.html' : 'index.html');
                    return null;
                }
                if (!respuesta.ok) {
                    throw new Error('No se pudieron cargar las métricas');
                }
                datosMetricas = await respuesta.json();
                return datosMetricas;
            } catch (error) {
                console.error('Error al obtener métricas:', error);
                resultadoMetricas.textContent = '';
                const msg = crearEl('p', 'Error al cargar las métricas. Revisá api/metricas.php');
                msg.style.color = 'red';
                resultadoMetricas.appendChild(msg);
                return null;
            }
        }

        function mostrarMetricas(tipo) {
            if (!datosMetricas) return;

            const fmt = (n) => Number(n).toLocaleString('es-AR', { maximumFractionDigits: 0 });
            // El servidor manda el total real (los precios varían por fila y por cupón)
            const subtotalDe = (item) => item.total !== undefined ? Number(item.total) : item.cantidad * item.precio;
            const frag = document.createDocumentFragment();
            let totalAcumulado = 0;

            // <li> con texto plano y, opcionalmente, un <strong> al inicio y un <em> al final
            const crearLi = (inicioNegrita, texto, finalEnfasis) => {
                const li = document.createElement('li');
                if (inicioNegrita !== null) li.appendChild(crearEl('strong', inicioNegrita));
                li.appendChild(document.createTextNode(texto));
                if (finalEnfasis !== null) li.appendChild(crearEl('em', finalEnfasis));
                return li;
            };
            const crearTotal = (etiqueta, valor) => {
                const p = crearEl('p', null, 'total-metricas');
                p.appendChild(crearEl('strong', etiqueta));
                p.appendChild(document.createTextNode(' ' + valor));
                return p;
            };

            if (tipo === 'entradas') {
                frag.appendChild(crearEl('h3', 'Entradas vendidas'));
                const ul = document.createElement('ul');
                datosMetricas.entradas.forEach(item => {
                    const subtotal = subtotalDe(item);
                    totalAcumulado += subtotal;
                    ul.appendChild(crearLi(item.pelicula,
                        ': ' + item.cantidad + (item.cantidad === 1 ? ' entrada = ' : ' entradas = '),
                        '$' + fmt(subtotal)));
                });
                frag.appendChild(ul);
                frag.appendChild(crearTotal('Total recaudado entradas:', '$' + fmt(totalAcumulado)));

            } else if (tipo === 'combos') {
                frag.appendChild(crearEl('h3', 'Combos vendidos'));
                const ul = document.createElement('ul');
                datosMetricas.combos.forEach(item => {
                    const subtotal = subtotalDe(item);
                    totalAcumulado += subtotal;
                    ul.appendChild(crearLi(item.nombre,
                        ': ' + item.cantidad + ' vendidos = ',
                        '$' + fmt(subtotal)));
                });
                frag.appendChild(ul);
                frag.appendChild(crearTotal('Total recaudado combos:', '$' + fmt(totalAcumulado)));

            } else if (tipo === 'visitas') {
                frag.appendChild(crearEl('h3', 'Visitas por día'));
                const ul = document.createElement('ul');
                datosMetricas.visitas.forEach(item => {
                    totalAcumulado += item.cantidad;
                    ul.appendChild(crearLi(item.dia, ': ' + item.cantidad + ' visitas', null));
                });
                frag.appendChild(ul);
                frag.appendChild(crearTotal('Total de visitas:', fmt(totalAcumulado)));

            } else if (tipo === 'recaudacion') {
                let totalEntradas = 0;
                let totalCombos = 0;

                datosMetricas.entradas.forEach(item => {
                    totalEntradas += subtotalDe(item);
                });
                datosMetricas.combos.forEach(item => {
                    totalCombos += subtotalDe(item);
                });

                totalAcumulado = totalEntradas + totalCombos;

                frag.appendChild(crearEl('h3', 'Recaudación total'));
                const ul = document.createElement('ul');
                [['Entradas: ', totalEntradas], ['Combos: ', totalCombos]].forEach(([etiqueta, valor]) => {
                    const li = document.createElement('li');
                    li.appendChild(document.createTextNode(etiqueta));
                    li.appendChild(crearEl('strong', '$' + fmt(valor)));
                    ul.appendChild(li);
                });
                frag.appendChild(ul);
                frag.appendChild(crearTotal('Total general:', '$' + fmt(totalAcumulado)));
            } else {
                frag.appendChild(crearEl('p', 'Seleccioná una métrica para ver los datos.', 'placeholder'));
            }

            resultadoMetricas.textContent = '';
            resultadoMetricas.appendChild(frag);
        }

        selectorMetricas.addEventListener('change', async () => {
            if (!datosMetricas) {
                resultadoMetricas.textContent = 'Cargando datos...';
                const datos = await obtenerMetricas();
                if (!datos) return;
            }
            mostrarMetricas(selectorMetricas.value);
        });

        if (btnActualizarMetricas) {
            btnActualizarMetricas.addEventListener('click', async () => {
                datosMetricas = null;
                resultadoMetricas.textContent = 'Cargando datos...';
                const datos = await obtenerMetricas();
                if (!datos) return;
                mostrarMetricas(selectorMetricas.value);
            });
        }
    }

});
