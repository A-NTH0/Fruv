/**
 * Flujo del administrador: Aceptar pedido / Pendiente / Asignar repartidor.
 * Se carga DESPUÉS de pedidos.js y reemplaza (por nombre global) las funciones:
 *   crearTarjetaPedido, confirmarAccion, actualizarColumnaKanban y filtrarTarjetasKanban.
 * El resto de pedidos.js (modales, edición, abonos, paginación) se mantiene igual.
 */

function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function fechaValida(f) { return !!f && !String(f).startsWith('0000'); }
function cantidadTxt(n) { return String(parseFloat(n)); }

async function postForm(ruta, datos) {
    const res = await fetch(BASE_URL + ruta, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(datos),
    });
    return res.json();
}

/* ───────── Tarjeta (única plantilla, también para el render inicial) ───────── */
function botonesTarjeta(p, estado, enEspera) {
    const id = p.id_pedido;
    if (estado === 'pedido') {
        return `<button class="btn-mini btn-ok order-primary-action" onclick="aceptarPedido(${id})"><i data-lucide="check"></i> Aceptar pedido</button>
                <button class="btn-mini btn-wait" onclick="ponerPendiente(${id}, ${enEspera ? 0 : 1})"><i data-lucide="${enEspera ? 'play' : 'pause'}"></i> ${enEspera ? 'Reactivar' : 'Poner en espera'}</button>
                <button class="btn-mini btn-cancel" onclick="confirmarAccion(${id},'cancelado')">Cancelar</button>`;
    }
    if (estado === 'confirmado') {
        return `<button class="btn-mini btn-rep" onclick="asignarRepartidor(${id})"><i data-lucide="user-round"></i> ${p.id_repartidor ? 'Cambiar repartidor' : 'Asignar repartidor'}</button>
                <button class="btn-mini btn-send order-primary-action" onclick="confirmarAccion(${id},'en_transito')"><i data-lucide="truck"></i> Iniciar entrega</button>
                <button class="btn-mini btn-cancel" onclick="confirmarAccion(${id},'cancelado')">Cancelar</button>`;
    }
    if (estado === 'en_transito') {
        const credito = p.modalidad_pago === 'credito';
        // Desde "En tránsito" ya NO se puede cancelar el pedido.
        return `<button class="btn-mini btn-ok order-primary-action" onclick="confirmarAccion(${id},'entregado')"><i data-lucide="package-check"></i> ${credito ? 'Confirmar entrega y cobro' : 'Confirmar entrega'}</button>`;
    }
    if (estado === 'credito' && parseFloat(p.deuda_restante) > 0) {
        return `<button class="btn-mini btn-abono order-primary-action" onclick="abrirModalAbono(${id},${p.deuda_restante})"><i data-lucide="circle-dollar-sign"></i> Registrar abono</button>`;
    }
    return '';
}

