const asientos = document.querySelectorAll('.asiento.libre');

asientos.forEach(asiento => {
  asiento.addEventListener('click', () => {  
    asiento.classList.toggle('seleccionado'); 
  });
});

const botonConfirmar = document.getElementById('confirmar-btn');

botonConfirmar.addEventListener('click', () => {
  const asientosSeleccionados = document.querySelectorAll('.asiento.seleccionado');
  if (asientosSeleccionados.length === 0) {
    alert('Por favor, elegí al menos un asiento antes de continuar.');
  } else {
    window.location.href = 'comprar.html';
  }
});
