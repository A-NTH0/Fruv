/* Mis pedidos: estado, pago, cancelación y abonos; se actualiza sin recargar la página. */
const INTERVALO_MS = 15000;

const CLASES_BADGE = ['rose', 'amber', 'blue', 'indigo', 'emerald', 'green', 'slate', 'red'].map(c => 'badge-' + c);

function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function dinero(n) {
    return '$' + (parseFloat(n) || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toast(icon, title) {
    Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 3500, timerProgressBar: true });
}

async function postForm(ruta, datos) {
    const res = await fetch(BASE_URL + ruta, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(datos),
    });
    return res.json();
}

/* ───────── Pago y acciones de cada pedido ───────── */
function renderPagoYAcciones(card) {
    let info;
    try { info = JSON.parse(card.dataset.info || '{}'); } catch (e) { info = {}; }

    const pago      = info.pago || {};
    const cancelado = card.dataset.clave === 'cancelado';
    const id        = card.dataset.id;

    // ── Bloque de pago ──
    let html = `<div class="pago-linea"><span>Método de pago</span><strong>${escHtml(pago.modalidad_texto || '')}</strong></div>`;

    if (pago.es_credito) {
        html += `<div class="pago-linea"><span>Total del pedido</span><strong>${dinero(pago.total)}</strong></div>`;
        html += `<div class="pago-linea"><span>Pago inicial</span><strong>${dinero(pago.pago_inicial)}</strong></div>`;
        if (parseFloat(pago.abonos_total) > 0) {
            html += `<div class="pago-linea"><span>Abonos registrados</span><strong>${dinero(pago.abonos_total)}</strong></div>`;
        }
        html += `<div class="pago-linea"><span>Total pagado</span><strong>${dinero(pago.pagado)}</strong></div>`;
        html += `<div class="pago-linea saldo"><span>Saldo pendiente</span><strong>${dinero(pago.saldo)}</strong></div>`;
    }
    if (!cancelado && pago.estado_pago) {
        const cls = (pago.es_credito && !pago.liquidado) ? 'badge-amber' : 'badge-green';
        html += `<div class="pago-estado"><span class="badge ${cls}">${escHtml(pago.estado_pago)}</span></div>`;
    }
    card.querySelector('.js-pago').innerHTML = html;

    // ── Acciones: abonar (solo crédito con saldo) y cancelar (solo Pendiente / Aceptado) ──
    let acciones = '';
    if (!cancelado && pago.puede_abonar) {
        acciones += `<span class="abono-form">
            <input type="number" id="abono-${id}" min="0.01" max="${pago.saldo}" step="0.01" placeholder="Monto a abonar" aria-label="Monto a abonar al pedido ${id}">
            <button type="button" class="btn-abonar" onclick="abonarPedido(${id})">Registrar abono</button>
        </span>`;
    }
    if (!cancelado && info.puede_cancelar) {
        acciones += `<button type="button" class="btn-cancelar" onclick="cancelarPedido(${id})">Cancelar pedido</button>`;
    }
    card.querySelector('.js-acciones').innerHTML = acciones;
}

function actualizarInfo(card, e) {
    const nueva = JSON.stringify({ pago: e.pago, puede_cancelar: e.puede_cancelar });
    // Solo se vuelve a dibujar si algo cambió (así no se borra lo que el cliente esté escribiendo).
    if (card.dataset.info !== nueva || card.dataset.dibujado !== card.dataset.clave) {
        card.dataset.info = nueva;
        card.dataset.dibujado = card.dataset.clave;
        renderPagoYAcciones(card);
    }
}

async function cancelarPedido(id) {
    const r = await Swal.fire({
        title: '¿Cancelar este pedido?',
        text: 'Solo puedes cancelarlo antes de que salga en camino. Esta acción no se puede deshacer.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Sí, cancelar pedido', cancelButtonText: 'No, conservarlo', confirmButtonColor: '#b91c1c',
    });
    if (!r.isConfirmed) return;

    try {
        const data = await postForm('mis-pedidos/cancelar', { id_pedido: id });
        if (data.success) toast('success', data.message);
        else Swal.fire({ icon: 'warning', title: 'No se pudo cancelar', text: data.message, confirmButtonColor: '#e11d48' });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Inténtalo de nuevo.', confirmButtonColor: '#e11d48' });
    }
    sondearEstados(true);   // refleja "Pedido Cancelado" (o el estado real) al instante
}

