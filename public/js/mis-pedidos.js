/* Mis pedidos: actualiza el estado de cada pedido sin recargar la página. */
const INTERVALO_MS = 15000;

const CLASES_BADGE = ['rose', 'amber', 'blue', 'indigo', 'emerald', 'green', 'slate'].map(c => 'badge-' + c);

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

    if (cambio) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', timer: 5000, timerProgressBar: true,
            showConfirmButton: false, title: `Pedido #${id}: ${e.texto}` });
    }
}

async function sondearEstados() {
    if (document.hidden) return;
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
    setInterval(sondearEstados, INTERVALO_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) sondearEstados(); });
});