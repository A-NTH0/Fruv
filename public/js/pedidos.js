lucide.createIcons();

let productosSeleccionados = [];

let paginaActualPorEstado = {
    pedido: typeof PAGINA_INICIAL !== 'undefined' ? PAGINA_INICIAL : 1,
    confirmado: 1,
    en_transito: 1,
    entregado: 1,
    cancelado: 1,
    credito: 1,
    pagado: 1
};

/**
 * Precio sugerido según el cliente (RF-12). Si el cliente es preferencial se
 * aplica el descuento, pero el campo de precio sigue siendo editable.
 */
function precioSugerido(precioBase, clienteId) {
    const c = CLIENTES_DATA.find(x => String(x.id) === String(clienteId));
    if (c && c.preferencial) {
        return Math.round(precioBase * (100 - DESCUENTO_PREFERENCIAL)) / 100;
    }
    return precioBase;
}

function avisoClientePreferencial(item) {
    if (!item || !item.preferencial) return;
    Swal.fire({
        toast: true, position: 'top-end', icon: 'info', timer: 4000, showConfirmButton: false,
        title: `Cliente preferencial: los precios sugeridos llevan ${DESCUENTO_PREFERENCIAL}% de descuento (puedes editarlos).`
    });
}

function crearAutocomplete(inputId, hiddenId, listaId, datos, onSelect, mensajeVacio) {
    const input  = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    const lista  = document.getElementById(listaId);
    if (!input || !lista) return;

    let activeIdx = -1;

    function mostrar(filtrados) {
        lista.innerHTML = '';
        activeIdx = -1;
        if (!filtrados.length) {
            lista.innerHTML = `<div class="autocomplete-empty">${mensajeVacio || 'Sin resultados'}</div>`;
            lista.classList.add('open');
            return;
        }
        filtrados.forEach((item) => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.textContent = item.label;
            div.addEventListener('mousedown', (e) => {
                e.preventDefault();
                seleccionar(item);
            });
            lista.appendChild(div);
        });
        lista.classList.add('open');
    }

    function seleccionar(item) {
        input.value  = item.label;
        hidden.value = item.id;
        lista.classList.remove('open');
        if (onSelect) onSelect(item);
    }

    function cerrar() {
        lista.classList.remove('open');
        activeIdx = -1;
    }

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        hidden.value = '';
        if (!q) { cerrar(); return; }
        const filtrados = datos.filter(d => d.label.toLowerCase().includes(q)).slice(0, 12);
        mostrar(filtrados);
    });

    input.addEventListener('keydown', (e) => {
        const items = lista.querySelectorAll('.autocomplete-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, items.length - 1);
            items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
            items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && items[activeIdx]) items[activeIdx].dispatchEvent(new Event('mousedown'));
        } else if (e.key === 'Escape') {
            cerrar();
        }
    });

    input.addEventListener('focus', () => {
        if (input.value.trim()) {
            const q = input.value.trim().toLowerCase();
            mostrar(datos.filter(d => d.label.toLowerCase().includes(q)).slice(0, 12));
        }
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !lista.contains(e.target)) cerrar();
    });
}

function scrollToColumn(estado) {
    const panel = document.querySelector(`.kanban-column[data-estado="${estado}"]`);
    if (!panel) return;

    document.querySelectorAll('.kanban-column').forEach(columna => {
        const activa = columna === panel;
        columna.hidden = !activa;
        columna.classList.toggle('is-active', activa);
    });
    document.querySelectorAll('.kpi-chip[role="tab"]').forEach(tab => {
        const activa = tab.getAttribute('aria-controls') === panel.id;
        tab.setAttribute('aria-selected', String(activa));
        tab.classList.toggle('is-active', activa);
    });
}

async function cambiarPaginaColumna(estado, direccion) {
    let nuevaPagina = paginaActualPorEstado[estado];
    if (direccion === 'next') nuevaPagina++;
    else if (direccion === 'prev') { if (nuevaPagina <= 1) return; nuevaPagina--; }

    const columna    = document.querySelector(`.kanban-column[data-estado="${estado}"]`);
    const contenedor = columna.querySelector('.col-body');
    contenedor.innerHTML = `<div class="flex justify-center items-center py-10"><span class="text-gray-500 text-sm">Cargando...</span></div>`;

    try {
        const res  = await fetch(`${BASE_URL}pedidos/getPedidosPaginados?estado=${estado}&page=${nuevaPagina}&per_page=10`);
        const data = await res.json();
        if (data.success) {
            paginaActualPorEstado[estado] = nuevaPagina;
            actualizarColumnaKanban(estado, data.pedidos, nuevaPagina, data.totalPages, data.total);
            if (data.stats) actualizarStatsUI(data.stats);
        } else throw new Error(data.message);
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudieron cargar los pedidos', 'error');
    }
}

function actualizarStatsUI(stats) {
    const map = {
        nuevo: stats.nuevo, confirmado: stats.confirmado,
        transito: stats.transito, completado: stats.completado,
        credito: stats.credito, pagado: stats.pagado, cancelado: stats.cancelado
    };
    document.querySelectorAll('.stat-value').forEach(el => {
        if (map[el.dataset.stat] !== undefined) el.textContent = map[el.dataset.stat];
    });
    const porEstado = {
        pedido: stats.nuevo, confirmado: stats.confirmado, en_transito: stats.transito,
        entregado: stats.completado, credito: stats.credito, pagado: stats.pagado, cancelado: stats.cancelado,
    };
    Object.entries(porEstado).forEach(([estado, total]) => {
        const conteo = document.querySelector(`.kanban-column[data-estado="${estado}"] .count-pedidos`);
        if (conteo && total !== undefined) conteo.textContent = `${total} pedido${Number(total) === 1 ? '' : 's'}`;
    });
    const atencion = document.querySelector('[data-summary="atencion"]');
    const transito = document.querySelector('[data-summary="transito"]');
    if (atencion) atencion.textContent = (Number(stats.nuevo) || 0) + (Number(stats.confirmado) || 0);
    if (transito) transito.textContent = Number(stats.transito) || 0;
}

