/**
 * Lógica interactiva y sondeo en tiempo real del Portal de Repartidor (Funcionalidades 7 y 8)
 */

let vistaActual     = (window.REPARTIDOR_INICIAL && REPARTIDOR_INICIAL.vistaInicial) || 'asignados';
let firmaActual     = (window.REPARTIDOR_INICIAL && REPARTIDOR_INICIAL.firmaEstado)  || '';
let DATA_ASIGNADOS   = (window.REPARTIDOR_INICIAL && REPARTIDOR_INICIAL.asignados)   || [];
let DATA_DISPONIBLES = (window.REPARTIDOR_INICIAL && REPARTIDOR_INICIAL.disponibles) || [];
let DATA_ENTREGADOS  = (window.REPARTIDOR_INICIAL && REPARTIDOR_INICIAL.entregados)  || [];

function formatoMoneda(valor) {
    return '$' + Number(valor || 0).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function escaparHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function armarLinkWhatsApp(p) {
    if (!p.cliente_telefono) return null;
    const tel = p.cliente_telefono.length === 10 ? '52' + p.cliente_telefono : p.cliente_telefono;
    let lineasProd = '';
    (p.productos || []).forEach(pr => {
        lineasProd += `• ${pr.cantidad} ${pr.unidad_venta} de ${pr.nombre}: ${formatoMoneda(pr.total)}\n`;
    });

    const estadoTxt = p.estado === 'en_transito'
        ? 'va EN CAMINO a tu domicilio 🛵'
        : (p.estado === 'entregado' ? 'ha sido ENTREGADO ✅' : 'está CONFIRMADO y preparándose 📦');

    const cobroTxt = p.modalidad_pago === 'credito'
        ? `A crédito (Saldo pendiente: ${formatoMoneda(p.deuda_restante)})`
        : `De contado (${formatoMoneda(p.total)})`;

    const msg = `¡Hola ${p.cliente_nombre}! 🍎\n`
              + `Tu pedido #${p.id_pedido} de *FRUVER* ${estadoTxt}.\n\n`
              + `*Resumen de tu ticket:*\n${lineasProd}`
              + `*Total:* ${formatoMoneda(p.total)}\n`
              + `*Modalidad:* ${cobroTxt}\n`
              + `*Dirección:* ${p.direccion}\n\n`
              + `¡Gracias por comprar en Fruver!`;

    return `https://wa.me/${tel}?text=${encodeURIComponent(msg)}`;
}

function renderStepper(estado) {
    const pasos = [
        { key: 'pedido',      label: 'Recibido' },
        { key: 'confirmado',  label: 'Confirmado' },
        { key: 'en_transito', label: 'En camino' },
        { key: 'entregado',   label: 'Entregado' }
    ];
    const orden = ['pedido', 'confirmado', 'en_transito', 'entregado'];
    const idxActual = orden.indexOf(estado);

    return `<ol class="stepper" aria-label="Progreso del pedido">` + pasos.map((p, idx) => {
        let cls = 'step';
        if (idx < idxActual) cls += ' is-done';
        else if (idx === idxActual) cls += ' is-current';
        return `<li class="${cls}"><span class="step-dot"></span>${p.label}</li>`;
    }).join('') + `</ol>`;
}

function crearTarjetaPedido(p, tipoSeccion) {
    const badgeEstado = {
        'pedido':      '<span class="badge badge-amber">Pendiente aceptar</span>',
        'confirmado':  '<span class="badge badge-blue">Listo para salir</span>',
        'en_transito': '<span class="badge badge-indigo">En tránsito</span>',
        'entregado':   '<span class="badge badge-emerald">Entregado</span>'
    }[p.estado] || `<span class="badge badge-slate">${escaparHtml(p.estado)}</span>`;

    const badgePago = p.modalidad_pago === 'credito'
        ? `<span class="badge badge-amber">A Crédito · Saldo: ${formatoMoneda(p.deuda_restante)}</span>`
        : `<span class="badge badge-green">De Contado · ${formatoMoneda(p.total)}</span>`;

    const linkMaps = p.tiene_mapa
        ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(p.direccion)}`
        : null;
    const linkWA = armarLinkWhatsApp(p);

    const prodsHtml = (p.productos || []).map(pr => `
        <li>
            <span><strong>${pr.cantidad} ${escaparHtml(pr.unidad_venta)}</strong> · ${escaparHtml(pr.nombre)}</span>
            <strong>${formatoMoneda(pr.total)}</strong>
        </li>
    `).join('');

    let botonPrimario = '';
    if (tipoSeccion === 'asignados') {
        if (p.estado === 'confirmado') {
            botonPrimario = `
                <button type="button" onclick="cambiarEstadoPedido(${p.id_pedido}, 'en_transito')"
                        class="rep-action-btn bg-rose-600 hover:bg-rose-700 text-white shadow-sm">
                    <i data-lucide="truck" class="w-4 h-4"></i> Iniciar Ruta (En camino)
                </button>`;
        } else if (p.estado === 'en_transito') {
            botonPrimario = `
                <button type="button" onclick="cambiarEstadoPedido(${p.id_pedido}, 'entregado')"
                        class="rep-action-btn bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Confirmar Entrega
                </button>`;
        }
    } else if (tipoSeccion === 'disponibles') {
        if (p.estado === 'confirmado') {
            botonPrimario = `
                <button type="button" onclick="tomarPedidoDisponible(${p.id_pedido})"
                        class="rep-action-btn bg-rose-600 hover:bg-rose-700 text-white shadow-sm">
                    <i data-lucide="hand" class="w-4 h-4"></i> Tomar Pedido para mi Ruta
                </button>`;
        } else {
            botonPrimario = `
                <span class="rep-action-btn bg-amber-50 text-amber-800 border border-amber-200 cursor-default">
                    <i data-lucide="clock" class="w-4 h-4"></i> Esperando confirmación de Admin
                </span>`;
        }
    }

    return `
    <article class="pedido-card shadow-sm">
        <div class="pedido-top">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2>Pedido #${p.id_pedido}</h2>
                    ${badgeEstado}
                    ${badgePago}
                </div>
                <p class="mt-1">Cliente: <strong class="text-gray-800">${escaparHtml(p.cliente_nombre)}</strong> · Registrado: ${escaparHtml(p.fecha)}</p>
            </div>
            <div class="pedido-right">
                <div class="text-right">
                    <span class="block text-xs text-gray-500 font-semibold">Total del pedido</span>
                    <span class="pedido-total">${formatoMoneda(p.total)}</span>
                </div>
            </div>
        </div>

        ${renderStepper(p.estado)}

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3.5 border border-slate-200/80 text-sm">
            <div class="flex items-start gap-2.5">
                <i data-lucide="map-pin" class="w-4 h-4 text-rose-600 mt-0.5 shrink-0"></i>
                <div>
                    <span class="text-xs font-bold uppercase text-gray-500 block">Dirección de entrega</span>
                    <span class="font-semibold text-gray-800">${escaparHtml(p.direccion)}</span>
                </div>
            </div>
            <div class="flex items-start gap-2.5">
                <i data-lucide="calendar-clock" class="w-4 h-4 text-rose-600 mt-0.5 shrink-0"></i>
                <div>
                    <span class="text-xs font-bold uppercase text-gray-500 block">Programación y Cobro</span>
                    <span class="text-gray-800">
                        ${p.fecha_entrega_programada ? 'Fecha: <strong>' + escaparHtml(p.fecha_entrega_programada) + '</strong>' : 'Entrega inmediata'}
                        ${p.horario_entrega ? ' · Hora: <strong>' + escaparHtml(p.horario_entrega) + '</strong>' : ''}
                    </span>
                    <span class="block text-xs font-bold mt-0.5 ${p.modalidad_pago === 'credito' ? 'text-amber-700' : 'text-emerald-700'}">
                        ${p.modalidad_pago === 'credito'
                            ? `Enganche: ${formatoMoneda(p.pago_inicial)} · Saldo pendiente: ${formatoMoneda(p.deuda_restante)}`
                            : `Cobrar al entregar: ${formatoMoneda(p.total)}`}
                    </span>
                </div>
            </div>
        </div>

        <details class="pedido-prods">
            <summary>Ver productos del pedido (${(p.productos || []).length})</summary>
            <ul>${prodsHtml || '<li><span>Sin desglose de productos</span></li>'}</ul>
        </details>

        <div class="mt-4 pt-3 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 rep-actions-grid">
            ${botonPrimario}

            ${linkWA ? `
                <a href="${linkWA}" target="_blank" rel="noopener"
                   class="rep-action-btn bg-green-600 hover:bg-green-700 text-white">
                    <i data-lucide="message-circle" class="w-4 h-4"></i> Enviar Ticket WhatsApp
                </a>
            ` : ''}

            ${linkMaps ? `
                <a href="${linkMaps}" target="_blank" rel="noopener"
                   class="rep-action-btn bg-white hover:bg-gray-50 text-gray-800 border border-gray-300">
                    <i data-lucide="map" class="w-4 h-4 text-rose-600"></i> Ver en Mapa
                </a>
            ` : ''}

            ${p.cliente_telefono ? `
                <a href="tel:${escaparHtml(p.cliente_telefono)}"
                   class="rep-action-btn bg-white hover:bg-gray-50 text-gray-800 border border-gray-300">
                    <i data-lucide="phone-call" class="w-4 h-4 text-emerald-600"></i> Llamar (${escaparHtml(p.cliente_telefono)})
                </a>
            ` : ''}
        </div>
    </article>`;
}

function filtrarLista(lista, texto) {
    if (!texto) return lista;
    const q = texto.toLowerCase();
    return lista.filter(p =>
        String(p.id_pedido).includes(q) ||
        (p.cliente_nombre || '').toLowerCase().includes(q) ||
        (p.direccion || '').toLowerCase().includes(q)
    );
}

function renderizarVistas() {
    const q = (document.getElementById('inputFiltroRep')?.value || '').trim();

    const asigFiltrados = filtrarLista(DATA_ASIGNADOS, q);
    const dispFiltrados = filtrarLista(DATA_DISPONIBLES, q);
    const histFiltrados = filtrarLista(DATA_ENTREGADOS, q);

    const secAsignados   = document.getElementById('sec-asignados');
    const secDisponibles = document.getElementById('sec-disponibles');
    const secHistorial   = document.getElementById('sec-historial');

    if (secAsignados) {
        secAsignados.innerHTML = asigFiltrados.length
            ? asigFiltrados.map(p => crearTarjetaPedido(p, 'asignados')).join('')
            : `<div class="empty-box"><p>No tienes entregas activas en este momento.</p><button type="button" onclick="cambiarVistaRep('disponibles')" class="btn-ghost">Ver nuevos pedidos disponibles</button></div>`;
    }

    if (secDisponibles) {
        secDisponibles.innerHTML = dispFiltrados.length
            ? dispFiltrados.map(p => crearTarjetaPedido(p, 'disponibles')).join('')
            : `<div class="empty-box"><p>No hay pedidos nuevos sin repartidor en la cola del rol.</p></div>`;
    }

    if (secHistorial) {
        secHistorial.innerHTML = histFiltrados.length
            ? histFiltrados.map(p => crearTarjetaPedido(p, 'historial')).join('')
            : `<div class="empty-box"><p>Aún no tienes entregas registradas en el historial.</p></div>`;
    }

    if (window.lucide) window.lucide.createIcons();
}

function actualizarBadgesMenu(asigCount, dispCount) {
    const bAsig = document.getElementById('nav-badge-asignados');
    const bDisp = document.getElementById('nav-badge-disponibles');
    if (bAsig) {
        bAsig.textContent = asigCount;
        bAsig.style.display = asigCount > 0 ? '' : 'none';
    }
    if (bDisp) {
        bDisp.textContent = dispCount;
        bDisp.style.display = dispCount > 0 ? '' : 'none';
    }
}

function cambiarVistaRep(vista) {
    vistaActual = vista;
    ['asignados', 'disponibles', 'historial', 'perfil'].forEach(v => {
        document.getElementById('sec-' + v)?.classList.toggle('hidden', v !== vista);
        document.getElementById('nav-rep-' + v)?.classList.toggle('active', v === vista);
    });
    document.getElementById('buscador-wrapper')?.classList.toggle('hidden', vista === 'perfil');
}

async function cambiarEstadoPedido(idPedido, nuevoEstado) {
    const esEntrega = nuevoEstado === 'entregado';
    const confirm = await Swal.fire({
        title: esEntrega ? `¿Confirmar entrega del Pedido #${idPedido}?` : `¿Iniciar ruta del Pedido #${idPedido}?`,
        text: esEntrega
            ? 'El pedido quedará marcado como Entregado al cliente con la hora actual.'
            : 'El cliente verá en su pantalla que su pedido ya va En Camino.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: esEntrega ? '#059669' : '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: esEntrega ? 'Sí, entregado' : 'Sí, iniciar ruta',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    });

    if (!confirm.isConfirmed) return;

    try {
        const res = await fetch(BASE_URL + 'repartidores/cambiar-estado', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id_pedido: idPedido, nuevo_estado: nuevoEstado })
        });
        const data = await res.json();

        if (data.ok) {
            Swal.fire({ icon: 'success', title: data.mensaje, timer: 1600, showConfirmButton: false });
            await sincronizarPedidos(false);
        } else {
            Swal.fire('Atención', data.mensaje || 'No se pudo actualizar el pedido.', 'warning');
        }
    } catch (e) {
        Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
    }
}