async function abonarPedido(id) {
    const input = document.getElementById('abono-' + id);
    const monto = parseFloat(input ? input.value : '');
    if (!monto || monto <= 0) { toast('warning', 'Ingresa un monto válido'); return; }

    try {
        const data = await postForm('mis-pedidos/abonar', { id_pedido: id, monto });
        if (data.success) toast('success', data.message);
        else Swal.fire({ icon: 'warning', title: 'No se pudo registrar el abono', text: data.message, confirmButtonColor: '#e11d48' });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Inténtalo de nuevo.', confirmButtonColor: '#e11d48' });
    }
    sondearEstados(true);   // el saldo se actualiza al instante
}

/* ───────── Total y productos (RF-12: la tienda puede ajustar precios, p. ej. precio preferencial) ───────── */
function cantidadTexto(n) {
    return String(parseFloat(n) || 0);
}

/** Actualiza el total y la lista de productos. Devuelve true si el total cambió. */
function actualizarMontos(card, e) {
    if (e.total === undefined) return false;

    let cambioTotal = false;
    const nuevo    = (parseFloat(e.total) || 0).toFixed(2);
    const anterior = card.dataset.total;

    if (anterior !== nuevo) {
        const totalEl = card.querySelector('.js-total');
        if (totalEl) totalEl.textContent = dinero(nuevo);
        card.dataset.total = nuevo;
        cambioTotal = anterior !== undefined && anterior !== '';
    }

    const lista  = card.querySelector('.js-prods-lista');
    const cuenta = card.querySelector('.js-prods-count');
    if (lista && Array.isArray(e.productos)) {
        const firma = JSON.stringify(e.productos);
        if (card.dataset.prods !== firma) {
            card.dataset.prods = firma;
            lista.innerHTML = e.productos.map(p =>
                `<li><span>${escHtml(p.nombre)} <small>× ${cantidadTexto(p.cantidad)} ${escHtml(p.unidad)}</small></span><strong>${dinero(p.total)}</strong></li>`
            ).join('');
            if (cuenta) cuenta.textContent = e.productos.length;
        }
    }

    return cambioTotal;
}

/* ───────── Estado ───────── */
function aplicarEstado(id, e) {
    const card = document.querySelector(`.pedido-card[data-id="${id}"]`);
    if (!card) return;

    const cambio = card.dataset.clave !== e.clave;
    card.dataset.clave = e.clave;
    card.classList.toggle('is-cancelado', e.clave === 'cancelado');

    const badge = card.querySelector('.js-badge');
    badge.classList.remove(...CLASES_BADGE);
    badge.classList.add('badge-' + e.color);
    badge.textContent = e.texto;

    card.querySelectorAll('.step').forEach(li => {
        const n = parseInt(li.dataset.n, 10);
        li.classList.toggle('is-done', n < e.paso);
        li.classList.toggle('is-current', n === e.paso);
    });

    card.querySelector('.js-detalle').textContent = e.detalle || '';

    const rep = card.querySelector('.js-repartidor');
    if (rep) {
        rep.style.display = e.repartidor ? '' : 'none';
        const strong = rep.querySelector('strong');
        if (strong) strong.textContent = e.repartidor || '';
    }

    const act = card.querySelector('.js-actualizado');
    if (act) act.textContent = e.actualizado ? 'Actualizado: ' + e.actualizado : '';

    actualizarInfo(card, e);
    const cambioTotal = actualizarMontos(card, e);

    if (cambio) {
        Swal.fire({ toast: true, position: 'top-end', icon: e.clave === 'cancelado' ? 'warning' : 'info', timer: 5000, timerProgressBar: true,
            showConfirmButton: false, title: `Pedido #${id}: ${e.texto}` });
    } else if (cambioTotal) {
        toast('info', `Pedido #${id}: la tienda actualizó los precios. Nuevo total: ${dinero(e.total)}`);
    }
}

async function sondearEstados(forzar = false) {
    if (document.hidden && forzar !== true) return;
    const ids = [...document.querySelectorAll('.pedido-card')].map(c => c.dataset.id);
    if (!ids.length) return;

    try {
        const res = await fetch(`${BASE_URL}mis-pedidos/estados?ids=${ids.join(',')}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) Object.entries(data.estados).forEach(([id, e]) => aplicarEstado(id, e));
    } catch (e) { /* sin conexión: se reintenta en el siguiente ciclo */ }
}

document.addEventListener('DOMContentLoaded', () => {
    // Primer dibujo del pago y las acciones con los datos que entregó el servidor.
    document.querySelectorAll('.pedido-card').forEach(card => {
        card.dataset.dibujado = card.dataset.clave;
        renderPagoYAcciones(card);
    });

    setInterval(sondearEstados, INTERVALO_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) sondearEstados(); });
});