function actualizarColumnaKanban(estado, pedidos, paginaActual, totalPages) {
    const columna    = document.querySelector(`.kanban-column[data-estado="${estado}"]`);
    if (!columna) return;
    const contenedor = columna.querySelector('.col-body');
    contenedor.innerHTML = '';
    if (!pedidos || !pedidos.length) {
        contenedor.innerHTML = `<div class="text-center py-10 opacity-60"><p class="text-gray-400 text-xs">No hay pedidos en esta página</p></div>`;
    } else {
        pedidos.forEach(p => contenedor.appendChild(crearTarjetaPedido(p, estado)));
    }
    const span = columna.querySelector('.pagina-actual');
    if (span) span.textContent = `Pág. ${paginaActual}`;
    const prev = columna.querySelector('.pagination-prev');
    const next = columna.querySelector('.pagination-next');
    if (prev) { prev.disabled = paginaActual <= 1; prev.style.opacity = paginaActual <= 1 ? '0.5' : '1'; }
    if (next) { next.disabled = paginaActual >= totalPages; next.style.opacity = paginaActual >= totalPages ? '0.5' : '1'; }
    lucide.createIcons();
}

function crearTarjetaPedido(pedido, estado) {
    const div = document.createElement('div');
    div.className = 'tarjeta-pedido p-3 hover:shadow-md transition-all';
    div.dataset.id        = pedido.id_pedido;
    div.dataset.cliente   = ((pedido.cliente_nombre || '') + ' ' + (pedido.cliente_apellido || '')).toLowerCase();
    div.dataset.productos = (pedido.productos || []).map(p => p.nombre_producto).join(' ').toLowerCase();

    const prodsHtml = (pedido.productos || []).slice(0, 2)
        .map(p => `<p>• ${p.nombre_producto} (x${p.cantidad})</p>`).join('');
    const masProds  = (pedido.productos || []).length > 2
        ? `<p class="text-gray-400">+${pedido.productos.length - 2} más</p>` : '';

    const botonesEstado = (() => {
        if (estado === 'pedido')
            return `<button onclick="confirmarAccion(${pedido.id_pedido},'confirmado')" class="flex-1 bg-emerald-500 text-white py-1.5 rounded-lg text-[11px] font-bold">✅ Confirmar</button>
                    <button onclick="confirmarAccion(${pedido.id_pedido},'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>`;
        if (estado === 'confirmado')
            return `<button onclick="confirmarAccion(${pedido.id_pedido},'en_transito')" class="flex-1 bg-blue-500 text-white py-1.5 rounded-lg text-[11px] font-bold">🚚 Enviar</button>
                    <button onclick="confirmarAccion(${pedido.id_pedido},'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>`;
        if (estado === 'en_transito') {
            const esCredito = pedido.modalidad_pago === 'credito';
            return `<button onclick="confirmarAccion(${pedido.id_pedido},'entregado')" class="flex-1 bg-emerald-500 text-white py-1.5 rounded-lg text-[11px] font-bold">${esCredito ? '📦 Entregar y cobrar' : '📦 Entregado'}</button>
                    <button onclick="confirmarAccion(${pedido.id_pedido},'cancelado')" class="flex-1 bg-gray-400 text-white py-1.5 rounded-lg text-[11px] font-bold">❌ Cancelar</button>`;
        }
        if (estado === 'credito' && pedido.deuda_restante > 0)
            return `<button onclick="abrirModalAbono(${pedido.id_pedido},${pedido.deuda_restante})" class="w-full bg-purple-500 text-white py-1.5 rounded-lg text-[11px] font-bold">💰 Registrar Abono</button>`;
        return '';
    })();

    div.innerHTML = `
        <div class="flex justify-between items-start mb-2">
            <div class="flex-1">
                <h3 class="font-bold text-gray-800 text-sm cliente-nombre">${pedido.cliente_nombre || ''} ${pedido.cliente_apellido || ''}</h3>
                <p class="text-[11px] text-gray-400">Pedido #${pedido.id_pedido}</p>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-xs font-black text-gray-700 mr-1">$${parseFloat(pedido.total || 0).toFixed(2)}</span>
                <button onclick="editarPedido(${pedido.id_pedido})" class="p-1 bg-amber-50 text-amber-600 rounded-md hover:bg-amber-100" title="Editar"><i data-lucide="edit-2" class="w-3 h-3"></i></button>
                <button onclick="verDetallePedido(${pedido.id_pedido})" class="p-1 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100" title="Ver detalle"><i data-lucide="eye" class="w-3 h-3"></i></button>
                <button onclick="confirmarEliminarPedido(${pedido.id_pedido})" class="p-1 bg-red-50 text-red-500 rounded-md hover:bg-red-100" title="Eliminar"><i data-lucide="trash-2" class="w-3 h-3"></i></button>
            </div>
        </div>
        <div class="text-[11px] text-gray-500 space-y-0.5 mb-2 productos-lista">${prodsHtml || '<p class="text-gray-300 italic">Sin productos</p>'}${masProds}</div>
        ${pedido.id_repartidor && pedido.repartidor_nombre ? `<div class="flex items-center gap-1 text-[10px] text-gray-400 mt-1 pt-1 border-t border-gray-100"><i data-lucide="user" class="w-3 h-3"></i><span>${pedido.repartidor_nombre}${pedido.repartidor_apellido || ''}</span></div>` : ''}
        ${estado === 'credito' && pedido.deuda_restante > 0 ? `<div class="mt-1 text-[11px] font-semibold text-purple-600">Saldo: $${parseFloat(pedido.deuda_restante).toFixed(2)}</div>` : ''}
        <div class="mt-2 flex gap-2">${botonesEstado}</div>`;
    return div;
}

function normalizarTexto(txt) {
    return (txt || '').toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function inicializarBuscador() {
    const buscador   = document.getElementById('buscador-pedidos');
    const btnLimpiar = document.getElementById('btn-limpiar-busqueda');
    if (!buscador) return;

    buscador.addEventListener('input', () => {
        const q = normalizarTexto(buscador.value.trim());
        if (!q) { limpiarBusqueda(); return; }
        btnLimpiar.classList.remove('hidden');
        filtrarTarjetasKanban(q);
    });

    buscador.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') limpiarBusqueda();
    });
}

