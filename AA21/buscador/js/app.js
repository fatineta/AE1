document.addEventListener('DOMContentLoaded', () => {
    const formBuscar  = document.querySelector('#form-buscar');
    const inputBuscar = document.querySelector('#input-buscar');
    const contenedor  = document.querySelector('#resultados');

    formBuscar.addEventListener('submit', async (e) => {
        e.preventDefault(); // Previene la recarga del navegador
        
        const query = inputBuscar.value.trim();
        contenedor.textContent = "Buscando en la base de datos...";

        try {
            // Petición GET con parámetro Query String en la URL
            const res = await fetch(`api/buscador-vulnerable.php?q=${encodeURIComponent(query)}`);
            const resultado = await res.json();

            if (res.ok && resultado['estado'] === 'exito') {
                renderizarProductos(resultado['data']);
            } else {
                contenedor.textContent = "No se encontraron productos.";
            }
        } catch (error) {
            contenedor.textContent = "Error de conexión con el servidor.";
        }
    });

    function renderizarProductos(productos) {
        contenedor.innerHTML = ''; // Limpiamos el contenedor
        
        if (productos.length === 0) {
            contenedor.textContent = "Sin resultados.";
            return;
        }

        const fragmento = document.createDocumentFragment();
        productos.forEach(prod => {
            const card = document.createElement('div');
            card.className = 'card-producto';
            
            // Renderizado seguro en el DOM con textContent
            const titulo = document.createElement('h4');
            titulo.textContent = prod['nombre'];

            const precio = document.createElement('p');
            precio.textContent = `$${prod['precio']}`;

            card.appendChild(titulo);
            card.appendChild(precio);
            fragmento.appendChild(card);
        });

        contenedor.appendChild(fragmento);
    }
});