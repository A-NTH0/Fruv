/**
 * Panel de ruta del repartidor (orden de paradas Fruv + calles de OpenStreetMap/OSRM).
 * Pide /repartidores/ruta (con el GPS si el repartidor lo permite) y dibuja
 * una sola secuencia de paradas.
 */
(function () {
    let cargando = false;
    let posicion = null;      // { lat, lng, t }
    const GPS_VIGENCIA_MS = 60000;
    let rutaSeguimiento = null;
    let temporizadorDesvio = null;
    let verificandoDesvio = false;
    let leafletPromise = null;
    const OSRM_URL = 'https://router.project-osrm.org';

    function cargarLeaflet() {
        if (window.L) return Promise.resolve();
        if (!leafletPromise) {
            const css = document.querySelector('link[data-leaflet]') || document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            css.dataset.leaflet = 'true';
            const cssPromise = css.sheet ? Promise.resolve() : new Promise((resolve, reject) => {
                css.addEventListener('load', resolve, { once: true });
                css.addEventListener('error', () => reject(new Error('No se pudo cargar el estilo del mapa.')), { once: true });
                if (!css.isConnected) document.head.appendChild(css);
            });
            const scriptPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.async = true;
                script.onload = resolve;
                script.onerror = () => reject(new Error('No se pudo cargar Leaflet.'));
                document.head.appendChild(script);
            });
            leafletPromise = Promise.all([cssPromise, scriptPromise])
                .then(() => {
                    if (!window.L) throw new Error('Leaflet no quedó disponible en el navegador.');
                })
                .catch(error => {
                    leafletPromise = null;
                    throw error;
                });
        }
        return leafletPromise;
    }

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

        const sinCoord = (r.sin_coordenadas && r.sin_coordenadas.length)
            ? `<p class="mt-2 text-[11px] text-gray-500">Fuera de la ruta por no tener ubicación: ${r.sin_coordenadas.map(i => '#' + i).join(', ')}.</p>`
            : '';

        panel.innerHTML = `
            <div class="rounded-2xl border border-rose-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-extrabold text-gray-900">Ruta sugerida</h2>
                        <p class="text-xs text-gray-600">${esc(r.motivo)} Sales desde ${salida} · ≈ ${r.distancia_km} km
                            (${r.metodo === 'exacto' ? 'orden exacto sobre distancias estimadas' : 'orden aproximado'}).</p>
                    </div>
                    <div>
                        <button type="button" id="btn-recalcular-ruta"
                                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:border-rose-300 hover:text-rose-600">
                            Recalcular desde mi ubicación
                        </button>
                    </div>
                </div>
                <ol class="mt-3 divide-y divide-gray-100">${paradas}</ol>
                <p class="mt-2 text-[11px] text-gray-500">Pulsa “Iniciar recorrido” en un pedido asignado para abrir el mapa vial y las instrucciones.</p>
                ${sinCoord}
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
            if (data.ok) {
                pintar(data);
                if (window.rutaMapaPintar) window.rutaMapaPintar(data);   // mapa fijo (repartidor-mapa.js)
            }
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
        rutaSeguimiento?.map?.remove();
        clearInterval(temporizadorDesvio);
        temporizadorDesvio = null;
        rutaSeguimiento = null;
        recIds = [];
    }

    function pintarRecorrido(data) {
        const caja = document.getElementById('modal-recorrido-caja');
        rutaSeguimiento?.map?.remove();
        const r = data.ruta;
        const salida = data.origen_gps ? 'tu ubicación' : 'la tienda';
        const cab = `
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-gray-900">Recorrido</h2>
                    <p class="text-xs text-gray-600">Las paradas van en el orden de la ruta más rápida desde ${salida}. Incluye todas las entregas elegidas; usa “Quitar” para dejar alguna fuera.</p>
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

            // Mis pedidos activos que no quedaron en la ruta ni "de paso": se pueden sumar cuando quiera.
            const misOtros = (data.otros || []).filter(o => !o.disponible).map(o => `
                <li class="flex items-center gap-3 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-gray-900">Pedido #${o.id_pedido} · ${esc(o.cliente_nombre)}</p>
                        <p class="text-xs text-gray-600">${esc(o.direccion)}</p>
                    </div>
                    <button type="button" data-agregar="${o.id_pedido}" data-disponible="0"
                            class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">Agregar</button>
                </li>`).join('');
            const totalOtros = (data.otros || []).length;
            const sinUbic = (data.sin_ubicacion || []).length;

            cuerpo = `
                <ol class="divide-y divide-gray-100">${filas}</ol>
                <p id="resumen-ruta-vial" class="mt-2 text-xs font-semibold text-gray-700">Calculando la ruta por calles…</p>
                <div id="mapa-recorrido" class="mt-3 h-72 w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-100 sm:h-96" role="application" aria-label="Mapa del recorrido">
                    <p class="p-4 text-sm text-gray-600">Cargando el mapa y la ruta vial…</p>
                </div>
                <label class="mt-2 flex items-center gap-2 text-xs font-semibold text-gray-700">
                    <input type="checkbox" id="rec-ver-otros" checked>
                    Mostrar los demás pedidos en el mapa (${totalOtros})
                </label>
                <p class="mt-1 text-[10px] text-gray-500">S: salida · números: pedidos incluidos · C azul: tuyo, de paso · + ámbar: sin asignar, de paso (se puede tomar) · punto azul claro: tuyo, fuera de ruta · punto gris: sin asignar, lejos de tu ruta (solo informativo). © OpenStreetMap.</p>
                ${sinUbic ? `<p class="mt-1 text-[10px] text-gray-500">${sinUbic} pedido(s) aún sin ubicación en el mapa: ${(data.sin_ubicacion || []).map(i => '#' + i).join(', ')}.</p>` : ''}
                <div id="estado-desvio" class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-800" role="status" aria-live="polite">
                    Esperando la ruta vial para verificar tu ubicación…
                </div>
                <details class="mt-3 rounded-xl border border-gray-200 p-3">
                    <summary class="cursor-pointer text-sm font-bold text-gray-800">Instrucciones paso a paso</summary>
                    <ol id="pasos-ruta-vial" class="mt-2 list-inside list-decimal space-y-2 text-xs text-gray-700"></ol>
                </details>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-extrabold uppercase text-amber-800">Pedidos cerca de tu camino</p>
                    ${cercanos
                        ? `<ul class="divide-y divide-amber-100">${cercanos}</ul>`
                        : '<p class="mt-1 text-xs text-amber-800">Por ahora no hay otros pedidos cerca de esta ruta.</p>'}
                </div>
                ${misOtros ? `
                <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-3">
                    <p class="text-xs font-extrabold uppercase text-blue-800">Tus otros pedidos activos</p>
                    <ul class="divide-y divide-blue-100">${misOtros}</ul>
                </div>` : ''}
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" id="rec-ubicacion"
                            class="rounded-xl border border-gray-200 bg-white px-3 py-3 text-xs font-bold text-gray-700 hover:border-indigo-300">
                        Actualizar mi ubicación
                    </button>
                </div>`;
        } else {
            cuerpo = `
                <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">No pude ubicar con precisión la dirección del pedido #${recIds[0]}, así que no hay ruta con Dijkstra.
                Verifica que el pedido tenga una dirección con coordenadas antes de iniciar el recorrido.</p>`;
        }

        caja.innerHTML = cab + cuerpo;
        rutaSeguimiento = r && r.hay_ruta ? {
            origen: r.origen,
            paradas: r.paradas,
            progreso: 0,
            tramos: null,
            cercanos: data.cercanos || [],
            otros: data.otros || []
        } : null;
        clearInterval(temporizadorDesvio);
        temporizadorDesvio = null;
        if (rutaSeguimiento) {
            calcularRutaVial(r, rutaSeguimiento, data.cercanos || []);
        }
        caja.querySelector('#rec-cerrar')?.addEventListener('click', cerrarRecorrido);
        caja.querySelector('#rec-ubicacion')?.addEventListener('click', () => { posicion = null; cargarRecorrido(); });
        caja.querySelectorAll('[data-quitar]').forEach(b => b.addEventListener('click', () => {
            if (recIds.length <= 1) return;
            recIds = recIds.filter(id => id !== Number(b.dataset.quitar));
            cargarRecorrido();
        }));
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

    function distanciaKm(a, b) {
        const radio = 6371.0088;
        const dLat = (b.lat - a.lat) * Math.PI / 180;
        const dLng = (b.lng - a.lng) * Math.PI / 180;
        const seno = Math.sin(dLat / 2) ** 2
            + Math.cos(a.lat * Math.PI / 180) * Math.cos(b.lat * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
        return 2 * radio * Math.asin(Math.min(1, Math.sqrt(seno)));
    }

    function distanciaPuntoTramo(p, a, b) {
        const largo = distanciaKm(a, b);
        if (largo < 0.001) return distanciaKm(p, a);
        const aP = distanciaKm(a, p);
        const bP = distanciaKm(b, p);
        const t = Math.max(0, Math.min(1, (aP * aP + largo * largo - bP * bP) / (2 * largo * largo)));
        return distanciaKm(p, {
            lat: a.lat + t * (b.lat - a.lat),
            lng: a.lng + t * (b.lng - a.lng)
        });
    }

    function distanciaARecorrido(pos, puntos) {
        if (!puntos || puntos.length === 0) return Infinity;
        if (puntos.length === 1) return distanciaKm(pos, puntos[0]);
        let distancia = Infinity;
        for (let i = 0; i < puntos.length - 1; i++) {
            distancia = Math.min(distancia, distanciaPuntoTramo(pos, puntos[i], puntos[i + 1]));
        }
        return distancia;
    }

    function crearIcono(etiqueta, color) {
        return L.divIcon({
            className: '',
            html: `<span style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;border:2px solid #fff;border-radius:50%;background:${color};box-shadow:0 1px 5px #0008;color:#fff;font-size:12px;font-weight:800">${esc(etiqueta)}</span>`,
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
    }

    function popupPedido(pedido, subtitulo) {
        const contenido = document.createElement('div');
        const titulo = document.createElement('strong');
        titulo.textContent = pedido.id_pedido === 'Salida' ? 'Salida' : `Pedido #${pedido.id_pedido}`;
        contenido.appendChild(titulo);
        if (pedido.cliente_nombre) {
            const cliente = document.createElement('div');
            cliente.textContent = pedido.cliente_nombre;
            contenido.appendChild(cliente);
        }
        if (pedido.direccion) {
            const direccion = document.createElement('div');
            direccion.textContent = pedido.direccion;
            contenido.appendChild(direccion);
        }
        const estado = document.createElement('div');
        estado.textContent = subtitulo;
        contenido.appendChild(estado);
        return contenido;
    }

    function puntosDeCoordenadas(coordenadas) {
        return (coordenadas || []).map(([lng, lat]) => ({ lat, lng }));
    }

    function instruccionOSRM(paso) {
        const maniobra = paso.maneuver || {};
        const direcciones = {
            left: 'a la izquierda',
            right: 'a la derecha',
            slight_left: 'ligeramente a la izquierda',
            slight_right: 'ligeramente a la derecha',
            sharp_left: 'cerrado a la izquierda',
            sharp_right: 'cerrado a la derecha',
            straight: 'de frente',
            uturn: 'en U'
        };
        const giro = direcciones[String(maniobra.modifier || '').replace(' ', '_')] || '';
        let texto;
        if (maniobra.type === 'arrive') texto = 'Llegaste al destino';
        else if (maniobra.type === 'depart') texto = 'Sal';
        else if (maniobra.type === 'roundabout' || maniobra.type === 'rotary') {
            texto = `En la glorieta toma la salida ${maniobra.exit || ''}`.trim();
        } else if (maniobra.type === 'turn' || maniobra.type === 'end of road' || maniobra.type === 'fork') {
            texto = `Gira ${giro || 'en la intersección'}`;
        } else if (maniobra.type === 'merge' || maniobra.type === 'on ramp' || maniobra.type === 'off ramp') {
            texto = `Incorpórate ${giro}`;
        } else {
            texto = `Continúa ${giro}`.trim();
        }
        if (paso.name) texto += ` por ${paso.name}`;
        return `${texto} · ${Math.round(paso.distance || 0)} m`;
    }

    async function calcularRutaVial(r, seguimiento, cercanos) {
        const aviso = document.getElementById('estado-desvio');
        const resumen = document.getElementById('resumen-ruta-vial');
        const pasos = document.getElementById('pasos-ruta-vial');
        const mapaContenedor = document.getElementById('mapa-recorrido');

        try {
            if (r.paradas.length > 26) {
                throw new Error('El recorrido admite hasta 26 paradas.');
            }
            await cargarLeaflet();
            if (rutaSeguimiento !== seguimiento) return;

            const puntos = [r.origen, ...r.paradas];
            if (puntos.some(p => !Number.isFinite(Number(p.lat)) || !Number.isFinite(Number(p.lng)))) {
                throw new Error('Hay paradas sin coordenadas válidas para trazar el recorrido.');
            }
            const coordenadas = puntos.map(p => `${Number(p.lng)},${Number(p.lat)}`).join(';');
            const query = new URLSearchParams({
                overview: 'full',
                geometries: 'geojson',
                steps: 'true',
                alternatives: 'false'
            });
            const respuesta = await fetch(`${OSRM_URL}/route/v1/driving/${coordenadas}?${query}`, {
                headers: { Accept: 'application/json' }
            });
            const resultado = await respuesta.json();
            if (!respuesta.ok || resultado.code !== 'Ok' || !resultado.routes?.length) {
                throw new Error(resultado.message || 'OSRM no encontró una ruta vial para estas paradas.');
            }
            if (rutaSeguimiento !== seguimiento) return;
            const route = resultado.routes[0];
            const rutaPuntos = puntosDeCoordenadas(route.geometry?.coordinates);
            if (rutaPuntos.length < 2) {
                throw new Error('OSRM no devolvió el trazo vial del recorrido.');
            }
            if (!Number.isFinite(route.distance) || !Number.isFinite(route.duration)) {
                throw new Error('OSRM no devolvió distancia y tiempo para la ruta.');
            }

            mapaContenedor.replaceChildren();
            const map = L.map(mapaContenedor, {
                dragging: true,
                doubleClickZoom: true,
                scrollWheelZoom: true,
                touchZoom: true
            });
            seguimiento.map = map;
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors'
            }).addTo(map);
            L.polyline(rutaPuntos.map(p => [p.lat, p.lng]), {
                color: '#e11d48',
                opacity: 0.9,
                weight: 6
            }).addTo(map);

            const controles = L.control({ position: 'topright' });
            let botonPantallaCompleta;
            controles.onAdd = () => {
                const caja = L.DomUtil.create('div', 'leaflet-bar');
                const estilo = 'display:block;width:auto;min-width:42px;padding:8px 10px;border:0;background:#fff;color:#111827;font-size:12px;font-weight:700;cursor:pointer';
                const centrar = L.DomUtil.create('button', '', caja);
                centrar.type = 'button';
                centrar.textContent = 'Mi ubicación';
                centrar.setAttribute('aria-label', 'Centrar el mapa en mi ubicación');
                centrar.style.cssText = estilo;
                L.DomEvent.on(centrar, 'click', async event => {
                    L.DomEvent.stop(event);
                    try {
                        const actual = await obtenerPosicion();
                        if (!actual) {
                            throw new Error('Activa la ubicación del navegador para centrar el mapa.');
                        }
                        map.setView([actual.lat, actual.lng], Math.max(map.getZoom(), 16));
                        if (rutaSeguimiento === seguimiento) actualizarEstadoDesvio();
                    } catch (error) {
                        if (aviso) aviso.textContent = error.message || 'No se pudo centrar el mapa.';
                        console.error('No se pudo centrar el mapa:', error);
                    }
                });

                botonPantallaCompleta = L.DomUtil.create('button', '', caja);
                botonPantallaCompleta.type = 'button';
                botonPantallaCompleta.textContent = 'Pantalla completa';
                botonPantallaCompleta.setAttribute('aria-label', 'Abrir mapa en pantalla completa');
                botonPantallaCompleta.style.cssText = estilo;
                L.DomEvent.on(botonPantallaCompleta, 'click', async event => {
                    L.DomEvent.stop(event);
                    try {
                        if (document.fullscreenElement === mapaContenedor) {
                            await document.exitFullscreen();
                        } else if (mapaContenedor.requestFullscreen) {
                            await mapaContenedor.requestFullscreen();
                        } else {
                            throw new Error('Este navegador no permite pantalla completa para el mapa.');
                        }
                    } catch (error) {
                        if (aviso) aviso.textContent = error.message || 'No se pudo abrir el mapa en pantalla completa.';
                        console.error('No se pudo abrir el mapa en pantalla completa:', error);
                    }
                });
                L.DomEvent.disableClickPropagation(caja);
                return caja;
            };
            controles.addTo(map);

            const actualizarPantallaCompleta = () => {
                const pantallaCompleta = document.fullscreenElement === mapaContenedor;
                mapaContenedor.style.height = pantallaCompleta ? '100vh' : '';
                mapaContenedor.style.width = pantallaCompleta ? '100vw' : '';
                mapaContenedor.style.maxWidth = pantallaCompleta ? '100vw' : '';
                mapaContenedor.style.borderRadius = pantallaCompleta ? '0' : '';
                if (botonPantallaCompleta) {
                    botonPantallaCompleta.textContent = pantallaCompleta ? 'Salir de pantalla completa' : 'Pantalla completa';
                    botonPantallaCompleta.setAttribute('aria-label', pantallaCompleta ? 'Salir de pantalla completa' : 'Abrir mapa en pantalla completa');
                }
                map.invalidateSize();
            };
            document.addEventListener('fullscreenchange', actualizarPantallaCompleta);
            map.on('unload', () => document.removeEventListener('fullscreenchange', actualizarPantallaCompleta));

            const bounds = L.latLngBounds(rutaPuntos.map(p => [p.lat, p.lng]));
            L.marker([r.origen.lat, r.origen.lng], {
                icon: crearIcono('S', '#16a34a'),
                title: 'Salida'
            }).addTo(map).bindPopup(popupPedido({ id_pedido: 'Salida' }, 'Inicio del recorrido'));
            r.paradas.forEach((parada, index) => {
                const punto = [parada.lat, parada.lng];
                bounds.extend(punto);
                L.marker(punto, {
                    icon: crearIcono(String(index + 1), '#e11d48'),
                    title: `Pedido #${parada.id_pedido}`
                }).addTo(map).bindPopup(popupPedido(parada, 'Incluido en la ruta'));
            });

            (cercanos || []).forEach(pedido => {
                if (!Number.isFinite(Number(pedido.lat)) || !Number.isFinite(Number(pedido.lng))) return;
                const punto = [Number(pedido.lat), Number(pedido.lng)];
                const disponible = Boolean(pedido.disponible);
                const subtitulo = disponible ? 'Cercano · disponible para tomar' : 'Cercano · asignado a este repartidor';
                bounds.extend(punto);
                L.marker(punto, {
                    icon: crearIcono(disponible ? '+' : 'C', disponible ? '#d97706' : '#2563eb'),
                    title: `Pedido cercano #${pedido.id_pedido}`
                }).addTo(map).bindPopup(popupPedido(pedido, subtitulo));
            });
            // Todos los demás pedidos (no incluidos ni de paso): solo contexto, no afectan la ruta.
            const capaOtros = L.layerGroup();
            (seguimiento.otros || []).forEach(pedido => {
                if (!Number.isFinite(Number(pedido.lat)) || !Number.isFinite(Number(pedido.lng))) return;
                const mio = !pedido.disponible;
                L.marker([Number(pedido.lat), Number(pedido.lng)], {
                    icon: crearIcono('', mio ? '#93c5fd' : '#9ca3af'),
                    title: `Pedido #${pedido.id_pedido}`
                }).addTo(capaOtros).bindPopup(popupPedido(
                    pedido,
                    mio ? 'Asignado a ti · fuera de la ruta actual (agrégalo desde la lista)'
                        : 'Sin asignar · no queda de paso en tu ruta'
                ));
            });
            capaOtros.addTo(map);
            document.getElementById('rec-ver-otros')?.addEventListener('change', e => {
                if (e.target.checked) capaOtros.addTo(map); else map.removeLayer(capaOtros);
            });
            map.fitBounds(bounds.pad(0.12), { maxZoom: 16 });
            setTimeout(() => map.invalidateSize(), 100);

            seguimiento.tramos = (route.legs || []).map(leg => {
                const coords = (leg.steps || []).flatMap(step => step.geometry?.coordinates || []);
                return puntosDeCoordenadas(coords);
            });
            if (seguimiento.tramos.length !== r.paradas.length || seguimiento.tramos.some(tramo => tramo.length < 2)) {
                throw new Error('OSRM no devolvió instrucciones completas para todos los tramos.');
            }

            const km = (route.distance / 1000).toFixed(1);
            const minutos = Math.max(1, Math.round(route.duration / 60));
            resumen.textContent = `Ruta vial única · ${km} km · ${minutos} min estimados. OSRM devuelve una ruta; Fruv conserva el orden calculado para las paradas.`;

            const listaPasos = (route.legs || []).flatMap((leg, index) => {
                const encabezado = `<li class="list-none pt-2 font-bold text-gray-900">Hacia el pedido #${r.paradas[index].id_pedido}</li>`;
                const instrucciones = (leg.steps || []).map(paso => `
                    <li>${esc(instruccionOSRM(paso))}</li>
                `).join('');
                return [encabezado, instrucciones];
            }).join('');
            pasos.innerHTML = listaPasos || '<li>Consulta el trazo de la ruta en el mapa.</li>';

            if (aviso) aviso.textContent = 'Monitoreando tu posición sobre la ruta vial…';
            actualizarEstadoDesvio();
            temporizadorDesvio = setInterval(actualizarEstadoDesvio, 15000);
        } catch (e) {
            if (rutaSeguimiento !== seguimiento) return;
            if (resumen) resumen.textContent = 'No se pudo calcular la ruta vial.';
            if (mapaContenedor) {
                mapaContenedor.innerHTML = `<p class="p-4 text-sm text-rose-700">${esc(e.message || 'Revisa la conexión con el servicio de rutas.')}</p>`;
            }
            if (aviso) aviso.textContent = 'No se puede detectar el desvío sin una ruta vial calculada.';
            if (pasos) pasos.replaceChildren();
            console.error('No se pudo calcular la ruta vial:', e);
        }
    }

    async function actualizarEstadoDesvio() {
        if (!rutaSeguimiento || !rutaSeguimiento.tramos || verificandoDesvio) return;
        const seguimiento = rutaSeguimiento;
        const estado = document.getElementById('estado-desvio');
        if (!estado) return;
        if (!navigator.geolocation) {
            estado.textContent = 'Activa la ubicación para detectar si te desvías de la ruta.';
            return;
        }

        verificandoDesvio = true;
        try {
            const pos = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(
                    p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
                    reject,
                    { timeout: 5000, maximumAge: 10000 }
                );
            });
            if (rutaSeguimiento !== seguimiento) return;

            if (!seguimiento.marcadorRepartidor) {
                seguimiento.marcadorRepartidor = L.circleMarker([pos.lat, pos.lng], {
                    radius: 7,
                    fillColor: '#2563eb',
                    fillOpacity: 1,
                    color: '#ffffff',
                    weight: 2
                }).addTo(seguimiento.map).bindTooltip('Tu ubicación');
            } else {
                seguimiento.marcadorRepartidor.setLatLng([pos.lat, pos.lng]);
            }

            const parada = seguimiento.paradas[seguimiento.progreso];
            const kmSiguiente = distanciaKm(pos, parada);
            const desvioKm = distanciaARecorrido(pos, seguimiento.tramos[seguimiento.progreso]);
            if (kmSiguiente <= 0.1 && seguimiento.progreso < seguimiento.paradas.length - 1) {
                rutaSeguimiento.progreso++;
            }
            if (desvioKm <= 0.2) {
                estado.className = 'mt-3 rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800';
                estado.textContent = `Vas sobre la ruta vial · Siguiente parada #${parada.id_pedido}, a ${kmSiguiente.toFixed(2)} km.`;
            } else {
                estado.className = 'mt-3 rounded-xl bg-rose-100 p-3 text-sm font-bold text-rose-800';
                estado.textContent = `Te desviaste de la ruta vial (${desvioKm.toFixed(2)} km). Regresa hacia el trazo para continuar.`;
            }
        } catch (e) {
            if (rutaSeguimiento !== seguimiento) return;
            estado.className = 'mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-800';
            estado.textContent = e.code === 1 || e.code === 2 || e.code === 3
                ? 'No se pudo obtener tu ubicación; activa el GPS para detectar desvíos.'
                : (e.message || 'No se pudo verificar si sigues la ruta.');
            console.error('No se pudo verificar el desvío:', e);
        } finally {
            verificandoDesvio = false;
        }
    }

    async function tomarPedidoRecorrido(idPedido) {
        try {
            const res = await fetch(BASE_URL + 'repartidores/tomar-pedido', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    id_pedido: idPedido,
                    ruta_pedidos: recIds.join(','),
                    origen_lat: rutaSeguimiento?.origen?.lat ?? '',
                    origen_lng: rutaSeguimiento?.origen?.lng ?? ''
                })
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
        rutaSeguimiento?.map?.remove();
        rutaSeguimiento = null;
        clearInterval(temporizadorDesvio);
        temporizadorDesvio = null;
        caja.innerHTML = '<p class="p-6 text-center text-sm font-semibold text-gray-600">Calculando una ruta vial única…</p>';
        try {
            const pos = await obtenerPosicion();
            const qs = `?pedidos=${recIds.join(',')}` + (pos ? `&lat=${pos.lat}&lng=${pos.lng}` : '');
            const res = await fetch(BASE_URL + 'repartidores/ruta' + qs, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error('respuesta inválida');
            pintarRecorrido(data);
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

    // Recorrido con varios pedidos a la vez: el orden lo decide la ruta más rápida desde tu ubicación.
    function iniciarRecorridoConPedidos(ids) {
        const lista = [...new Set((ids || []).map(Number).filter(i => i > 0))];
        if (!lista.length) return;
        const m = asegurarModal();
        m.classList.remove('hidden');
        m.classList.add('flex');
        recIds = lista;
        cargarRecorrido();
    }

    window.iniciarRecorrido = iniciarRecorrido;
    window.iniciarRecorridoConPedidos = iniciarRecorridoConPedidos;

    window.cargarRutaRepartidor = cargarRutaRepartidor;
    window.recalcularRutaDesdeGps = () => { posicion = null; cargarRutaRepartidor(); };
    document.addEventListener('DOMContentLoaded', cargarRutaRepartidor);
})();