function filtrarTarjetasKanban(q) {
    const panel  = document.getElementById('resultado-busqueda');
    const lista  = document.getElementById('lista-resultado-busqueda');
    const titulo = document.getElementById('titulo-resultado-busqueda');

    const tarjetas = document.querySelectorAll('.tarjeta-pedido');
    const encontradas = [];

    tarjetas.forEach(tarjeta => {
        const cliente    = normalizarTexto(tarjeta.dataset.cliente || '');
        const repSpan    = tarjeta.querySelector('.flex.items-center.gap-1 span');
        const repartidor = normalizarTexto(repSpan ? repSpan.textContent : '');

        if (cliente.includes(q) || repartidor.includes(q)) {
            encontradas.push(tarjeta);
        }
    });

    panel.classList.remove('hidden');
    lista.innerHTML = '';

    if (!encontradas.length) {
        titulo.textContent = 'Sin resultados';
        lista.innerHTML = '<p class="text-gray-400 text-sm text-center py-4">No se encontraron pedidos con ese nombre</p>';
        return;
    }

    titulo.textContent = `${encontradas.length} resultado(s)`;

    const estadoLabels = {
        pedido:      { label: '📥 Nuevo',      cls: 'bg-rose-100 text-rose-700' },
        confirmado:  { label: '✅ Confirmado', cls: 'bg-blue-100 text-blue-700' },
        en_transito: { label: '🚚 En Tránsito',cls: 'bg-amber-100 text-amber-700' },
        entregado:   { label: '🏆 Entregado',  cls: 'bg-emerald-100 text-emerald-700' },
        cancelado:   { label: '❌ Cancelado',  cls: 'bg-gray-100 text-gray-700' },
        credito:     { label: '💰 Crédito',    cls: 'bg-purple-100 text-purple-700' },
        pagado:      { label: '✅ Pagado',     cls: 'bg-green-100 text-green-700' },
    };

    encontradas.forEach(tarjeta => {
        const idPedido    = tarjeta.dataset.id;
        const columna     = tarjeta.closest('.kanban-column');
        const estadoKey   = columna ? columna.dataset.estado : '';
        const est         = estadoLabels[estadoKey] || { label: estadoKey, cls: 'bg-gray-100 text-gray-700' };

        const nombreCliente = tarjeta.querySelector('.cliente-nombre')?.textContent.trim() || '';
        const productos     = tarjeta.querySelector('.productos-lista')?.textContent.trim().replace(/\s+/g, ' ') || 'Sin productos';
        const totalSpan     = tarjeta.querySelector('.text-xs.font-black');
        const total         = totalSpan ? totalSpan.textContent.trim() : '';

        const div = document.createElement('div');
        div.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100 hover:shadow-sm transition-all cursor-pointer';
        div.onclick = () => verDetallePedido(idPedido);
        div.innerHTML = `
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="font-bold text-gray-800 text-sm">${nombreCliente}</span>
                    <span class="text-xs text-gray-400">Pedido #${idPedido}</span>
                </div>
                <p class="text-[11px] text-gray-500 truncate">${productos}</p>
            </div>
            <div class="flex items-center gap-2 ml-3 shrink-0">
                <span class="text-xs font-bold text-gray-700">${total}</span>
                <span class="text-[10px] font-medium px-2 py-0.5 rounded-full ${est.cls}">${est.label}</span>
                <button onclick="event.stopPropagation(); verDetallePedido(${idPedido})" class="p-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                    <i data-lucide="eye" class="w-3 h-3"></i>
                </button>
            </div>`;
        lista.appendChild(div);
    });

    lucide.createIcons();
}

function limpiarBusqueda() {
    document.getElementById('buscador-pedidos').value = '';
    document.getElementById('resultado-busqueda').classList.add('hidden');
    document.getElementById('btn-limpiar-busqueda').classList.add('hidden');
}

async function confirmarAccion(idPedido, nuevoEstado) {
    const msgs = {
        confirmado:  ['¿Confirmar este pedido?',        '✅ Confirmar'],
        cancelado:   ['¿Cancelar este pedido?',         '❌ Cancelar'],
        entregado:   ['¿Marcar como entregado?',        '📦 Entregar'],
        en_transito: ['¿Enviar pedido a repartidor?',   '🚚 Enviar'],
    };
    const [msg, btn] = msgs[nuevoEstado] || ['¿Continuar?', 'Aceptar'];

    const result = await Swal.fire({
        title: msg, icon: 'question',
        showCancelButton: true, cancelButtonText: 'No',
        confirmButtonColor: '#E91E63', confirmButtonText: btn,
    });
    if (!result.isConfirmed) return;

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res  = await fetch(BASE_URL + 'pedidos/cambiarEstado', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id_pedido: idPedido, nuevo_estado: nuevoEstado }),
        });
        const data = await res.json();
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

function confirmarEliminarPedido(id) {
    Swal.fire({
        title: '¿Eliminar pedido?', text: 'Esta acción no se puede deshacer',
        icon: 'warning', showCancelButton: true, cancelButtonText: 'Cancelar',
        confirmButtonColor: '#E91E63', confirmButtonText: 'Sí, eliminar',
    }).then(r => { if (r.isConfirmed) window.location.href = BASE_URL + 'pedidos/eliminar/' + id; });
}