function crearTarjetaPedido(p, estado) {
    const id        = p.id_pedido;
    const enEspera  = String(p.en_espera) === '1' && estado === 'pedido';
    const productos = p.productos || [];
    const nombre    = `${p.cliente_nombre || ''} ${p.cliente_apellido || ''}`.trim();
    const repNombre = `${p.repartidor_nombre || ''} ${p.repartidor_apellido || ''}`.trim();

    const div = document.createElement('div');
    div.className = 'tarjeta-pedido';
    div.dataset.id           = id;
    div.dataset.cliente      = nombre.toLowerCase();
    div.dataset.productos    = productos.map(x => x.nombre_producto).join(' ').toLowerCase();
    div.dataset.repartidor   = repNombre.toLowerCase();
    div.dataset.repartidorId = p.id_repartidor || '';

    const estadoTitulos = {
        pedido: 'Pendiente', confirmado: 'Aceptado', en_transito: 'En tránsito',
        entregado: 'Entregado', credito: 'Saldo pendiente', pagado: 'Pagado', cancelado: 'Cancelado',
    };
    const tags = [];
    tags.push(p.origen === 'cliente'
        ? '<span class="tag tag-web">Pedido del cliente</span>'
        : '<span class="tag tag-admin">Registrado por tienda</span>');
    if (enEspera) tags.push('<span class="tag tag-espera">En espera</span>');
    tags.push(p.modalidad_pago === 'credito'
        ? '<span class="tag tag-credito">A Crédito</span>'
        : '<span class="tag tag-contado">De Contado</span>');
    if (p.modalidad_pago === 'credito' && estado !== 'cancelado' && (parseFloat(p.deuda_restante) || 0) <= 0.004) {
        tags.push('<span class="tag tag-pagado">Totalmente pagado</span>');
    }

    // A crédito: pago inicial, abonos posteriores y saldo restante (se ve en cualquier columna).
    let lineaPago = '';
    if (p.modalidad_pago === 'credito' && estado !== 'cancelado') {
        const total   = parseFloat(p.total) || 0;
        const inicial = parseFloat(p.pago_inicial) || 0;
        const saldo   = parseFloat(p.deuda_restante) || 0;
        const abonado = Math.max(0, total - inicial - saldo);
        lineaPago = `<div class="tp-line pago">Inicial: $${inicial.toFixed(2)}${abonado > 0.004 ? ' · Abonado: $' + abonado.toFixed(2) : ''}</div>
                     <div class="tp-line saldo">Saldo: $${saldo.toFixed(2)}</div>`;
    }

    const productosHtml = productos.slice(0, 3)
        .map(x => `<li><span>${escHtml(x.nombre_producto)}</span><strong>×${cantidadTxt(x.cantidad)} ${escHtml(x.unidad_venta || '')}</strong></li>`).join('');
    const masProductos = productos.length > 3
        ? `<p class="order-more-items">y ${productos.length - 3} producto${productos.length - 3 === 1 ? '' : 's'} más</p>` : '';

    const entrega = fechaValida(p.fecha_entrega_programada)
        ? `<div class="order-info"><i data-lucide="calendar-days"></i><span><small>Entrega programada</small><strong>${escHtml(p.fecha_entrega_programada)}${p.horario_entrega && p.horario_entrega !== '00:00:00' ? ' · ' + escHtml(String(p.horario_entrega).slice(0, 5)) : ''}</strong></span></div>`
        : `<div class="order-info"><i data-lucide="calendar-days"></i><span><small>Pedido registrado</small><strong>${escHtml(p.fecha || 'Fecha no disponible')}</strong></span></div>`;
    const telefono = p.cliente_telefono
        ? `<span class="order-customer-phone"><i data-lucide="phone"></i>${escHtml(p.cliente_telefono)}</span>`
        : '';
    const repartidor = repNombre
        ? `<div class="order-info"><i data-lucide="bike"></i><span><small>Repartidor</small><strong>${escHtml(repNombre)}</strong></span></div>`
        : `<div class="order-info"><i data-lucide="bike"></i><span><small>Repartidor</small><strong>${estado === 'confirmado' ? 'Pendiente de asignar' : 'Sin asignar'}</strong></span></div>`;
    const inicial = escHtml(nombre.trim().charAt(0).toUpperCase() || '?');

    div.innerHTML = `
        <div class="order-card-top">
            <div class="order-id-group">
                <span class="order-id">Pedido #${id}</span>
                <span class="order-date">${escHtml(p.fecha || '')}</span>
            </div>
            <span class="order-status status-${escHtml(estado)}"><i data-lucide="circle"></i>${escHtml(estadoTitulos[estado] || estado)}</span>
        </div>
        <div class="order-customer">
            <span class="order-avatar">${inicial}</span>
            <div class="order-customer-main">
                <h3 class="cliente-nombre">${escHtml(nombre || 'Cliente sin nombre')}</h3>
                <div class="order-tags">${tags.join('')}</div>
            </div>
            <strong class="tp-total">$${(parseFloat(p.total) || 0).toFixed(2)}</strong>
        </div>
        ${telefono}
        <section class="order-items">
            <div class="order-section-title"><span>Productos</span><span>${productos.length} artículo${productos.length === 1 ? '' : 's'}</span></div>
            ${productos.length
                ? `<ul class="productos-lista">${productosHtml}</ul>${masProductos}`
                : '<p class="order-empty-items">No hay productos registrados.</p>'}
        </section>
        <div class="order-info-grid">${entrega}${repartidor}</div>
        ${lineaPago ? `<div class="order-payment">${lineaPago}</div>` : ''}
        <div class="order-card-actions">
            <div class="tp-actions">${botonesTarjeta(p, estado, enEspera)}</div>
            <div class="tp-icons">
                <button class="icon-btn icon-view" onclick="verDetallePedido(${id})" title="Ver detalle" aria-label="Ver detalle del pedido ${id}"><i data-lucide="eye"></i></button>
                <button class="icon-btn icon-edit" onclick="editarPedido(${id})" title="Editar pedido" aria-label="Editar pedido ${id}"><i data-lucide="pencil"></i></button>
                <button class="icon-btn icon-del" onclick="confirmarEliminarPedido(${id})" title="Eliminar pedido" aria-label="Eliminar pedido ${id}"><i data-lucide="trash-2"></i></button>
            </div>
        </div>`;
    return div;
}

