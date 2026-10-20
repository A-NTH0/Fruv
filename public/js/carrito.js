/* Carrito del cliente: se usa en el catálogo (agregar) y en /carrito (gestionar y confirmar). */

function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function dinero(n) {
    return '$' + (parseFloat(n) || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function postCarrito(ruta, datos) {
    const res = await fetch(BASE_URL + ruta, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(datos),
    });
    return res.json();
}

function toast(icon, title) {
    Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2200, timerProgressBar: true });
}

function actualizarBadge(n) {
    const b = document.getElementById('badge-carrito');
    if (!b) return;
    b.textContent = n;
    b.style.display = n > 0 ? '' : 'none';
}

/* ───────── Catálogo ───────── */
async function agregarAlCarrito(id) {
    const input = document.getElementById('cant-' + id);
    const cantidad = parseFloat(input ? input.value : 1);
    if (!cantidad || cantidad <= 0) { toast('warning', 'Indica una cantidad válida'); return; }

    try {
        const data = await postCarrito('carrito/agregar', { id_producto: id, cantidad });
        if (data.success) {
            actualizarBadge(data.cuenta);
            toast('success', data.message);
        } else {
            Swal.fire({ icon: 'warning', title: 'No se pudo agregar', text: data.message, confirmButtonColor: '#e11d48' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Inténtalo de nuevo.', confirmButtonColor: '#e11d48' });
    }
}

/* ───────── Página del carrito ───────── */
function filaCarrito(i) {
    const paso = i.unidad === 'kilos' ? 0.5 : 1;
    const img = BASE_URL + 'uploads/' + encodeURIComponent(i.imagen || 'default.jpg');
    return `
    <article class="cart-item ${i.ok ? '' : 'cart-item--error'}" data-id="${i.id}">
        <img src="${img}" alt="${escHtml(i.nombre)}" onerror="this.src='https://placehold.co/144x144?text=Fruta'">
        <div>
            <h3>${escHtml(i.nombre)}</h3>
            <div class="price">${dinero(i.precio)} / ${escHtml(i.unidad)}</div>
            ${i.ok ? '' : `<span class="warn">${escHtml(i.motivo || 'No disponible')}</span>`}
        </div>
        <div class="qty">
            <button type="button" onclick="cambiarCantidad(${i.id}, ${i.cantidad - paso})" aria-label="Quitar uno">−</button>
            <input type="number" value="${i.cantidad}" min="${paso}" step="${paso}"
                   onchange="cambiarCantidad(${i.id}, this.value)" aria-label="Cantidad de ${escHtml(i.nombre)}">
            <button type="button" onclick="cambiarCantidad(${i.id}, ${i.cantidad + paso})" aria-label="Agregar uno">+</button>
        </div>
        <div class="sub">${dinero(i.subtotal)}<button type="button" class="btn-x" onclick="quitarProducto(${i.id})" title="Quitar">✕</button></div>
    </article>`;
}

function renderCarrito(data) {
    const lista = document.getElementById('carrito-lista');
    if (!lista) return;

    const vacio = !data.items.length;
    lista.innerHTML = vacio
        ? `<div class="empty-box"><p>Tu carrito está vacío.</p><a href="${BASE_URL}productos" class="btn-confirm" style="display:inline-block;width:auto;padding:.7rem 1.4rem">Ver catálogo</a></div>`
        : data.items.map(filaCarrito).join('');

    document.getElementById('sum-cuenta').textContent = data.cuenta;
    document.getElementById('sum-total').textContent = dinero(data.total);
    document.getElementById('btn-confirmar').disabled = vacio || !data.todo_ok;
    document.getElementById('btn-vaciar').style.display = vacio ? 'none' : '';
    document.getElementById('carrito-resumen').style.display = vacio ? 'none' : '';
    actualizarBadge(data.cuenta);
}

async function cambiarCantidad(id, cantidad) {
    cantidad = parseFloat(cantidad);
    try {
        const data = await postCarrito('carrito/actualizar', { id_producto: id, cantidad: isNaN(cantidad) ? 0 : cantidad });
        if (data.success) renderCarrito(data);
        else { toast('warning', data.message); recargarResumen(); }
    } catch (e) { toast('error', 'Error de conexión'); }
}

async function quitarProducto(id) {
    try {
        const data = await postCarrito('carrito/quitar', { id_producto: id });
        if (data.success) renderCarrito(data);
    } catch (e) { toast('error', 'Error de conexión'); }
}

async function vaciarCarrito() {
    const r = await Swal.fire({ title: '¿Vaciar el carrito?', icon: 'question', showCancelButton: true,
        confirmButtonText: 'Sí, vaciar', cancelButtonText: 'Cancelar', confirmButtonColor: '#e11d48' });
    if (!r.isConfirmed) return;
    const data = await postCarrito('carrito/vaciar', {});
    if (data.success) renderCarrito(data);
}

async function recargarResumen() {
    const res = await fetch(BASE_URL + 'carrito/resumen', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.success) renderCarrito(data);
}

async function confirmarPedido() {
    const r = await Swal.fire({
        title: '¿Enviar tu pedido?',
        text: 'La tienda lo recibirá como Pendiente y lo aceptará cuando confirme la disponibilidad.',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Sí, enviar pedido', cancelButtonText: 'Revisar', confirmButtonColor: '#e11d48',
    });
    if (!r.isConfirmed) return;

    Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const data = await postCarrito('carrito/confirmar', {
            fecha_entrega: document.getElementById('fecha_entrega').value,
            horario: document.getElementById('horario').value,
        });
        Swal.close();
        if (data.success) {
            await Swal.fire({ icon: 'success', title: '¡Pedido enviado!', text: data.message, confirmButtonColor: '#e11d48' });
            window.location.href = data.redirect;
        } else {
            Swal.fire({ icon: 'warning', title: 'No se pudo enviar', text: data.message, confirmButtonColor: '#e11d48' });
            recargarResumen();
        }
    } catch (e) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Error de conexión', confirmButtonColor: '#e11d48' });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof CARRITO_INICIAL !== 'undefined') renderCarrito(CARRITO_INICIAL);
});