function verDetallePedido(idPedido) {
    fetch(BASE_URL + 'pedidos/obtenerDetallePedido?id_pedido=' + idPedido)
    .then(r => r.json())
    .then(data => {
        if (!data.success) { Swal.fire('Error', 'No se pudo cargar el detalle', 'error'); return; }
        const p = data.pedido;

        let prodsHtml = '<div class="space-y-2">';
        if (data.detalles?.length) {
            data.detalles.forEach(d => {
                prodsHtml += `<div class="flex justify-between items-center border-b pb-2">
                    <div><span class="font-medium">${d.nombre_producto}</span><span class="text-xs text-gray-400 ml-1">(${d.unidad_venta})</span></div>
                    <div class="text-right"><div>x${d.cantidad}</div><div class="text-sm font-medium">$${(d.cantidad * d.precio_venta).toFixed(2)}</div></div>
                </div>`;
            });
        } else prodsHtml += '<p class="text-gray-400 text-center">Sin productos</p>';
        prodsHtml += '</div>';

        let estadosHtml = '<div class="space-y-2 max-h-40 overflow-y-auto">';
        if (data.historial_estados?.length) {
            data.historial_estados.forEach(e => {
                const cls = {
                    pedido:'bg-rose-100 text-rose-700', confirmado:'bg-blue-100 text-blue-700',
                    en_transito:'bg-amber-100 text-amber-700', entregado:'bg-emerald-100 text-emerald-700',
                    cancelado:'bg-gray-100 text-gray-700', credito:'bg-purple-100 text-purple-700',
                    pagado:'bg-green-100 text-green-700',
                }[e.estado] || 'bg-gray-100 text-gray-700';
                estadosHtml += `<div class="flex justify-between p-2 rounded-lg ${cls}">
                    <span class="font-medium">${e.estado}</span>
                    <span class="text-xs">${new Date(e.fecha).toLocaleString('es-MX')}</span>
                </div>`;
            });
        } else estadosHtml += '<p class="text-gray-400 text-center">Sin historial</p>';
        estadosHtml += '</div>';

        let html = `<div class="space-y-5">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl">
                <h3 class="font-bold text-blue-800 mb-3">👤 Cliente</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">Nombre:</span> <span class="font-medium">${p.cliente_nombre} ${p.cliente_apellido || ''}</span></div>
                    <div><span class="text-gray-500">Teléfono:</span> <span class="font-medium">${p.cliente_telefono || 'N/A'}</span></div>
                    ${p.cliente_telefono ? `<div class="col-span-2"><button type="button" onclick="enviarTicketWhatsApp(${p.id_pedido})" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-bold"><i data-lucide="message-circle" class="w-4 h-4"></i> Enviar ticket por WhatsApp</button></div>` : ''}
                </div>
            </div>
            <div class="bg-gradient-to-r from-rose-50 to-orange-50 p-4 rounded-xl">
                <h3 class="font-bold text-rose-800 mb-3">📦 Pedido</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">Pedido #:</span> <span class="font-medium">${p.id_pedido}</span></div>
                    <div><span class="text-gray-500">Fecha:</span> <span class="font-medium">${p.fecha || 'N/A'}</span></div>
                    <div><span class="text-gray-500">Entrega programada:</span> <span class="font-medium">${p.fecha_entrega_programada || 'Pendiente'}</span></div>
                    <div><span class="text-gray-500">Horario:</span> <span class="font-medium">${p.horario_entrega || 'No especificado'}</span></div>
                    <div><span class="text-gray-500">Repartidor:</span> <span class="font-medium">${p.repartidor_nombre || 'Sin asignar'} ${p.repartidor_apellido || ''}</span></div>
                    <div><span class="text-gray-500">Pago:</span> <span class="font-medium">${p.modalidad_pago === 'credito' ? '💰 A Crédito' : '💵 De Contado'}</span></div>
                    <div><span class="text-gray-500">Origen:</span> <span class="font-medium">${p.origen === 'cliente' ? '🌐 Cliente web' : '🧑‍💼 Administrador'}</span></div>
                    <div class="col-span-2"><span class="text-gray-500">Total:</span> <span class="font-bold text-rose-600 text-lg">$${parseFloat(p.total).toFixed(2)}</span></div>
                </div>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl"><h3 class="font-bold text-gray-700 mb-3">🛒 Productos</h3>${prodsHtml}</div>`;

        if (p.modalidad_pago === 'credito') {
            const totalDet   = parseFloat(p.total) || 0;
            const inicialDet = parseFloat(p.pago_inicial) || 0;
            const deudaDet   = parseFloat(p.deuda_restante) || 0;
            const abonadoDet = (data.historial_abonos || []).reduce((s, a) => s + (parseFloat(a.monto) || 0), 0);
            const liquidado  = deudaDet <= 0.004;
            let abonosHtml = '<div class="space-y-2">';
            if (data.historial_abonos?.length) {
                data.historial_abonos.forEach(a => {
                    abonosHtml += `<div class="flex justify-between p-2 bg-gray-50 rounded-lg">
                        <span>${new Date(a.fecha_abono).toLocaleDateString('es-MX')}</span>
                        <span class="text-green-600 font-bold">+$${parseFloat(a.monto).toFixed(2)}</span>
                    </div>`;
                });
            } else abonosHtml += '<p class="text-gray-400 text-center">Sin abonos registrados</p>';
            abonosHtml += '</div>';

            html += `<div class="bg-gradient-to-r from-purple-50 to-pink-50 p-4 rounded-xl">
                <h3 class="font-bold text-purple-800 mb-3">💳 Crédito</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">Total:</span> <span class="font-medium">$${totalDet.toFixed(2)}</span></div>
                    <div><span class="text-gray-500">Pago inicial:</span> <span class="font-medium">$${inicialDet.toFixed(2)}</span></div>
                    <div><span class="text-gray-500">Abonos posteriores:</span> <span class="font-medium">$${abonadoDet.toFixed(2)}</span></div>
                    <div><span class="text-gray-500">Total pagado:</span> <span class="font-medium">$${Math.max(0, totalDet - deudaDet).toFixed(2)}</span></div>
                    <div><span class="text-gray-500">Saldo restante:</span> <span class="font-bold ${liquidado ? 'text-green-600' : 'text-rose-600'}">$${deudaDet.toFixed(2)}</span></div>
                    <div><span class="text-gray-500">Estado de pago:</span> <span class="font-bold ${liquidado ? 'text-green-600' : 'text-amber-600'}">${liquidado ? 'Totalmente Pagado' : 'Saldo pendiente'}</span></div>
                </div>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl"><h3 class="font-bold text-gray-700 mb-3">📋 Historial de Abonos</h3>${abonosHtml}</div>`;
        }

        html += `<div class="bg-gray-50 p-4 rounded-xl"><h3 class="font-bold text-gray-700 mb-3">🕐 Historial de Estados</h3>${estadosHtml}</div></div>`;

        document.getElementById('detalleContenido').innerHTML = html;
        document.getElementById('modalDetalle').classList.remove('hidden');
        document.getElementById('modalDetalle').classList.add('flex');
        lucide.createIcons();
    }).catch(e => { console.error(e); Swal.fire('Error', 'Error al cargar el detalle', 'error'); });
}

function cerrarModalDetalle(event) {
    if (!event || event.target === document.getElementById('modalDetalle')) {
        document.getElementById('modalDetalle').classList.add('hidden');
        document.getElementById('modalDetalle').classList.remove('flex');
    }
}

/* ═════════ EDICIÓN DE PEDIDO ═════════
   RF-12: cantidad y precio unitario son editables en cada línea, también cuando el cliente es
   preferencial (el descuento solo se sugiere; el administrador puede cambiarlo). */

let editPedidoDescontado = false;   // true si el pedido ya fue aceptado (su stock ya está descontado)

/** Fila de producto del modal de edición. */
function filaEdicionHtml(id, nombre, cantidad, unidad, precio) {
    const cant = parseFloat(cantidad) || 0;
    const prec = parseFloat(precio) || 0;
    return `
    <tr data-producto-id="${escHtml(id)}" data-unidad="${escHtml(unidad)}">
        <td class="py-2 text-left font-medium text-sm">${escHtml(nombre)}</td>
        <td class="py-2 text-center">
            <input type="number" value="${cant}" min="0.01" step="0.01"
                class="w-20 p-1 border rounded text-center linea-cant"
                aria-label="Cantidad de ${escHtml(nombre)}">
        </td>
        <td class="py-2 text-center text-sm">${escHtml(unidad)}</td>
        <td class="py-2 text-right">
            <input type="number" value="${prec.toFixed(2)}" min="0.01" step="0.01"
                class="w-24 p-1 border rounded text-right linea-precio border-amber-300 bg-amber-50"
                aria-label="Precio unitario de ${escHtml(nombre)}">
        </td>
        <td class="py-2 text-right font-bold text-sm subtotal">$${(cant * prec).toFixed(2)}</td>
        <td class="py-2 text-center">
            <button type="button" onclick="eliminarFilaProducto(this)" aria-label="Quitar ${escHtml(nombre)} del pedido" class="text-rose-500 hover:text-rose-700">
                <i data-lucide="trash-2" class="w-4 h-4" aria-hidden="true"></i>
            </button>
        </td>
    </tr>`;
}

