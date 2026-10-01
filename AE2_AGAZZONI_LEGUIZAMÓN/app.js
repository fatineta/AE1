const PRECIO_ASIENTO = 4800;
let descuentoCupon = 0;

document.addEventListener('DOMContentLoaded', () => {

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

    const formLogin = document.querySelector('[data-role="form-login"]');
    if (formLogin) {
        formLogin.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Sesión iniciada (simulación)');
            window.location.assign('index.html');
        });
    }

    const formRegistro = document.querySelector('[data-role="form-registro"]');
    if (formRegistro) {
        formRegistro.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Cuenta creada (simulación)');
            window.location.assign('index.html');
        });
    }

    // ========== ASIENTOS ==========
    // Los asientos ya usan clases de estado (.libre / .seleccionado) en el HTML/CSS del proyecto
    const asientos = document.querySelectorAll('[data-role="asiento"].libre, .asiento.libre');
    const totalPriceAsientos = document.querySelector('[data-role="total-price"]');
    const botonConfirmar = document.querySelector('[data-action="confirmar-asientos"]');

    if (asientos.length > 0 && botonConfirmar) {
        function calcularTotalAsientos() {
            let total = 0;
            document.querySelectorAll('.asiento.seleccionado, [data-role="asiento"].seleccionado').forEach(asiento => {
                total += parseInt(asiento.getAttribute('data-price')) || 0;
            });
            if (totalPriceAsientos) {
                totalPriceAsientos.textContent = '$' + total.toLocaleString('es-AR');
            }
        }

        asientos.forEach(asiento => {
            asiento.addEventListener('click', () => {
                asiento.classList.toggle('seleccionado');
                calcularTotalAsientos();
            });
        });

        botonConfirmar.addEventListener('click', () => {
            const seleccionados = document.querySelectorAll('.asiento.seleccionado, [data-role="asiento"].seleccionado');
            if (seleccionados.length === 0) {
                alert('Por favor, elegí al menos un asiento antes de continuar.');
                return;
            }

            const params = new URLSearchParams(window.location.search);
            const codigos = Array.from(seleccionados).map(a => a.textContent.trim());

            params.set('asientos', codigos.join(','));
            window.location.href = 'combos.html?' + params.toString();
        });
    }

    const candyForm = document.querySelector('[data-role="candy-form"]');
    const listaCombos = document.querySelector('[data-role="lista-combos"]');
    const contenedorCombos = document.querySelector('[data-role="candy-shop"]') || listaCombos;

    if (candyForm || contenedorCombos) {

        async function cargarCombos() {
            try {
                const respuesta = await fetch('data/combos.json');
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

                let visual = '';
                if (combo.imagen) {
                    visual = `<img src="${combo.imagen}" alt="${nombre}"
                        style="width:50px;height:50px;object-fit:contain;flex-shrink:0;">`;
                } else if (combo.icono) {
                    visual = `<span class="product-icon">${combo.icono}</span>`;
                }

                const div = document.createElement('div');
                div.className = 'product-combo';
                div.setAttribute('data-role', 'product-combo');
                div.setAttribute('data-name', nombre);
                div.setAttribute('data-price', precio);

                div.innerHTML = `
                    <div class="product-info">
                        ${visual}
                        <div style="flex:1;">
                            <h3>${nombre}</h3>
                            <p>${descripcion}</p>
                            <span class="price">$ ${precio.toLocaleString('es-AR')}</span>
                        </div>
                    </div>
                    <div class="quantity-controls">
                        <input type="number" data-role="quantity-input" class="quantity-input"
                               data-id="${combo.id ?? ''}" value="0" min="0">
                    </div>
                `;

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

            const totalAsientos = getCantidadAsientos() * PRECIO_ASIENTO;

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

            if (codigo === '') {
                mensajeEl.textContent = 'Por favor, ingrese un código';
                mensajeEl.classList.add('mensaje-error');
                descuentoCupon = 0;
            } else if (codigo === 'UCP10') {
                mensajeEl.textContent = '¡Cupón aplicado! Tenés un 10% de descuento';
                mensajeEl.classList.add('mensaje-exito');
                descuentoCupon = 0.10;
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
                document.querySelectorAll('[data-role="product-combo"]').forEach(combo => {
                    const input = combo.querySelector('[data-role="quantity-input"]');
                    const cant = input ? (parseInt(input.value) || 0) : 0;
                    if (cant > 0) {
                        const nombre = combo.getAttribute('data-name') || 'Combo';
                        combosElegidos.push(`${cant}x ${nombre}`);
                    }
                });

                if (combosElegidos.length > 0) {
                    params.set('combos', combosElegidos.join(' | '));
                } else {
                    params.delete('combos');
                }
            } else {
                params.delete('combos');
            }

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
        const TITULOS_PELICULAS = {
            'spiderman': 'Spider-Man: Brand New Day',
            'spider-man': 'Spider-Man: Brand New Day',
            'obsession': 'Obsession',
            'wicked': 'Wicked',
            'crepusculo': 'Crepúsculo',
            'chainsawman': 'Chainsaw Man: Reze Arc',
            'spiderverse': 'Spider-Man: Across the Spider-Verse',
            'batman': 'The Batman - Part II',
            'avatar': 'Avatar 3'
        };

        const params = new URLSearchParams(window.location.search);

        const peliSlug = params.get('peli');
        const hora = params.get('hora') || '18:30';
        const sala = params.get('sala') || '2';
        const asientosParam = params.get('asientos');
        const combos = params.get('combos');
        const total = params.get('total');

        const peliEl = document.querySelector('[data-role="peli-sel"]');
        if (peliEl) {
            peliEl.textContent = TITULOS_PELICULAS[peliSlug] || 'Película seleccionada';
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

        function mostrarExito() {
            const codigoRandom = 'CL-' + Math.random().toString(36).substring(2, 8).toUpperCase();
            const codigoEl = document.querySelector('[data-role="codigo-reserva"]');
            if (codigoEl) codigoEl.textContent = codigoRandom;

            if (formSection) formSection.style.display = 'none';
            if (successSection) {
                successSection.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        formCompra.addEventListener('submit', async (e) => {
            e.preventDefault();

            const nombreEl = document.querySelector('[data-role="campo-nombre"]');
            const emailEl = document.querySelector('[data-role="campo-email"]');
            const direccionEl = document.querySelector('[data-role="campo-direccion"]');
            const telefonoEl = document.querySelector('[data-role="campo-telefono"]');
            const pagoEl = document.querySelector('[data-role="campo-pago"]');
            const totalSelEl = document.querySelector('[data-role="total-sel"]');

            const datosOrden = {
                nombre: nombreEl ? nombreEl.value : '',
                email: emailEl ? emailEl.value : '',
                direccion: direccionEl ? direccionEl.value : '',
                telefono: telefonoEl ? telefonoEl.value : '',
                pago: pagoEl ? pagoEl.value : '',
                total: totalSelEl ? totalSelEl.textContent : '$0',
                fecha: new Date().toISOString()
            };

            try {
                await fetch('data/ordenes.json', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(datosOrden)
                });
                console.log('Orden enviada (simulada):', datosOrden);
            } catch (error) {
                console.warn('Simulación de envío:', error.message);
            }

            mostrarExito();
        });
    }

    const selectorMetricas = document.querySelector('[data-role="selector-metricas"]');
    const btnActualizarMetricas = document.querySelector('[data-action="actualizar-metricas"]');
    const resultadoMetricas = document.querySelector('[data-role="resultado-metricas"]');

    if (selectorMetricas && resultadoMetricas) {

        let datosMetricas = null;

        async function obtenerMetricas() {
            try {
                const respuesta = await fetch('data/metricas.json?t=' + Date.now());
                if (!respuesta.ok) {
                    throw new Error('No se pudieron cargar las métricas');
                }
                datosMetricas = await respuesta.json();
                return datosMetricas;
            } catch (error) {
                console.error('Error al obtener métricas:', error);
                resultadoMetricas.innerHTML = `
                    <p style="color:red;">Error al cargar las métricas. Revisá data/metricas.json</p>
                `;
                return null;
            }
        }

        function mostrarMetricas(tipo) {
            if (!datosMetricas) return;

            const fmt = (n) => n.toLocaleString('es-AR');
            let html = '';
            let totalAcumulado = 0;

            if (tipo === 'entradas') {
                html += `<h3>Entradas vendidas</h3><ul>`;
                datosMetricas.entradas.forEach(item => {
                    const subtotal = item.cantidad * item.precio;
                    totalAcumulado += subtotal;
                    html += `
                        <li>
                            <strong>${item.pelicula}</strong>:
                            ${item.cantidad} entradas × $${fmt(item.precio)}
                            = <em>$${fmt(subtotal)}</em>
                        </li>`;
                });
                html += `</ul>`;
                html += `<p class="total-metricas"><strong>Total recaudado entradas:</strong> $${fmt(totalAcumulado)}</p>`;

            } else if (tipo === 'combos') {
                html += `<h3>Combos vendidos</h3><ul>`;
                datosMetricas.combos.forEach(item => {
                    const subtotal = item.cantidad * item.precio;
                    totalAcumulado += subtotal;
                    html += `
                        <li>
                            <strong>${item.nombre}</strong>:
                            ${item.cantidad} × $${fmt(item.precio)}
                            = <em>$${fmt(subtotal)}</em>
                        </li>`;
                });
                html += `</ul>`;
                html += `<p class="total-metricas"><strong>Total recaudado combos:</strong> $${fmt(totalAcumulado)}</p>`;

            } else if (tipo === 'visitas') {
                html += `<h3>Visitas por día</h3><ul>`;
                datosMetricas.visitas.forEach(item => {
                    totalAcumulado += item.cantidad;
                    html += `<li><strong>${item.dia}</strong>: ${item.cantidad} visitas</li>`;
                });
                html += `</ul>`;
                html += `<p class="total-metricas"><strong>Total de visitas:</strong> ${fmt(totalAcumulado)}</p>`;

            } else if (tipo === 'recaudacion') {
                let totalEntradas = 0;
                let totalCombos = 0;

                datosMetricas.entradas.forEach(item => {
                    totalEntradas += item.cantidad * item.precio;
                });
                datosMetricas.combos.forEach(item => {
                    totalCombos += item.cantidad * item.precio;
                });

                totalAcumulado = totalEntradas + totalCombos;

                html += `
                    <h3>Recaudación total</h3>
                    <ul>
                        <li>Entradas: <strong>$${fmt(totalEntradas)}</strong></li>
                        <li>Combos: <strong>$${fmt(totalCombos)}</strong></li>
                    </ul>
                    <p class="total-metricas"><strong>Total general:</strong> $${fmt(totalAcumulado)}</p>
                `;
            } else {
                html = `<p class="placeholder">Seleccioná una métrica para ver los datos.</p>`;
            }

            resultadoMetricas.innerHTML = html;
        }

        selectorMetricas.addEventListener('change', async () => {
            if (!datosMetricas) {
                resultadoMetricas.innerHTML = '<p>Cargando datos...</p>';
                const datos = await obtenerMetricas();
                if (!datos) return;
            }
            mostrarMetricas(selectorMetricas.value);
        });

        if (btnActualizarMetricas) {
            btnActualizarMetricas.addEventListener('click', async () => {
                datosMetricas = null;
                resultadoMetricas.innerHTML = '<p>Cargando datos...</p>';
                const datos = await obtenerMetricas();
                if (!datos) return;
                mostrarMetricas(selectorMetricas.value);
            });
        }
    }

});