async function tomarPedidoDisponible(idPedido) {
    try {
        const res = await fetch(BASE_URL + 'repartidores/tomar-pedido', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id_pedido: idPedido })
        });
        const data = await res.json();

        if (data.ok) {
            Swal.fire({ icon: 'success', title: data.mensaje, timer: 1600, showConfirmButton: false });
            await sincronizarPedidos(false);
            cambiarVistaRep('asignados');
        } else {
            Swal.fire('Atención', data.mensaje || 'No se pudo asignar el pedido.', 'warning');
        }
    } catch (e) {
        Swal.fire('Error', 'Error de conexión.', 'error');
    }
}

async function sincronizarPedidos(mostrarToastManual = false) {
    try {
        const res = await fetch(BASE_URL + 'repartidores/actualizaciones', {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.ok) return;

        const habiaCambio = firmaActual && data.firma !== firmaActual;
        firmaActual = data.firma;

        DATA_ASIGNADOS   = data.asignados || [];
        DATA_DISPONIBLES = data.disponibles || [];
        DATA_ENTREGADOS  = data.entregados || [];

        document.getElementById('kpi-activos').textContent     = data.stats.activos;
        document.getElementById('kpi-disponibles').textContent = data.stats.disponibles;
        document.getElementById('kpi-entregados').textContent  = data.stats.entregados;
        document.getElementById('kpi-monto').textContent       = formatoMoneda(data.stats.monto_ruta);

        document.getElementById('sync-hora').textContent         = data.timestamp;

        actualizarBadgesMenu(DATA_ASIGNADOS.length, DATA_DISPONIBLES.length);
        renderizarVistas();

        if (habiaCambio && !mostrarToastManual) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: '¡Nuevos pedidos o cambios de estado detectados!',
                showConfirmButton: false,
                timer: 3000
            });
        } else if (mostrarToastManual) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Panel sincronizado al día',
                showConfirmButton: false,
                timer: 1500
            });
        }
    } catch (e) {
        console.error('Error al sincronizar entregas:', e);
    }
}

window.cambiarVistaRep       = cambiarVistaRep;
window.renderizarVistas      = renderizarVistas;
window.sincronizarPedidos    = sincronizarPedidos;
window.cambiarEstadoPedido   = cambiarEstadoPedido;
window.tomarPedidoDisponible = tomarPedidoDisponible;

document.addEventListener('DOMContentLoaded', () => {
    actualizarBadgesMenu(DATA_ASIGNADOS.length, DATA_DISPONIBLES.length);
    renderizarVistas();
    cambiarVistaRep(vistaActual);
    // Sondeo ligero cada 8 segundos (Funcionalidad 8 y RNF-08)
    setInterval(() => sincronizarPedidos(false), 8000);
});