function editarPedido(idPedido) {
    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(BASE_URL + 'pedidos/obtenerDetallePedido?id_pedido=' + idPedido)
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (!data.success) { Swal.fire('Error', data.message || 'No se pudo cargar el pedido', 'error'); return; }
        const p = data.pedido;
        editPedidoDescontado = parseInt(p.stock_descontado, 10) === 1;

        let productosExistentesHtml = '';
        if (data.detalles && data.detalles.length > 0) {
            data.detalles.forEach(d => {
                productosExistentesHtml += filaEdicionHtml(d.id_producto3, d.nombre_producto, d.cantidad, d.unidad_venta, d.precio_venta);
            });
        } else {
            productosExistentesHtml = '<tr id="fila-vacia-edit"><td colspan="6" class="text-center py-4 text-gray-400">No hay productos</td></tr>';
        }

        const clienteActual    = CLIENTES_DATA.find(c => c.id == p.id_cliente1);
        const repartidorActual = REPARTIDORES_DATA.find(r => r.id == p.id_repartidor);

        document.getElementById('edit-id_pedido').textContent = p.id_pedido;
        document.getElementById('editarContenido').innerHTML = `
            <form id="formEditarPedido">
                <input type="hidden" name="id_pedido" value="${p.id_pedido}">
                <input type="hidden" id="edit-cliente-id" name="id_cliente" value="${p.id_cliente1 || ''}">
                <input type="hidden" id="edit-repartidor-id" name="id_repartidor" value="${p.id_repartidor || ''}">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Modalidad de Pago</label>
                        <select name="modalidad_pago" class="w-full p-2 border rounded-lg">
                            <option value="contado" ${p.modalidad_pago == 'contado' ? 'selected' : ''}>💵 Contado</option>
                            <option value="credito"  ${p.modalidad_pago == 'credito'  ? 'selected' : ''}>💰 Crédito</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cliente</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="edit-cliente-texto" class="autocomplete-input"
                                placeholder="Buscar cliente..." autocomplete="off"
                                value="${clienteActual ? clienteActual.label : ''}">
                            <div id="edit-cliente-lista" class="autocomplete-list"></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Repartidor</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="edit-repartidor-texto" class="autocomplete-input"
                                placeholder="Buscar repartidor..." autocomplete="off"
                                value="${repartidorActual ? repartidorActual.label : ''}">
                            <div id="edit-repartidor-lista" class="autocomplete-list"></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fecha Pedido</label>
                        <input type="date" name="fecha" value="${p.fecha || ''}" class="w-full p-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fecha Entrega</label>
                        <input type="date" name="fecha_entrega_programada" value="${p.fecha_entrega_programada || ''}" class="w-full p-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Horario Entrega</label>
                        <input type="time" name="horario_entrega" value="${p.horario_entrega || ''}" class="w-full p-2 border rounded-lg">
                    </div>
                </div>
                <div class="bg-gray-100 p-4 rounded-lg mb-4">
                    <h3 class="font-bold text-gray-700 mb-3 text-sm">🛒 Agregar Productos</h3>
                    <div class="flex gap-2 flex-wrap">
                        <input type="hidden" id="edit-prod-id">
                        <input type="hidden" id="edit-prod-precio">
                        <div class="autocomplete-wrap flex-1 min-w-[180px]">
                            <input type="text" id="edit-sel-prod-texto" class="autocomplete-input text-sm" placeholder="Buscar producto..." autocomplete="off">
                            <div id="edit-prod-lista" class="autocomplete-list"></div>
                        </div>
                        <input type="number" id="edit-cant-prod" value="1" min="0.01" step="0.01" class="w-24 p-2 border rounded-lg text-sm" aria-label="Cantidad a agregar">
                        <select id="edit-unidad-prod" class="w-28 p-2 border rounded-lg text-sm" aria-label="Unidad">
                            <option value="kilos">Kilos</option>
                            <option value="domos">Domos</option>
                            <option value="ramos">Ramos</option>
                            <option value="caja">Caja</option>
                            <option value="mazo">Mazo</option>
                            <option value="arpilla">Arpilla</option>
                        </select>
                        <button type="button" onclick="agregarProductoEnEdicion()" class="bg-rose-500 hover:bg-rose-600 text-white px-4 rounded-lg text-sm font-bold">+ Agregar</button>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                        <h3 class="font-bold text-gray-700 text-sm">📦 Productos del Pedido</h3>
                        <button type="button" onclick="aplicarPrecioPreferencialEdicion()" class="text-xs font-bold bg-amber-100 text-amber-900 px-3 py-1.5 rounded-lg hover:bg-amber-200">
                            ⭐ Aplicar precio preferencial (-${DESCUENTO_PREFERENCIAL}%)
                        </button>
                    </div>
                    <p class="text-[11px] text-gray-600 mb-2">💡 La cantidad y el precio unitario se pueden editar en cada línea.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-2 text-left">Producto</th>
                                    <th class="p-2 text-center">Cantidad</th>
                                    <th class="p-2 text-center">Unidad</th>
                                    <th class="p-2 text-right">Precio Unit.</th>
                                    <th class="p-2 text-right">Subtotal</th>
                                    <th class="p-2 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="edit-productos-body">${productosExistentesHtml}</tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="4" class="p-2 text-right font-bold">TOTAL:</td>
                                    <td class="p-2 text-right font-bold text-rose-600" id="edit-total-modal">$${parseFloat(p.total || 0).toFixed(2)}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </form>
            <div class="flex gap-3 mt-4">
                <button onclick="guardarEdicionPedido()" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white py-3 rounded-xl font-bold transition-colors">💾 Guardar Cambios</button>
                <button onclick="cerrarModalEditar()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-colors">Cancelar</button>
            </div>`;

        document.getElementById('modalEditar').classList.remove('hidden');
        document.getElementById('modalEditar').classList.add('flex');
        lucide.createIcons();

        crearAutocomplete('edit-cliente-texto', 'edit-cliente-id', 'edit-cliente-lista', CLIENTES_DATA, avisoClientePreferencial);
        crearAutocomplete('edit-repartidor-texto', 'edit-repartidor-id', 'edit-repartidor-lista', REPARTIDORES_DATA);
        crearAutocomplete('edit-sel-prod-texto', 'edit-prod-id', 'edit-prod-lista', PRODUCTOS_DATA, (item) => {
            document.getElementById('edit-prod-precio').value = precioSugerido(item.precio, document.getElementById('edit-cliente-id').value);
            const inputEditProd = document.getElementById('edit-sel-prod-texto');
            inputEditProd.dataset.stock        = item.stock;
            inputEditProd.dataset.nombreLimpio = item.nombre_limpio;
        }, 'No hay productos activos o con stock');

        // Subtotales y total salen de los campos de cantidad y precio, y se recalculan al escribir.
        document.getElementById('edit-productos-body').addEventListener('input', (ev) => {
            if (ev.target.matches('.linea-cant, .linea-precio')) recalcularTotalEdicion();
        });

    }).catch(err => { Swal.close(); console.error(err); Swal.fire('Error', 'No se pudo cargar el pedido para editar', 'error'); });
}