function actualizarColumnaKanban(estado, pedidos, paginaActual, totalPages, totalPedidos = null) {
    const columna = document.querySelector(`.kanban-column[data-estado="${estado}"]`);
    if (!columna) return;
    const contenedor = columna.querySelector('.col-body');
    contenedor.innerHTML = '';

    if (!pedidos || !pedidos.length) {
        contenedor.innerHTML = '<div class="col-vacia">Sin pedidos</div>';
    } else {
        pedidos.forEach(p => contenedor.appendChild(crearTarjetaPedido(p, estado)));
    }

    const span = columna.querySelector('.pagina-actual');
    if (span) span.textContent = `Pág. ${paginaActual}`;
    const prev = columna.querySelector('.pagination-prev');
    const next = columna.querySelector('.pagination-next');
    if (prev) { prev.disabled = paginaActual <= 1; prev.style.opacity = paginaActual <= 1 ? '0.5' : '1'; }
    if (next) { next.disabled = paginaActual >= totalPages; next.style.opacity = paginaActual >= totalPages ? '0.5' : '1'; }
    const total = totalPedidos ?? (typeof TOTALES_ESTADO !== 'undefined' ? TOTALES_ESTADO[estado] : null);
    const conteo = columna.querySelector('.count-pedidos');
    if (conteo && total !== null && total !== undefined) {
        conteo.textContent = `${total} pedido${Number(total) === 1 ? '' : 's'}`;
    }
    lucide.createIcons();
}

/* ───────── Acciones del flujo ───────── */
async function pedirRepartidor(titulo, actualId) {
    const opciones = {};
    REPARTIDORES_DATA.forEach(r => { opciones[r.id] = r.label; });

    if (!Object.keys(opciones).length) {
        Swal.fire('Sin repartidores', 'Primero registra un repartidor en el módulo Repartidores.', 'info');
        return null;
    }

    const r = await Swal.fire({
        title: titulo, input: 'select', inputOptions: opciones,
        inputPlaceholder: 'Selecciona un repartidor', inputValue: actualId || '',
        showCancelButton: true, confirmButtonText: 'Asignar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#E91E63',
        inputValidator: v => (!v ? 'Selecciona un repartidor' : undefined),
    });
    return r.isConfirmed ? r.value : null;
}

async function aceptarPedido(id) {
    const c = await Swal.fire({
        title: '¿Aceptar este pedido?', text: 'Se verificará la existencia de los productos.',
        icon: 'question', showCancelButton: true, confirmButtonText: '✅ Aceptar pedido',
        cancelButtonText: 'No', confirmButtonColor: '#10b981',
    });
    if (!c.isConfirmed) return;

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const data = await postForm('pedidos/aceptar', { id_pedido: id });
        Swal.close();

        if (!data.success) {
            Swal.fire({ icon: data.sin_stock ? 'warning' : 'error', title: data.sin_stock ? 'Sin existencia suficiente' : 'Error', text: data.message });
            return;
        }
        if (data.stats) actualizarStatsUI(data.stats);

        const r = await Swal.fire({
            icon: 'success', title: 'Pedido aceptado',
            text: data.necesita_repartidor ? '¿Quieres asignar el repartidor ahora?' : 'El pedido ya tiene repartidor asignado.',
            showCancelButton: !!data.necesita_repartidor,
            confirmButtonText: data.necesita_repartidor ? '🛵 Asignar repartidor' : 'Listo',
            cancelButtonText: 'Más tarde', confirmButtonColor: '#E91E63',
        });
        if (r.isConfirmed && data.necesita_repartidor) {
            await asignarRepartidor(id, false);
        }
        location.reload();
    } catch (e) {
        Swal.close();
        console.error(e);
        Swal.fire('Error', 'Error de conexión al servidor', 'error');
    }
}

