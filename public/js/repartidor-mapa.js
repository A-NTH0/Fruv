/**
 * Mapa fijo del panel del repartidor (Leaflet + calles de OSRM calculadas en el servidor).
 *
 * Muestra, sin abrir ningún recorrido:
 *   - tus entregas activas en el orden de la ruta más corta (con hora estimada y hora prometida),
 *   - los pedidos SIN asignar que te quedan de paso (se pueden tomar, y avisa a quién atrasarían),
 *   - el resto de pedidos sin asignar como contexto (solo informativo, no se pueden tomar).
 *
 * Los datos llegan en data.mapa desde repartidor-ruta.js (GET /repartidores/ruta).
 * No toca el recorrido ("Iniciar recorrido") ni las tarjetas de pedidos.
 */
(function () {
    const $ = id => document.getElementById(id);
    let mapa = null, capaRuta = null, capaPaso = null, capaOtros = null, capaYo = null;
    let marcadorYo = null, siguiendo = false, ultimoBounds = null, posicionYo = null;
    let datos = null;                 // data.mapa vigente
    const marcadores = {};            // id_pedido -> marcador (mis paradas y los de paso)

    function esc(t) {
        const d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    function esAproximada(precision) {
        return precision === 'colonia' || precision === 'municipio';
    }

    function iniciarMapa() {
        if (mapa) return true;
        const el = $('mapa-ruta');
        if (!el || !window.L) return false;

        const centro = (window.TIENDA_COORD && [TIENDA_COORD.lat, TIENDA_COORD.lng]) || [19.3139, -97.9206];
        mapa = L.map(el).setView(centro, 12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
        }).addTo(mapa);
        capaOtros = L.layerGroup().addTo(mapa);
        capaRuta  = L.layerGroup().addTo(mapa);
        capaPaso  = L.layerGroup().addTo(mapa);
        capaYo    = L.layerGroup().addTo(mapa);
        return true;
    }

    function pin(clase, html, tam) {
        const t = tam || 32;
        return L.divIcon({
            className: clase, html: `<span>${html}</span>`,
            iconSize: [t, t], iconAnchor: [t / 2, t / 2], popupAnchor: [0, -(t / 2) + 2]
        });
    }

    function actualizarMarcadorYo() {
        if (!mapa || !posicionYo) return;
        const ll = [posicionYo.lat, posicionYo.lng];
        if (marcadorYo) {
            marcadorYo.setLatLng(ll);
        } else {
            marcadorYo = L.marker(ll, { icon: pin('ruta-pin ruta-pin--yo', '●'), zIndexOffset: 1000 })
                .bindPopup('Tu ubicación actual').addTo(capaYo);
        }
    }

    // Mueve tu marcador con el GPS (no recalcula la ruta; para eso está "Recalcular").
    function seguirPosicion() {
        if (siguiendo || !navigator.geolocation) return;
        siguiendo = true;
        navigator.geolocation.watchPosition(
            p => { posicionYo = { lat: p.coords.latitude, lng: p.coords.longitude }; actualizarMarcadorYo(); },
            () => { /* sin permiso: se queda la tienda como salida */ },
            { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 }
        );
    }

    // ───────────── Tomar un pedido que queda de paso ─────────────
    async function tomarDePaso(c, boton) {
        if (!datos) return;
        if (boton) boton.disabled = true;
        try {
            const res = await fetch(BASE_URL + 'repartidores/tomar-pedido', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    id_pedido: c.id_pedido,
                    ruta_pedidos: datos.paradas.map(p => p.id_pedido).join(','),
                    origen_lat: datos.origen.lat,
                    origen_lng: datos.origen.lng
                })
            });
            const r = await res.json();
            if (r.ok) {
                if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: r.mensaje || 'Pedido agregado a tu ruta', showConfirmButton: false, timer: 2200 });
                mapa?.closePopup();
                if (window.sincronizarPedidos) await window.sincronizarPedidos(false);   // refresca tarjetas y KPIs
                if (window.cargarRutaRepartidor) window.cargarRutaRepartidor();          // refresca el mapa
            } else {
                if (window.Swal) Swal.fire('Atención', r.mensaje || 'No se pudo tomar el pedido.', 'warning');
                if (boton) boton.disabled = false;
            }
        } catch (e) {
            if (window.Swal) Swal.fire('Error', 'Error de conexión.', 'error');
            if (boton) boton.disabled = false;
        }
    }

    function textoEfecto(c) {
        const partes = [`≈ +${c.extra_min} min a tu ruta · desvío ≈ ${c.desvio_km} km`];
        if (c.limite) partes.push(`Prometido ${c.limite}${c.tarde_min > 0 ? ` (llegarías ≈ ${c.tarde_min} min tarde)` : ''}`);
        return partes;
    }

    function popupParada(p) {
        const aprox = esAproximada(p.precision);
        return `<strong>${p.orden}. Pedido #${p.id_pedido}</strong><br>${esc(p.cliente_nombre)}<br>` +
            `<span style="font-size:12px">${esc(p.direccion)}</span><br>` +
            `<span style="font-size:12px">+${p.km_tramo} km · ≈ ${p.min_tramo} min · llegada ≈ ${esc(p.llegada)}</span>` +
            (p.limite ? `<br><span style="font-size:12px">Entrega prometida: ${esc(p.limite)}</span>` : '') +
            (p.tarde_min > 0 ? `<br><span style="font-size:11px;color:#b91c1c">Llegarías ≈ ${p.tarde_min} min tarde</span>` : '') +
            (aprox ? `<br><span style="font-size:11px;color:#b45309">Ubicación aproximada (${esc(p.precision)})</span>` : '');
    }

    function popupPaso(c) {
        const caja = document.createElement('div');
        caja.innerHTML =
            `<strong>Pedido #${c.id_pedido}</strong> · sin asignar<br>${esc(c.cliente_nombre)}<br>` +
            `<span style="font-size:12px">${esc(c.direccion)}</span><br>` +
            textoEfecto(c).map(t => `<span style="font-size:12px">${esc(t)}</span>`).join('<br>') +
            (c.atrasa.length
                ? `<br><span style="font-size:11px;color:#b91c1c">Atrasaría: ${c.atrasa.map(a => `#${a.id_pedido} (≈ +${a.min_extra} min)`).join(', ')}</span>`
                : '') +
            (esAproximada(c.precision) ? `<br><span style="font-size:11px;color:#b45309">Ubicación aproximada (${esc(c.precision)})</span>` : '') +
            '<br>';
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = 'Tomar y agregar';
        b.style.cssText = 'margin-top:6px;padding:6px 10px;border:0;border-radius:8px;background:#059669;color:#fff;font-weight:700;font-size:12px;cursor:pointer';
        b.addEventListener('click', () => tomarDePaso(c, b));
        caja.appendChild(b);
        return caja;
    }

    function popupOtro(o) {
        return `<strong>Pedido #${o.id_pedido}</strong> · sin asignar<br>${esc(o.cliente_nombre)}<br>` +
            `<span style="font-size:12px">${esc(o.direccion)}</span><br>` +
            '<span style="font-size:11px;color:#6b7280">No queda de paso en tu ruta, solo informativo.</span>';
    }

    // ───────────── Dibujo ─────────────
    function dibujar(data, m) {
        const contenedor = $('mapa-ruta');
        const hayAlgo = m.hay_ruta || (m.otros && m.otros.length);
        contenedor.classList.toggle('hidden', !hayAlgo || !window.L);
        if (!hayAlgo || !iniciarMapa()) return;

        capaRuta.clearLayers();
        capaPaso.clearLayers();
        capaOtros.clearLayers();
        Object.keys(marcadores).forEach(k => delete marcadores[k]);
        mapa.invalidateSize();

        const puntos = [];

        if (m.hay_ruta) {
            const linea = L.polyline(m.trazo, {
                color: '#e11d48', weight: 5, opacity: 0.85,
                dashArray: m.trazo_calles ? null : '8 8'   // punteada = línea recta (OSRM no respondió)
            }).addTo(capaRuta);
            puntos.push(...m.trazo);

            if (!data.origen_gps) {
                L.marker([m.origen.lat, m.origen.lng], { icon: pin('ruta-pin ruta-pin--tienda', '🏪') })
                    .bindPopup('Punto de salida: tienda').addTo(capaRuta);
            }

            m.paradas.forEach(p => {
                const clase = 'ruta-pin' + (p.tarde_min > 0 ? ' ruta-pin--tarde' : (esAproximada(p.precision) ? ' ruta-pin--aprox' : ''));
                marcadores[p.id_pedido] = L.marker([p.lat, p.lng], { icon: pin(clase, p.orden) })
                    .bindPopup(popupParada(p)).addTo(capaRuta);
            });
            ultimoBounds = linea.getBounds().extend([m.origen.lat, m.origen.lng]);
        }

        (m.de_paso || []).forEach(c => {
            marcadores[c.id_pedido] = L.marker([c.lat, c.lng], { icon: pin('ruta-pin ruta-pin--paso', '+'), title: `Pedido #${c.id_pedido}` })
                .bindPopup(popupPaso(c)).addTo(capaPaso);
            puntos.push([c.lat, c.lng]);
        });

        (m.otros || []).forEach(o => {
            L.marker([o.lat, o.lng], { icon: pin('ruta-pin ruta-pin--otro', '', 18), title: `Pedido #${o.id_pedido}` })
                .bindPopup(popupOtro(o)).addTo(capaOtros);
        });
        const ver = $('mapa-ver-otros');
        if (ver && !ver.checked) mapa.removeLayer(capaOtros); else if (!mapa.hasLayer(capaOtros)) capaOtros.addTo(mapa);

        if (data.origen_gps) {
            posicionYo = { lat: m.origen.lat, lng: m.origen.lng };
            actualizarMarcadorYo();
        }

        if (!ultimoBounds || !m.hay_ruta) {
            const todos = [...puntos, ...(m.otros || []).map(o => [o.lat, o.lng])];
            ultimoBounds = todos.length ? L.latLngBounds(todos) : null;
        } else if ((m.de_paso || []).length) {
            ultimoBounds = L.latLngBounds([...puntos]);
        }
        if (ultimoBounds) mapa.fitBounds(ultimoBounds, { padding: [30, 30], maxZoom: 16 });
    }

    // ───────────── Panel (resumen + lista de "de paso") ─────────────
    function pintar(data) {
        const m = data.mapa;
        datos = m;

        const avisos = [];
        if (m.hay_ruta && !data.origen_gps) avisos.push('Sin ubicación del dispositivo: la ruta sale de la tienda.');
        if (m.hay_ruta && !m.trazo_calles) avisos.push('No se pudo consultar el servicio de calles; se muestran líneas rectas (distancia aproximada).');
        const aviso = $('mapa-aviso');
        aviso.textContent = avisos.join(' ');
        aviso.classList.toggle('hidden', avisos.length === 0);

        const google = $('btn-google-maps');
        if (m.hay_ruta) {
            $('mapa-resumen').textContent =
                `${m.paradas.length} ${m.paradas.length === 1 ? 'entrega' : 'entregas'} · ${m.distancia_km} km · ≈ ${m.duracion_min} min de manejo` +
                ` · última entrega ≈ ${m.llegada_final} (salida desde ${data.origen_gps ? 'tu ubicación' : 'la tienda'}).`;
            google.href = m.google_url;
            google.classList.remove('hidden');
        } else {
            $('mapa-resumen').textContent = m.motivo || 'Sin ruta por calcular.';
            google.classList.add('hidden');
        }

        // Lista de pedidos que quedan de paso
        const caja = $('mapa-paso'), lista = $('mapa-paso-lista');
        lista.innerHTML = '';
        (m.de_paso || []).forEach(c => {
            const li = document.createElement('li');
            li.className = 'flex items-start gap-3 py-2';
            li.innerHTML = `
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900">Pedido #${c.id_pedido} · ${esc(c.cliente_nombre)}
                        ${esAproximada(c.precision) ? '<span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">Ubicación aproximada</span>' : ''}
                        ${c.limite ? `<span class="ml-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700">Prometido ${esc(c.limite)}</span>` : ''}
                    </p>
                    <p class="truncate text-xs text-gray-600">${esc(c.direccion)}</p>
                    <p class="text-[11px] font-semibold text-emerald-800">≈ +${c.extra_min} min a tu ruta · desvío ≈ ${c.desvio_km} km</p>
                    ${c.tarde_min > 0 ? `<p class="text-[11px] font-semibold text-red-700">Este pedido llegaría ≈ ${c.tarde_min} min tarde.</p>` : ''}
                    ${c.atrasa.length ? `<p class="text-[11px] font-semibold text-red-700">Atrasaría: ${c.atrasa.map(a => `#${a.id_pedido} (≈ +${a.min_extra} min, llegaría ${a.tarde_min} min tarde)`).join(', ')}.</p>` : ''}
                </div>`;
            const acciones = document.createElement('div');
            acciones.className = 'flex flex-col gap-1.5';
            const ver = document.createElement('button');
            ver.type = 'button';
            ver.className = 'whitespace-nowrap text-xs font-semibold text-emerald-700 hover:underline';
            ver.textContent = 'Ver en mapa';
            ver.addEventListener('click', () => window.rutaEnfocarPedido(c.id_pedido));
            const tomar = document.createElement('button');
            tomar.type = 'button';
            tomar.className = 'rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50';
            tomar.textContent = 'Tomar y agregar';
            tomar.addEventListener('click', () => tomarDePaso(c, tomar));
            acciones.append(ver, tomar);
            li.appendChild(acciones);
            lista.appendChild(li);
        });
        caja.classList.toggle('hidden', !(m.de_paso || []).length);

        const nOtros = (m.otros || []).length;
        $('mapa-n-otros').textContent = nOtros;
        const wrap = $('mapa-otros-wrap');
        wrap.classList.toggle('hidden', nOtros === 0);
        wrap.classList.toggle('flex', nOtros > 0);
        $('mapa-leyenda').classList.toggle('hidden', !(m.hay_ruta || nOtros));

        const n = [];
        if (m.hay_ruta) {
            n.push(m.fuente === 'calles'
                ? 'Distancias y tiempos por calle (OpenStreetMap), sin tráfico en vivo.'
                : 'Distancias estimadas en línea recta (sin servicio de calles).');
        }
        if (m.truncada) n.push('Google Maps admite 10 paradas: el enlace incluye las primeras 10.');
        if (m.omitidas && m.omitidas.length) n.push(`Por el límite de paradas quedaron fuera: ${m.omitidas.map(i => '#' + i).join(', ')}.`);
        if (m.sin_ubicacion && m.sin_ubicacion.length) n.push(`Sin ubicación en el mapa (aún no se geocodifica su dirección): ${m.sin_ubicacion.map(i => '#' + i).join(', ')}.`);
        $('mapa-notas').textContent = n.join(' ');

        // Botón global: el recorrido arranca con todas las entregas, en el orden de la ruta más rápida.
        const iniciar = $('btn-iniciar-recorrido');
        if (iniciar) {
            iniciar.classList.toggle('hidden', !m.hay_ruta);
            iniciar.textContent = `Iniciar recorrido (${m.paradas.length})`;
        }

        publicarOrden(m);
        dibujar(data, m);
    }

    // Deja el número de parada de cada pedido para que las tarjetas se ordenen y numeren igual que la ruta.
    function publicarOrden(m) {
        const nuevo = {};
        (m.paradas || []).forEach(p => {
            nuevo[p.id_pedido] = { orden: p.orden, total: m.paradas.length, llegada: p.llegada, limite: p.limite, tarde_min: p.tarde_min };
        });
        const firma = JSON.stringify(nuevo);
        if (firma === JSON.stringify(window.ORDEN_RUTA || {})) return;
        window.ORDEN_RUTA = nuevo;
        if (window.renderizarVistas) window.renderizarVistas();
        if (window.lucide) window.lucide.createIcons();
    }

    // ───────────── API para el resto de la página ─────────────
    window.rutaMapaPintar = function (data) {
        if (!data || !data.mapa || !$('panel-mapa')) return;
        pintar(data);
        if (data.origen_gps) seguirPosicion();
    };

    window.rutaRefrescarMapa = function () {          // al volver a la pestaña "Mis entregas"
        if (!mapa) return;
        mapa.invalidateSize();
        if (ultimoBounds) mapa.fitBounds(ultimoBounds, { padding: [30, 30], maxZoom: 16 });
    };

    window.rutaEnfocarPedido = function (id) {         // "Ver en mapa" (tarjetas y lista de de paso)
        const mk = marcadores[id];
        if (!mk || !mapa) {
            if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Este pedido aún no tiene ubicación en el mapa', showConfirmButton: false, timer: 2500 });
            return;
        }
        $('mapa-ruta').scrollIntoView({ behavior: 'smooth', block: 'center' });
        mapa.setView(mk.getLatLng(), Math.max(mapa.getZoom(), 16));
        mk.openPopup();
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.L) {
            const a = $('mapa-aviso');
            if (a) { a.textContent = 'No se pudo cargar el mapa (revisa tu conexión a internet).'; a.classList.remove('hidden'); }
        }
        $('btn-iniciar-recorrido')?.addEventListener('click', () => {
            const ids = (datos && datos.paradas || []).map(p => p.id_pedido);
            if (ids.length && window.iniciarRecorridoConPedidos) window.iniciarRecorridoConPedidos(ids);
        });
        $('btn-mapa-recalcular')?.addEventListener('click', () => {
            if (window.recalcularRutaDesdeGps) window.recalcularRutaDesdeGps();
        });
        $('mapa-ver-otros')?.addEventListener('change', e => {
            if (!mapa || !capaOtros) return;
            if (e.target.checked) capaOtros.addTo(mapa); else mapa.removeLayer(capaOtros);
        });
    });
})();