/** Aplica a cada línea el precio de catálogo con el descuento preferencial; se puede volver a editar a mano. */
function aplicarPrecioPreferencialEdicion() {
    const clienteId = document.getElementById('edit-cliente-id').value;
    const cliente   = CLIENTES_DATA.find(c => String(c.id) === String(clienteId));

    if (!cliente || !cliente.preferencial) {
        Swal.fire('Cliente no preferencial', 'Este cliente todavía no es preferencial. Puedes asignarle la marca desde la vista de Clientes.', 'info');
        return;
    }

    let aplicados = 0, omitidos = 0;
    document.querySelectorAll('#edit-productos-body tr[data-producto-id]').forEach(row => {
        const prod = PRODUCTOS_DATA.find(x => String(x.id) === String(row.dataset.productoId));
        if (!prod || !(prod.precio > 0)) { omitidos++; return; }
        row.querySelector('.linea-precio').value = precioSugerido(prod.precio, cliente.id).toFixed(2);
        aplicados++;
    });
    recalcularTotalEdicion();

    Swal.fire({
        toast: true, position: 'top-end', icon: aplicados ? 'success' : 'warning', timer: 4500, showConfirmButton: false,
        title: aplicados
            ? `Precio preferencial aplicado a ${aplicados} línea(s)` + (omitidos ? `; ${omitidos} sin precio de catálogo` : '')
            : 'Ninguna línea tiene precio de catálogo disponible'
    });
}

function agregarProductoEnEdicion() {
    const id             = document.getElementById('edit-prod-id').value;
    const precio         = parseFloat(document.getElementById('edit-prod-precio').value);
    const inputEditTexto = document.getElementById('edit-sel-prod-texto');
    const nombre         = inputEditTexto.dataset.nombreLimpio || inputEditTexto.value.trim();
    const stockDisp      = parseFloat(inputEditTexto.dataset.stock || 0);
    const cantidad       = parseFloat(document.getElementById('edit-cant-prod').value);
    const unidad         = document.getElementById('edit-unidad-prod').value;

    if (!id)                              { Swal.fire('Error', 'Seleccione un producto de la lista', 'error'); return; }
    if (isNaN(cantidad) || cantidad <= 0) { Swal.fire('Error', 'Cantidad inválida', 'error');                  return; }
    if (!precio || isNaN(precio))         { Swal.fire('Error', 'Este producto no tiene precio', 'error');      return; }

    const filaExistente = document.querySelector(`#edit-productos-body tr[data-producto-id="${id}"]`);
    const cantActual    = filaExistente ? (parseFloat(filaExistente.querySelector('.linea-cant').value) || 0) : 0;

    // Si el pedido ya fue aceptado, su stock ya está descontado: solo lo que se agrega cuenta contra lo disponible.
    const requerido = editPedidoDescontado ? cantidad : cantActual + cantidad;

    if (requerido > stockDisp) {
        Swal.fire({
            title: 'Stock Insuficiente',
            html: `Solo tienes <b>${stockDisp}</b> unidades disponibles de ${escHtml(nombre)}.<br>No puedes asignar ${requerido}.`,
            icon: 'warning'
        });
        return;
    }

    if (filaExistente) {
        filaExistente.querySelector('.linea-cant').value = Math.round((cantActual + cantidad) * 100) / 100;
        recalcularTotalEdicion();
        document.getElementById('edit-cant-prod').value = 1;
        return;
    }

    const filaVacia = document.getElementById('fila-vacia-edit');
    if (filaVacia) filaVacia.remove();

    document.getElementById('edit-productos-body').insertAdjacentHTML('beforeend', filaEdicionHtml(id, nombre, cantidad, unidad, precio));

    document.getElementById('edit-cant-prod').value            = 1;
    inputEditTexto.value                                       = '';
    inputEditTexto.dataset.stock                               = '';
    inputEditTexto.dataset.nombreLimpio                        = '';
    document.getElementById('edit-prod-id').value              = '';
    document.getElementById('edit-prod-precio').value          = '';
    recalcularTotalEdicion();
    lucide.createIcons();
}

function eliminarFilaProducto(btn) {
    btn.closest('tr').remove();
    const body = document.getElementById('edit-productos-body');
    if (!body.querySelector('tr')) {
        body.innerHTML = '<tr id="fila-vacia-edit"><td colspan="6" class="text-center py-4 text-gray-400">No hay productos</td></tr>';
    }
    recalcularTotalEdicion();
}

function recalcularTotalEdicion() {
    let total = 0;
    document.querySelectorAll('#edit-productos-body tr[data-producto-id]').forEach(row => {
        const cant = parseFloat(row.querySelector('.linea-cant').value) || 0;
        const prec = parseFloat(row.querySelector('.linea-precio').value) || 0;
        const sub  = Math.round(cant * prec * 100) / 100;
        row.querySelector('.subtotal').textContent = '$' + sub.toFixed(2);
        total += sub;
    });
    document.getElementById('edit-total-modal').textContent = '$' + total.toFixed(2);
}