async function ponerPendiente(id, valor) {
    const c = await Swal.fire({
        title: valor ? '¿Dejar en espera?' : '¿Reactivar el pedido?',
        text: valor ? 'Úsalo si otro pedido se atendió primero y falta stock. El cliente verá "En espera".'
                    : 'Volverá a la lista normal de pendientes.',
        icon: 'question', showCancelButton: true, confirmButtonText: valor ? '⏸ Dejar pendiente' : '▶ Reactivar',
        cancelButtonText: 'No', confirmButtonColor: '#f59e0b',
    });
    if (!c.isConfirmed) return;

    try {
        const data = await postForm('pedidos/poner-pendiente', { id_pedido: id, en_espera: valor });
        if (data.success) {
            Swal.fire('Listo', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'No se pudo actualizar', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Error de conexión al servidor', 'error');
    }
}

async function asignarRepartidor(id, recargar = true) {
    const tarjeta = document.querySelector(`.tarjeta-pedido[data-id="${id}"]`);
    const rep = await pedirRepartidor('Asignar repartidor', tarjeta ? tarjeta.dataset.repartidorId : '');
    if (!rep) return false;

    try {
        const data = await postForm('pedidos/asignar-repartidor', { id_pedido: id, id_repartidor: rep });
        if (!data.success) { Swal.fire('Error', data.message || 'No se pudo asignar', 'error'); return false; }
        if (recargar) Swal.fire('Listo', data.message, 'success').then(() => location.reload());
        return true;
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Error de conexión al servidor', 'error');
        return false;
    }
}

async function confirmarAccion(idPedido, nuevoEstado) {
    const msgs = {
        confirmado:  ['¿Confirmar este pedido?',      '✅ Confirmar'],
        cancelado:   ['¿Cancelar este pedido?',       '❌ Cancelar'],
        entregado:   ['¿Marcar como entregado?',      '📦 Entregar'],
        en_transito: ['¿Enviar pedido a repartidor?', '🚚 Enviar'],
    };
    const [msg, btn] = msgs[nuevoEstado] || ['¿Continuar?', 'Aceptar'];

    // Para enviar hace falta repartidor: si no tiene, se pide primero.
    let repartidorId = '';
    const tarjeta = document.querySelector(`.tarjeta-pedido[data-id="${idPedido}"]`);
    if (nuevoEstado === 'en_transito' && tarjeta && !tarjeta.dataset.repartidorId) {
        repartidorId = await pedirRepartidor('Asigna un repartidor para enviar el pedido');
        if (!repartidorId) return;
    } else {
        const result = await Swal.fire({
            title: msg, icon: 'question', showCancelButton: true, cancelButtonText: 'No',
            confirmButtonColor: '#E91E63', confirmButtonText: btn,
        });
        if (!result.isConfirmed) return;
    }

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const datos = { id_pedido: idPedido, nuevo_estado: nuevoEstado };
        if (repartidorId) datos.id_repartidor = repartidorId;
        const data = await postForm('pedidos/cambiarEstado', datos);
        Swal.close();
        if (data.success) {
            if (data.stats) actualizarStatsUI(data.stats);
            Swal.fire('¡Éxito!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'No se pudo actualizar el estado', 'error');
        }
    } catch (e) {
        Swal.close();
        console.error(e);
        Swal.fire('Error', 'Error de conexión al servidor', 'error');
    }
}

/* ───────── Búsqueda (por cliente o repartidor) ───────── */
function filtrarTarjetasKanbanLocal(q) {
    const panel  = document.getElementById('resultado-busqueda');
    const lista  = document.getElementById('lista-resultado-busqueda');
    const titulo = document.getElementById('titulo-resultado-busqueda');

    const encontradas = [...document.querySelectorAll('.tarjeta-pedido')].filter(t =>
        normalizarTexto(t.dataset.cliente || '').includes(q) || normalizarTexto(t.dataset.repartidor || '').includes(q));

    panel.classList.remove('hidden');
    lista.innerHTML = '';

    if (!encontradas.length) {
        titulo.textContent = 'Sin resultados';
        lista.innerHTML = '<p class="text-gray-400 text-sm text-center py-4">No se encontraron pedidos con ese nombre</p>';
        return;
    }
    titulo.textContent = `${encontradas.length} resultado(s)`;

    const etiquetas = {
        pedido:      { label: '⏳ Pendiente',  cls: 'bg-rose-100 text-rose-700' },
        confirmado:  { label: '✅ Aceptado',   cls: 'bg-blue-100 text-blue-700' },
        en_transito: { label: '🚚 En tránsito', cls: 'bg-amber-100 text-amber-700' },
        entregado:   { label: '🏆 Entregado',  cls: 'bg-emerald-100 text-emerald-700' },
        cancelado:   { label: '❌ Cancelado',  cls: 'bg-gray-100 text-gray-700' },
        credito:     { label: '💰 Crédito',    cls: 'bg-purple-100 text-purple-700' },
        pagado:      { label: '✅ Pagado',     cls: 'bg-green-100 text-green-700' },
    };

    encontradas.forEach(t => {
        const id      = t.dataset.id;
        const columna = t.closest('.kanban-column');
        const est     = etiquetas[columna ? columna.dataset.estado : ''] || { label: '', cls: 'bg-gray-100 text-gray-700' };
        const nombre  = t.querySelector('.cliente-nombre')?.textContent.trim() || '';
        const prods   = t.querySelector('.productos-lista')?.textContent.trim().replace(/\s+/g, ' ') || 'Sin productos';
        const total   = t.querySelector('.tp-total')?.textContent.trim() || '';

        const div = document.createElement('div');
        div.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100 hover:shadow-sm transition-all cursor-pointer';
        div.onclick = () => verDetallePedido(id);
        div.innerHTML = `
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="font-bold text-gray-800 text-sm">${escHtml(nombre)}</span>
                    <span class="text-xs text-gray-400">Pedido #${id}</span>
                </div>
                <p class="text-[11px] text-gray-500 truncate">${escHtml(prods)}</p>
            </div>
            <div class="flex items-center gap-2 ml-3 shrink-0">
                <span class="text-xs font-bold text-gray-700">${escHtml(total)}</span>
                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full ${est.cls}">${est.label}</span>
            </div>`;
        lista.appendChild(div);
    });
}

/* ───────── Aviso de pedidos nuevos (sondeo cada 20 s) ───────── */
let ultimoNuevo = 0;

function iniciarSondeoPedidos() {
    const el = document.querySelector('.stat-value[data-stat="nuevo"]');
    ultimoNuevo = el ? parseInt(el.textContent, 10) || 0 : 0;

    setInterval(async () => {
        if (document.hidden) return;
        try {
            const res  = await fetch(BASE_URL + 'pedidos/getStatsActualizados');
            const data = await res.json();
            if (!data.success) return;

            actualizarStatsUI(data.stats);
            const nuevo = parseInt(data.stats.nuevo, 10) || 0;
            if (nuevo > ultimoNuevo) {
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'info', title: 'Llegó un nuevo pedido',
                    showConfirmButton: true, confirmButtonText: 'Actualizar', timer: 15000, timerProgressBar: true,
                }).then(r => { if (r.isConfirmed) location.reload(); });
            }
            ultimoNuevo = nuevo;
        } catch (e) { /* se reintenta en el siguiente ciclo */ }
    }, 20000);
}

