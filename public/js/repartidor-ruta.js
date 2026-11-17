/**
 * Panel "Ruta sugerida" del repartidor (Dijkstra en el servidor).
 * Pide /repartidores/ruta (con el GPS si el repartidor lo permite) y dibuja
 * las paradas en orden + botón para abrir la ruta en Google Maps.
 */
(function () {
    let cargando = false;
    let posicion = null;      // { lat, lng, t }
    const GPS_VIGENCIA_MS = 60000;

    function esc(t) {
        const d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    function obtenerPosicion() {
        return new Promise(resolve => {
            if (posicion && Date.now() - posicion.t < GPS_VIGENCIA_MS) return resolve(posicion);
            if (!navigator.geolocation) return resolve(null);
            navigator.geolocation.getCurrentPosition(
                p => {
                    posicion = { lat: p.coords.latitude, lng: p.coords.longitude, t: Date.now() };
                    resolve(posicion);
                },
                () => resolve(null),                       // sin permiso / sin HTTPS: se usa la tienda
                { timeout: 4000, maximumAge: GPS_VIGENCIA_MS }
            );
        });
    }

    function pintar(data) {
        const panel = document.getElementById('panel-ruta');
        if (!panel) return;

        const r = data.ruta;
        if (!r || !r.hay_ruta) {
            const aviso = (r && r.sin_coordenadas && r.sin_coordenadas.length)
                ? `Sin ubicación para los pedidos: ${r.sin_coordenadas.map(i => '#' + i).join(', ')}.`
                : '';
            panel.innerHTML = aviso
                ? `<p class="text-xs text-gray-500">${esc(aviso)}</p>`
                : '';
            return;
        }

        const salida = data.origen_gps ? 'tu ubicación' : 'la tienda';
        const paradas = r.paradas.map(p => `
            <li class="flex items-start gap-3 py-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-600 text-xs font-extrabold text-white">${p.orden}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900">Pedido #${p.id_pedido} · ${esc(p.cliente_nombre)}
                        <span class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${p.motivo === 'zona' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700'}">
                            ${p.motivo === 'zona' ? 'Misma zona' : 'De paso'}
                        </span>
                    </p>
                    <p class="truncate text-xs text-gray-600">${esc(p.direccion)}</p>
                </div>
                <span class="whitespace-nowrap text-xs font-semibold text-gray-500">+${p.km_tramo} km</span>
            </li>`).join('');

        const nota = r.truncada
            ? '<p class="mt-2 text-[11px] text-amber-700">Google Maps admite 10 paradas: el enlace incluye las primeras 10.</p>'
            : '';
        const sinCoord = (r.sin_coordenadas && r.sin_coordenadas.length)
            ? `<p class="mt-2 text-[11px] text-gray-500">Fuera de la ruta por no tener ubicación: ${r.sin_coordenadas.map(i => '#' + i).join(', ')}.</p>`
            : '';

        panel.innerHTML = `
            <div class="rounded-2xl border border-rose-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-extrabold text-gray-900">Ruta sugerida</h2>
                        <p class="text-xs text-gray-600">${esc(r.motivo)} Sales desde ${salida} · ≈ ${r.distancia_km} km
                            (${r.metodo === 'exacto' ? 'orden óptimo' : 'orden aproximado'}).</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" id="btn-recalcular-ruta"
                                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:border-rose-300 hover:text-rose-600">
                            Recalcular desde mi ubicación
                        </button>
                        <a href="${esc(r.google_url)}" target="_blank" rel="noopener noreferrer"
                           class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-700">
                            Ver ruta en Google Maps
                        </a>
                    </div>
                </div>
                <ol class="mt-3 divide-y divide-gray-100">${paradas}</ol>
                ${nota}${sinCoord}
            </div>`;

        document.getElementById('btn-recalcular-ruta')?.addEventListener('click', () => {
            posicion = null;
            cargarRutaRepartidor();
        });
    }

    async function cargarRutaRepartidor() {
        if (cargando) return;
        cargando = true;
        try {
            const pos = await obtenerPosicion();
            const qs = pos ? `?lat=${pos.lat}&lng=${pos.lng}` : '';
            const res = await fetch(BASE_URL + 'repartidores/ruta' + qs, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (data.ok) pintar(data);
        } catch (e) {
            console.error('No se pudo calcular la ruta:', e);
        } finally {
            cargando = false;
        }
    }

    // ───────────── Ventana "Iniciar recorrido" ─────────────
    let recIds = [];          // pedidos incluidos en el recorrido
    let recCargando = false;

    function asegurarModal() {
        let m = document.getElementById('modal-recorrido');
        if (m) return m;
        m = document.createElement('div');
        m.id = 'modal-recorrido';
        m.className = 'fixed inset-0 z-[80] hidden items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4';
        m.innerHTML = '<div id="modal-recorrido-caja" class="max-h-[90vh] w-full overflow-y-auto rounded-t-2xl bg-white p-4 shadow-xl sm:max-w-lg sm:rounded-2xl"></div>';
        m.addEventListener('click', e => { if (e.target === m) cerrarRecorrido(); });
        document.body.appendChild(m);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarRecorrido(); });
        return m;
    }

    function cerrarRecorrido() {
        const m = document.getElementById('modal-recorrido');
        if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
        recIds = [];
    }

    function direccionDe(id) {
        const lista = (typeof DATA_ASIGNADOS !== 'undefined') ? DATA_ASIGNADOS : [];
        const p = lista.find(x => Number(x.id_pedido) === Number(id));
        return p ? p.direccion : '';
    }

    function pintarRecorrido(data, pos) {
        const caja = document.getElementById('modal-recorrido-caja');
        const r = data.ruta;
        const salida = data.origen_gps ? 'tu ubicación' : 'la tienda';
        const cab = `
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-gray-900">Recorrido</h2>
                    <p class="text-xs text-gray-600">Ruta más corta (Dijkstra) saliendo desde ${salida}.</p>
                </div>
                <button type="button" id="rec-cerrar" class="rounded-lg px-2 py-1 text-lg font-bold text-gray-500 hover:bg-gray-100" aria-label="Cerrar">&times;</button>
            </div>`;

        let cuerpo = '';
        if (r && r.hay_ruta) {
            const filas = r.paradas.map(p => `
                <li class="flex items-start gap-3 py-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-extrabold text-white">${p.orden}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-gray-900">Pedido #${p.id_pedido} · ${esc(p.cliente_nombre)}</p>
                        <p class="text-xs text-gray-600">${esc(p.direccion)}</p>
                    </div>
                    <div class="text-right">
                        <span class="block whitespace-nowrap text-xs font-semibold text-gray-500">+${p.km_tramo} km</span>
                        ${recIds.length > 1 ? `<button type="button" data-quitar="${p.id_pedido}" class="text-[11px] font-bold text-rose-600 hover:underline">Quitar</button>` : ''}
                    </div>
                </li>`).join('');

            const cercanos = (data.cercanos || []).map(c => `
                <li class="flex items-center gap-3 py-2">
                    <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900">Pedido #${c.id_pedido} · ${esc(c.cliente_nombre)}
                        ${c.disponible ? '<span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Sin asignar</span>' : ''}
                    </p>
                    <p class="text-xs text-gray-600">${esc(c.direccion)}</p>
                    <p class="text-[11px] font-semibold text-amber-700">A ${c.dist_parada_km} km de tu ruta · desvío ≈ ${c.desvio_km} km</p>
                </div>
                <button type="button" data-agregar="${c.id_pedido}" data-disponible="${c.disponible ? 1 : 0}"
                        class="rounded-xl bg-amber-500 px-3 py-2 text-xs font-bold text-white hover:bg-amber-600 disabled:opacity-50">${c.disponible ? 'Tomar y agregar' : 'Agregar'}</button>
                </li>`).join('');

            cuerpo = `
                <ol class="divide-y divide-gray-100">${filas}</ol>
                <p class="mt-2 text-xs font-semibold text-gray-700">Total ≈ ${r.distancia_km} km ${r.metodo === 'exacto' ? '(orden óptimo)' : '(orden aproximado)'}</p>
                ${r.truncada ? '<p class="mt-1 text-[11px] text-amber-700">Google Maps admite 10 paradas: el enlace incluye las primeras 10.</p>' : ''}
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-extrabold uppercase text-amber-800">Pedidos cerca de tu camino</p>
                    ${cercanos
                        ? `<ul class="divide-y divide-amber-100">${cercanos}</ul>`
                        : '<p class="mt-1 text-xs text-amber-800">Por ahora no hay otros pedidos cerca de esta ruta.</p>'}
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="${esc(r.google_url)}" target="_blank" rel="noopener noreferrer"
                       class="flex-1 rounded-xl bg-indigo-600 px-4 py-3 text-center text-sm font-bold text-white shadow-sm hover:bg-indigo-700">
                        Abrir en Google Maps
                    </a>
                    <button type="button" id="rec-ubicacion"
                            class="rounded-xl border border-gray-200 bg-white px-3 py-3 text-xs font-bold text-gray-700 hover:border-indigo-300">
                        Actualizar mi ubicación
                    </button>
                </div>`;
        } else {
            // No se pudo ubicar la dirección en el mapa: se abre Google Maps por texto de dirección.
            const dir = direccionDe(recIds[0]);
            const origen = pos ? `&origin=${pos.lat},${pos.lng}` : '';
            const url = `https://www.google.com/maps/dir/?api=1${origen}&destination=${encodeURIComponent(dir)}&travelmode=driving`;
            cuerpo = `
                <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">No pude ubicar con precisión la dirección del pedido #${recIds[0]}, así que no hay ruta con Dijkstra.
                Puedes abrirla en Google Maps por dirección.</p>
                <a href="${esc(url)}" target="_blank" rel="noopener noreferrer"
                   class="mt-3 block rounded-xl bg-indigo-600 px-4 py-3 text-center text-sm font-bold text-white hover:bg-indigo-700">Abrir en Google Maps</a>`;
        }

        caja.innerHTML = cab + cuerpo;
        caja.querySelector('#rec-cerrar')?.addEventListener('click', cerrarRecorrido);
        caja.querySelector('#rec-ubicacion')?.addEventListener('click', () => { posicion = null; cargarRecorrido(); });
        caja.querySelectorAll('[data-agregar]').forEach(b => b.addEventListener('click', async () => {
            const id = Number(b.dataset.agregar);
            if (b.dataset.disponible === '1') {          // nadie lo ha tomado: primero se asigna a este repartidor
                b.disabled = true;
                if (!(await tomarPedidoRecorrido(id))) { b.disabled = false; return; }
            }
            recIds.push(id);
            cargarRecorrido();
        }));
    }
    async function tomarPedidoRecorrido(idPedido) {
        try {
            const res = await fetch(BASE_URL + 'repartidores/tomar-pedido', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id_pedido: idPedido })
            });
            const data = await res.json();
            if (data.ok) {
                if (window.sincronizarPedidos) window.sincronizarPedidos(false);   // refresca las tarjetas de fondo
                return true;
            }
            if (window.Swal) Swal.fire('Atención', data.mensaje || 'No se pudo tomar el pedido.', 'warning');
            return false;
        } catch (e) {
            if (window.Swal) Swal.fire('Error', 'Error de conexión.', 'error');
            return false;
        }
    }
    async function cargarRecorrido() {
        if (recCargando) return;
        recCargando = true;
        const caja = document.getElementById('modal-recorrido-caja');
        caja.innerHTML = '<p class="p-6 text-center text-sm font-semibold text-gray-600">Calculando la ruta más corta…</p>';
        try {
            const pos = await obtenerPosicion();
            const qs = `?pedidos=${recIds.join(',')}` + (pos ? `&lat=${pos.lat}&lng=${pos.lng}` : '');
            const res = await fetch(BASE_URL + 'repartidores/ruta' + qs, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error('respuesta inválida');
            pintarRecorrido(data, pos);
        } catch (e) {
            console.error(e);
            caja.innerHTML = '<p class="p-6 text-center text-sm font-semibold text-rose-700">No se pudo calcular el recorrido. Intenta de nuevo.</p>'
                + '<button type="button" id="rec-cerrar" class="mx-auto block rounded-lg px-3 py-2 text-xs font-bold text-gray-600">Cerrar</button>';
            caja.querySelector('#rec-cerrar')?.addEventListener('click', cerrarRecorrido);
        } finally {
            recCargando = false;
        }
    }

    function iniciarRecorrido(idPedido) {
        const m = asegurarModal();
        m.classList.remove('hidden');
        m.classList.add('flex');
        recIds = [Number(idPedido)];
        cargarRecorrido();
    }

    window.iniciarRecorrido = iniciarRecorrido;

    window.cargarRutaRepartidor = cargarRutaRepartidor;
    document.addEventListener('DOMContentLoaded', cargarRutaRepartidor);
})();