function guardarEdicionPedido() {
    const form     = document.getElementById('formEditarPedido');
    const formData = new FormData(form);

    const clienteId    = document.getElementById('edit-cliente-id').value;
    const repartidorId = document.getElementById('edit-repartidor-id').value;
    formData.set('id_cliente',    clienteId);
    formData.set('id_repartidor', repartidorId);

    const productos = [];
    let lineaInvalida = null;
    document.querySelectorAll('#edit-productos-body tr[data-producto-id]').forEach(row => {
        const cantidad = parseFloat(row.querySelector('.linea-cant').value);
        const precio   = parseFloat(row.querySelector('.linea-precio').value);
        if (isNaN(cantidad) || cantidad <= 0 || isNaN(precio) || precio <= 0) {
            lineaInvalida = lineaInvalida || row.cells[0].textContent.trim();
            return;
        }
        productos.push({ id: row.dataset.productoId, cantidad, precio, unidad: row.dataset.unidad });
    });

    if (!clienteId)             { Swal.fire('Error', 'Seleccione un cliente', 'error');         return; }
    if (lineaInvalida)          { Swal.fire('Revisa el pedido', `"${lineaInvalida}" necesita cantidad y precio mayores a $0.`, 'error'); return; }
    if (productos.length === 0) { Swal.fire('Error', 'Agregue al menos un producto', 'error'); return; }

    formData.append('productos', JSON.stringify(productos));
    formData.append('total', parseFloat(document.getElementById('edit-total-modal').textContent.replace('$', '')) || 0);

    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(BASE_URL + 'pedidos/actualizar', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) Swal.fire('¡Éxito!', 'Pedido actualizado correctamente', 'success').then(() => location.reload());
        else Swal.fire('Error', data.message || 'Error al actualizar', 'error');
    })
    .catch(() => { Swal.close(); Swal.fire('Error', 'Error de conexión', 'error'); });
}

function cerrarModalEditar(event) {
    if (!event || event.target === document.getElementById('modalEditar')) {
        document.getElementById('modalEditar').classList.add('hidden');
        document.getElementById('modalEditar').classList.remove('flex');
    }
}

function abrirModalAbono(idPedido, deudaActual) {
    document.getElementById('abono_id_pedido').value           = idPedido;
    document.getElementById('abono_monto').value               = '';
    document.getElementById('abono_fecha').value               = new Date().toISOString().slice(0, 10);
    document.getElementById('abono_deuda_display').textContent = '$' + parseFloat(deudaActual).toFixed(2);
    document.getElementById('abono_monto').max                 = deudaActual;
    document.getElementById('modalAbono').classList.remove('hidden');
    document.getElementById('modalAbono').classList.add('flex');
}

async function registrarAbono() {
    const idPedido = document.getElementById('abono_id_pedido').value;
    const monto    = parseFloat(document.getElementById('abono_monto').value);
    const fecha    = document.getElementById('abono_fecha').value;

    if (!monto || monto <= 0) { Swal.fire('Error', 'Ingresa un monto válido mayor a 0', 'error'); return; }
    if (!fecha)               { Swal.fire('Error', 'Selecciona una fecha de abono', 'error');       return; }

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res  = await fetch(BASE_URL + 'pedidos/registrarAbono', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id_pedido: idPedido, monto, fecha_abono: fecha }),
        });
        const data = await res.json();
        Swal.close();
        if (data.success) {
            cerrarModalAbono();
            Swal.fire('¡Éxito!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'No se pudo registrar el abono', 'error');
        }
    } catch (e) {
        Swal.close();
        console.error(e);
        Swal.fire('Error', 'Error de conexión al servidor', 'error');
    }
}

function cerrarModalAbono(event) {
    if (!event || event.target === document.getElementById('modalAbono')) {
        document.getElementById('modalAbono').classList.add('hidden');
        document.getElementById('modalAbono').classList.remove('flex');
    }
}

function abrirModalNuevoPedido() {
    productosSeleccionados = [];
    renderizarProductos();
    document.getElementById('np-fecha_pedido').value       = new Date().toISOString().slice(0, 10);
    document.getElementById('np-modalidad').value          = 'contado';
    document.getElementById('np-pago-inicial').value       = '0';
    actualizarPagoNuevo();
    document.getElementById('np-cliente').value            = '';
    document.getElementById('np-cliente-texto').value      = '';
    document.getElementById('np-repartidor').value         = '';
    document.getElementById('np-repartidor-texto').value   = '';
    document.getElementById('np-fecha_entrega').value      = '';
    document.getElementById('np-horario').value            = '';
    const inputProdTexto                                   = document.getElementById('np-sel-prod-texto');
    inputProdTexto.value                                   = '';
    inputProdTexto.dataset.stock                           = '';
    inputProdTexto.dataset.nombreLimpio                    = '';
    document.getElementById('np-prod-id').value            = '';
    document.getElementById('np-prod-precio').value        = '';
    document.getElementById('modalNuevoPedido').classList.remove('hidden');
    document.getElementById('modalNuevoPedido').classList.add('flex');
}

function cerrarModalNuevoPedido(event) {
    if (!event || event.target === document.getElementById('modalNuevoPedido')) {
        document.getElementById('modalNuevoPedido').classList.add('hidden');
        document.getElementById('modalNuevoPedido').classList.remove('flex');
    }
}

function agregarFilaProducto() {
    const id              = document.getElementById('np-prod-id').value;
    const precio          = parseFloat(document.getElementById('np-prod-precio').value);
    const cant            = parseFloat(document.getElementById('np-cant-prod').value);
    const unidad          = document.getElementById('np-unidad-prod').value;
    const inputProdTexto  = document.getElementById('np-sel-prod-texto');
    const nombre          = inputProdTexto.dataset.nombreLimpio || inputProdTexto.value.trim();
    const stockDisponible = parseFloat(inputProdTexto.dataset.stock || 0);

    if (!id)                                      { Swal.fire('Error', 'Seleccione un producto de la lista', 'error'); return; }
    if (isNaN(cant) || cant <= 0)                 { Swal.fire('Error', 'Cantidad inválida', 'error'); return; }
    if (!precio || isNaN(precio) || precio <= 0)  { Swal.fire('Error', 'Ingresa el precio para este producto', 'error'); return; }

    const existente               = productosSeleccionados.find(p => p.id === id);
    const cantidadTotalSolicitada = (existente ? existente.cant : 0) + cant;

    if (cantidadTotalSolicitada > stockDisponible) {
        Swal.fire({
            title: 'Stock Insuficiente',
            html: `Solo tienes <b>${stockDisponible}</b> unidades disponibles de ${nombre}.<br>No puedes agregar ${cantidadTotalSolicitada}.`,
            icon: 'warning'
        });
        return;
    }

    if (existente) {
        existente.cant += cant;
        existente.sub   = existente.cant * existente.precio;
    } else {
        productosSeleccionados.push({ id, nombre, cant, precio, unidad, sub: precio * cant });
    }

    document.getElementById('np-cant-prod').value   = 1;
    inputProdTexto.value                            = '';
    inputProdTexto.dataset.stock                    = '';
    inputProdTexto.dataset.nombreLimpio             = '';
    document.getElementById('np-prod-id').value     = '';
    document.getElementById('np-prod-precio').value = '';
    renderizarProductos();
}

