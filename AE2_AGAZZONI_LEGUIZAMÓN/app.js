//primero el precio de los asientos
const PRECIO_ASIENTO= 4800
let descuentoCupon=0;  
// creamos la variable para guradar el catalog
let catalogoCombos= [];
 // funcion asincrona
document.addEventListener('DOMContentLoaded', () => {
    obtenerCombos();
    inicializarEventos(); // Se agregan los listeners para cupon, confirmar y omitir
});
async function obtenerCombos(){
    try{
        const respuesta= await fetch('data/combos.json'); // peticion http asincrona
        catalogoCombos= await respuesta.json();
        renderizarCombos(catalogoCombos);
    } catch(error){
              console.error("Error al cargar el archivo json de combos", error);
    }      
}



function renderizarCombos(lista) {
    const contenedor = document.getElementById('lista-combos');
    if (!contenedor) return;
    contenedor.innerHTML = '';


    lista.forEach(combo => {
        const tarjeta = document.createElement('div');
        tarjeta.classList.add('product-combo');
        tarjeta.setAttribute('data-name', combo.titulo);
        tarjeta.setAttribute('data-price', combo.precio);

        tarjeta.innerHTML = `
        <div class="product-info">
                <img 
                    src="${combo.imagen}" 
                    alt="${combo.titulo}" 
                    style="width: 50px !important; height: 50px !important; max-width: 50px !important; max-height: 50px !important; object-fit: contain; flex-shrink: 0;"
                >
                <div style="flex: 1;">
                    <h3 style="margin: 0; font-size: 1rem; color: #000;">${combo.titulo}</h3>
                    <p style="margin: 2px 0; font-size: 0.75rem; color: #444; text-transform: uppercase;">${combo.descripcion}</p>
                    <span class="price">$ ${combo.precio.toLocaleString('es-AR')}</span>
                </div>
            </div>
            <div class="quantity-controls">
                <input 
                    type="number" 
                    class="quantity-input" 
                    data-id="${combo.id}" 
                    data-precio="${combo.precio}" 
                    value="0" 
                    min="0"
                >
            </div>
        `;
        
        contenedor.appendChild(tarjeta);
    });

    asociarEventosContenedores();
    calcularTotal();
}


function asociarEventosContenedores(){
    const inputCantidad= document.querySelectorAll('.quantity-input');
    // asignamos addEventListener a cada input generado

    inputCantidad.forEach(input => {
        input.addEventListener('change', calcularTotal);
        input.addEventListener('input', calcularTotal);
  });
}


function inicializarEventos() {
    const btnCupon = document.getElementById('btn-cupon');
    if (btnCupon) btnCupon.addEventListener('click', validarCupon);

  const btnConfirmar = document.getElementById('btn-confirmar');
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', () => irAPagar(true));
    }


    const btnOmitir = document.getElementById('btn-omitir');
    if (btnOmitir) {
        btnOmitir.addEventListener('click', () => irAPagar(false));
    }
}




function getCantidadAsientos() {
    const params = new URLSearchParams(window.location.search);
    const asientosParam = params.get('asientos');
  
// Si viene un numero simple 
if (!asientosParam) return 0;
    return asientosParam.split(',').filter(a => a.trim() !== '').length;

    // Si vienen codigos separados por coma 
    return asientosParam.split(',').filter(a => a.trim() !== '').length;
}
function validarCupon() {
    const cuponInput = document.getElementById('cupon-input');
    const mensajeEl = document.getElementById('mensaje-cupon');
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

    calcularTotal();
}

function calcularTotal() {
    const cantidadAsientos = getCantidadAsientos();
    const totalAsientos = cantidadAsientos * PRECIO_ASIENTO;

    let totalCombos = 0;
    document.querySelectorAll('.product-combo').forEach(combo => {
        const precio = parseInt(combo.getAttribute('data-price')) || 0;
        const input = combo.querySelector('.quantity-input');
        const cantidad = input ? (parseInt(input.value) || 0) : 0;
        totalCombos += precio * cantidad;
    });

    let total = totalAsientos + totalCombos;

    if (descuentoCupon > 0) {
        total = Math.round(total - total * descuentoCupon);
    }

    const totalPriceEl = document.getElementById('total-price');
    if (totalPriceEl) {
        totalPriceEl.textContent = '$' + total.toLocaleString('es-AR');
    }
}

function irAPagar(incluirCombos = true) {
    const params = new URLSearchParams(window.location.search);

    if (incluirCombos) {
        const combosElegidos = [];
        document.querySelectorAll('.product-combo').forEach(combo => {
            const input = combo.querySelector('.quantity-input');
            const cant = input ? (parseInt(input.value) || 0) : 0;
            if (cant > 0) {
                const nombre = combo.getAttribute('data-name');
                combosElegidos.push(`${cant}x ${nombre}`);
            }
        });
        if (combosElegidos.length > 0) {
            params.set('combos', combosElegidos.join(' | '));
        }else {
            params.delete('combos'); // Si no sumó combos, limpiamos la variable
        }
    } else {
        params.delete('combos'); // Omitió los combos
    }

    const totalPriceEl = document.getElementById('total-price');
    if (totalPriceEl) {
        const totalTexto = totalPriceEl.textContent.replace(/[^\d]/g, '');
        params.set('total', totalTexto);    }

    window.location.href = 'comprar.html?' + params.toString();
}