let busquedaPedidosTimer;
let busquedaPedidosAbort;

function limpiarBusqueda() {
    const buscador = document.getElementById('buscador-pedidos');
    const panel = document.getElementById('resultado-busqueda');
    const boton = document.getElementById('btn-limpiar-busqueda');
    if (busquedaPedidosTimer) clearTimeout(busquedaPedidosTimer);
    if (busquedaPedidosAbort) busquedaPedidosAbort.abort();
    if (buscador) buscador.value = '';
    if (panel) panel.classList.add('hidden');
    if (boton) boton.classList.add('hidden');
}

function filtrarTarjetasKanban(q) {
    const panel = document.getElementById('resultado-busqueda');
    const lista = document.getElementById('lista-resultado-busqueda');
    const titulo = document.getElementById('titulo-resultado-busqueda');
    panel.classList.remove('hidden');
    titulo.textContent = 'Buscando pedidos...';
    lista.innerHTML = '<div class="search-loading"><span class="loading-spinner"></span><span>Buscando en todos los pedidos</span></div>';

    clearTimeout(busquedaPedidosTimer);
    if (busquedaPedidosAbort) busquedaPedidosAbort.abort();
    busquedaPedidosTimer = setTimeout(async () => {
        busquedaPedidosAbort = new AbortController();
        try {
            const res = await fetch(`${BASE_URL}pedidos/buscar?q=${encodeURIComponent(q)}`, {
                signal: busquedaPedidosAbort.signal,
            });
            if (!res.ok) throw new Error('No se pudo completar la búsqueda.');
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'No se pudo completar la búsqueda.');

            const estados = {
                pedido: ['Pendiente', 'rose'], confirmado: ['Aceptado', 'blue'],
                en_transito: ['En tránsito', 'amber'], entregado: ['Entregado', 'emerald'],
                cancelado: ['Cancelado', 'slate'], credito: ['Saldo pendiente', 'purple'], pagado: ['Pagado', 'green'],
            };
            lista.replaceChildren();
            titulo.textContent = `${data.total} resultado${data.total === 1 ? '' : 's'}`;
            if (!data.pedidos.length) {
                lista.innerHTML = '<p class="search-empty">No hay pedidos que coincidan. Prueba con otro nombre, producto o número.</p>';
                return;
            }

            data.pedidos.forEach(p => {
                const [estado, color] = estados[p.estado_actual] || ['Sin estado', 'slate'];
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'search-result-card';
                item.onclick = () => verDetallePedido(p.id_pedido);
                const cantidadProductos = (p.productos || []).length;
                item.innerHTML = `
                    <span class="search-result-icon"><i data-lucide="package-search"></i></span>
                    <span class="search-result-main">
                        <strong>${escHtml(`${p.cliente_nombre || ''} ${p.cliente_apellido || ''}`.trim() || 'Cliente')}</strong>
                        <small>Pedido #${Number(p.id_pedido)} · ${cantidadProductos} producto${cantidadProductos === 1 ? '' : 's'}</small>
                    </span>
                    <span class="search-result-total">$${(Number(p.total) || 0).toFixed(2)}</span>
                    <span class="order-status status-${color}">${estado}</span>
                    <i data-lucide="arrow-up-right" class="search-result-open"></i>`;
                lista.appendChild(item);
            });
            lucide.createIcons();
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error(error);
            titulo.textContent = 'No se pudo buscar';
            lista.innerHTML = '<p class="search-empty search-error">Ocurrió un error al buscar pedidos. Intenta de nuevo.</p>';
        }
    }, 250);
}

/* ───────── Render inicial ───────── */
document.addEventListener('DOMContentLoaded', () => {
    const iniciales = typeof PEDIDOS_INICIALES !== 'undefined' ? PEDIDOS_INICIALES : {};
    const totales   = typeof TOTALES_ESTADO !== 'undefined' ? TOTALES_ESTADO : {};
    const pagina    = typeof PAGINA_INICIAL !== 'undefined' ? PAGINA_INICIAL : 1;

    Object.keys(iniciales).forEach(estado => {
        const totalPages = Math.max(1, Math.ceil((parseInt(totales[estado], 10) || 0) / 10));
        actualizarColumnaKanban(estado, iniciales[estado], pagina, totalPages, totales[estado] || 0);
    });
    iniciarSondeoPedidos();
});