function eliminarProducto(index) {
    productosSeleccionados.splice(index, 1);
    renderizarProductos();
}

function cambiarPrecioNuevo(index, valor) {
    const p      = productosSeleccionados[index];
    const precio = parseFloat(valor);
    if (!p || isNaN(precio) || precio <= 0) {
        Swal.fire('Precio no válido', 'El precio debe ser mayor a $0.', 'warning');
        renderizarProductos();
        return;
    }
    p.precio = Math.round(precio * 100) / 100;
    p.sub    = p.cant * p.precio;
    renderizarProductos();
}

function renderizarProductos() {
    const body = document.getElementById('lista-productos-body');
    if (!body) return;
    if (productosSeleccionados.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-400">No hay productos agregados</td></tr>';
        const tm = document.getElementById('total-modal');
        if (tm) tm.textContent = '$0.00';
        actualizarPagoNuevo();
        return;
    }
    let html = '', total = 0;
    productosSeleccionados.forEach((p, i) => {
        total += p.sub;
        html  += `<tr class="border-b">
            <td class="py-2 text-left font-medium">${escHtml(p.nombre)}</td>
            <td class="py-2 text-center">${p.cant}</td>
            <td class="py-2 text-center">${escHtml(p.unidad)}</td>
            <td class="py-2 text-right">
                <input type="number" value="${p.precio.toFixed(2)}" min="0.01" step="0.01"
                    class="w-24 p-1 border rounded text-right border-amber-300 bg-amber-50"
                    aria-label="Precio unitario de ${escHtml(p.nombre)}"
                    onchange="cambiarPrecioNuevo(${i}, this.value)">
            </td>
            <td class="py-2 text-right font-bold">$${p.sub.toFixed(2)}</td>
            <td class="py-2 text-center">
                <button type="button" onclick="eliminarProducto(${i})" aria-label="Quitar ${escHtml(p.nombre)}" class="text-rose-500 hover:text-rose-700">
                    <i data-lucide="trash-2" class="w-4 h-4" aria-hidden="true"></i>
                </button>
            </td>
        </tr>`;
    });
    body.innerHTML = html;
    const tm = document.getElementById('total-modal');
    if (tm) tm.textContent = `$${total.toFixed(2)}`;
    actualizarPagoNuevo();
    lucide.createIcons();
}

/** Nuevo pedido: A crédito muestra el pago inicial (desde $0); De contado lo oculta y toma el total como pagado. */
function actualizarPagoNuevo() {
    const box = document.getElementById('np-pago-box');
    if (!box) return;

    const credito = document.getElementById('np-modalidad').value === 'credito';
    box.classList.toggle('hidden', !credito);

    const total = productosSeleccionados.reduce((s, p) => s + p.sub, 0);
    const input = document.getElementById('np-pago-inicial');
    input.max = total;
    if (!credito) input.value = '0';

    const inicial = parseFloat(input.value) || 0;
    document.getElementById('np-saldo').textContent = '$' + Math.max(0, total - inicial).toFixed(2);
}

async function guardarNuevoPedido() {
    const clienteId    = document.getElementById('np-cliente').value;
    const clienteTexto = document.getElementById('np-cliente-texto').value.trim();
    const repId        = document.getElementById('np-repartidor').value;
    const repTexto     = document.getElementById('np-repartidor-texto').value.trim();

    if (!clienteId && clienteTexto) {
        Swal.fire('Cliente no válido', 'Debes seleccionar un cliente registrado de la lista. Si no existe, regístralo primero.', 'error'); return;
    }
    if (!clienteId) {
        Swal.fire('Error', 'Seleccione un cliente de la lista', 'error'); return;
    }
    if (repTexto && !repId) {
        Swal.fire('Repartidor no válido', 'Debes seleccionar un repartidor registrado de la lista. Si no existe, regístralo primero.', 'error'); return;
    }
    if (productosSeleccionados.length === 0) { Swal.fire('Error', 'Agregue al menos un producto', 'error'); return; }

    const total   = productosSeleccionados.reduce((s, p) => s + p.sub, 0);
    const credito = document.getElementById('np-modalidad').value === 'credito';
    const inicial = credito ? (parseFloat(document.getElementById('np-pago-inicial').value) || 0) : total;

    if (credito && inicial < 0)               { Swal.fire('Error', 'El pago inicial no puede ser negativo', 'error'); return; }
    if (credito && inicial > total + 0.001)   { Swal.fire('Error', 'El pago inicial no puede superar el total del pedido', 'error'); return; }

    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const res = await fetch(BASE_URL + 'pedidos/guardar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                id_cliente:     clienteId,
                id_repartidor:  repId,
                modalidad_pago: document.getElementById('np-modalidad').value,
                pago_inicial:   credito ? inicial : 0,
                total,
                fecha_pedido:   document.getElementById('np-fecha_pedido').value,
                fecha_entrega:  document.getElementById('np-fecha_entrega').value,
                horario:        document.getElementById('np-horario').value,
                productos:      JSON.stringify(productosSeleccionados.map(p => ({ id: p.id, cantidad: p.cant, precio: p.precio, unidad: p.unidad }))),
            }),
        });
        const data = await res.json();
        Swal.close();
        if (data.success) {
            cerrarModalNuevoPedido();
            Swal.fire('¡Éxito!', 'Pedido guardado correctamente', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'Error al guardar', 'error');
        }
    } catch (e) {
        Swal.close();
        console.error(e);
        Swal.fire('Error', 'Error de conexión al servidor', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    inicializarBuscador();
    lucide.createIcons();

    document.getElementById('np-modalidad').addEventListener('change', actualizarPagoNuevo);
    document.getElementById('np-pago-inicial').addEventListener('input', actualizarPagoNuevo);

    crearAutocomplete('np-cliente-texto',    'np-cliente',    'np-cliente-lista',    CLIENTES_DATA,     avisoClientePreferencial, 'Se debe registrar primero al cliente');
    crearAutocomplete('np-repartidor-texto', 'np-repartidor', 'np-repartidor-lista', REPARTIDORES_DATA, null, 'Se debe registrar primero al repartidor');
    crearAutocomplete('np-sel-prod-texto',   'np-prod-id',    'np-prod-lista',       PRODUCTOS_DATA,    (item) => {
        document.getElementById('np-prod-precio').value = precioSugerido(item.precio, document.getElementById('np-cliente').value);
        const inputProdTexto = document.getElementById('np-sel-prod-texto');
        inputProdTexto.dataset.stock        = item.stock;
        inputProdTexto.dataset.nombreLimpio = item.nombre_limpio;
    }, 'No hay productos activos o con stock');
});