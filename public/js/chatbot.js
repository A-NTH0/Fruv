
(() => {
    'use strict';

    const CFG  = window.FVBOT || {};
    const root = document.getElementById('fvbot');
    if (!root || !CFG.base) return;
    document.body.appendChild(root);

    const $  = (s) => root.querySelector(s);
    const el = (tag, cls, texto) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (texto != null) n.textContent = texto;
        return n;
    };
    const dinero = (n) => '$' + Number(n || 0).toFixed(2);
    const hora   = (ts) => new Date(ts).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });

    const panel     = $('#fvbot-panel');
    const launcher  = $('#fvbot-launcher');
    const lista     = $('#fvbot-msgs');
    const campo     = $('#fvbot-texto');
    const vistas    = { bienvenida: $('[data-view="bienvenida"]'), chat: $('[data-view="chat"]'), historial: $('[data-view="historial"]') };

    const CHIPS_INICIO = [
        { label: 'Hacer seguimiento de mi pedido', intent: 'seguimiento' },
        { label: '¿Cuáles son las promociones vigentes?', intent: 'promociones' },
        { label: 'Mis últimos pedidos', intent: 'historial' },
        { label: 'Preguntas frecuentes', intent: 'faq_lista' },
    ];

    const KEY = `fvbot:v1:${CFG.clienteId}`;
    const MAX_CONV = 12, MAX_MSG = 80;
    let store = cargar();

    function cargar() {
        try {
            const o = JSON.parse(localStorage.getItem(KEY) || 'null');
            if (o && Array.isArray(o.convs)) return o;
        } catch (e) { /* sin almacenamiento: el chat funciona, solo que sin historial */ }
        return { convs: [], actual: null };
    }
    function guardar() { try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (e) {} }
    const convActual = () => store.convs.find((c) => c.id === store.actual) || null;

    /* ───────────── Vistas ───────────── */
    let vistaPrev = 'bienvenida';
    function mostrarVista(v) {
        if (root.dataset.vista !== 'historial') vistaPrev = root.dataset.vista;
        root.dataset.vista = v;
        Object.entries(vistas).forEach(([k, n]) => { n.hidden = k !== v; });
        if (v === 'historial') pintarHistorial();
        $('#fvbot-btn-historial').setAttribute('aria-label', v === 'historial' ? 'Volver' : 'Historial de chat');
    }

    function abrir(desdeUsuario = true) {
        panel.hidden = false;
        root.classList.add('is-open');
        launcher.setAttribute('aria-expanded', 'true');
        try { sessionStorage.setItem('fvbot:abierto', '1'); } catch (e) {}
        if (!cargarInicio.hecho) cargarInicio();
        if (desdeUsuario) setTimeout(() => campo.focus(), 30);
    }
    function cerrar() {
        panel.hidden = true;
        root.classList.remove('is-open');
        launcher.setAttribute('aria-expanded', 'false');
        try { sessionStorage.removeItem('fvbot:abierto'); } catch (e) {}
        launcher.focus();
    }

    /* ───────────── Red ───────────── */
    async function llamar(ruta, opciones) {
        const f = window.fruverFetch || ((u, o) => fetch(u, o));
        const r = await f(CFG.base + 'chatbot/' + ruta, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, ...opciones });
        if (!(r.headers.get('content-type') || '').includes('json')) throw new Error('sesion'); // p. ej. redirigido al login
        return r.json();
    }
    const post = (ruta, datos) => llamar(ruta, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(datos),
    });

    function textoError(e) {
        return e && e.message === 'sesion'
            ? 'Tu sesión expiró. Recarga la página para continuar.'
            : 'No pude conectarme. Revisa tu internet e inténtalo de nuevo.';
    }

    /* ───────────── Bienvenida ───────────── */
    function pintarChipsInicio() {
        const caja = $('#fvbot-chips-inicio');
        caja.replaceChildren(...CHIPS_INICIO.map(crearChip));
    }
    async function cargarInicio() {
        cargarInicio.hecho = true;
        try {
            const d = await llamar('inicio');
            if (!d.ok) return;
            if (d.nombre) $('#fvbot-saludo').textContent = `¡Hola, ${d.nombre}!`;
            const cont = $('#fvbot-cards');
            cont.replaceChildren(...(d.destacados || []).map((p) => {
                const c = el('div', 'fvbot-pcard');
                const img = el('img'); img.src = p.imagen; img.alt = ''; img.loading = 'lazy';
                img.addEventListener('error', () => { img.style.visibility = 'hidden'; });
                c.append(img, el('span', null, p.nombre));
                return c;
            }));
        } catch (e) { /* la bienvenida funciona sin las tarjetas */ }
    }

    /* ───────────── Mensajes ───────────── */
    function crearChip(op) {
        const b = el('button', 'fvbot-chip', op.label);
        b.type = 'button';
        b.addEventListener('click', () => accion(op));
        return b;
    }

    const RENDER = {
        texto(d) {
            const b = el('div', 'fvbot-bubble');
            String(d.texto || '').split('\n').forEach((l) => b.appendChild(el('p', null, l)));
            return b;
        },
        chips(d) {
            const c = el('div', 'fvbot-chips');
            c.append(...(d.opciones || []).map(crearChip));
            return c;
        },
        pedido(d) {
            const p = d.pedido, card = el('div', 'fvbot-card');
            const head = el('div', 'fvbot-card-head');
            head.append(el('h3', null, `Pedido #${p.numero}`), el('span', `fvbot-badge fvbot-badge--${p.estado.color}`, p.estado.texto));
            card.appendChild(head);

            if (p.estado.paso > 0) {
                const barra = el('div', 'fvbot-progress'); barra.setAttribute('aria-hidden', 'true');
                const relleno = el('span'); relleno.style.width = `${(p.estado.paso / p.pasos.length) * 100}%`;
                barra.appendChild(relleno);
                card.append(barra, el('p', 'fvbot-progress-label', `Paso ${p.estado.paso} de ${p.pasos.length} · ${p.pasos[p.estado.paso - 1]}`));
            }
            if (p.estado.detalle) card.appendChild(el('p', 'fvbot-detail', p.estado.detalle));

            const dl = el('dl', 'fvbot-dl');
            const fila = (k, v) => { if (v == null || v === '') return; const f = el('div'); f.append(el('dt', null, k), el('dd', null, v)); dl.appendChild(f); };
            fila('Fecha del pedido', p.fecha);
            fila('Entrega programada', p.entrega);
            fila('Repartidor', p.repartidor);
            fila('Pago', p.modalidad + (p.estado_pago ? ` · ${p.estado_pago}` : ''));
            if (p.saldo > 0.004) fila('Saldo pendiente', dinero(p.saldo));
            fila('Total', dinero(p.total));
            card.appendChild(dl);

            if (p.productos.length) {
                const ul = el('ul', 'fvbot-items');
                p.productos.forEach((x) => {
                    const li = el('li');
                    li.append(el('span', null, `${x.cantidad} ${x.unidad} · ${x.nombre}`), el('span', null, dinero(x.total)));
                    ul.appendChild(li);
                });
                card.appendChild(ul);
            }
            const a = el('a', null, 'Ver en Mis pedidos'); a.href = p.url;
            card.appendChild(a);
            return card;
        },
        lista_pedidos(d) {
            const box = el('div', 'fvbot-list');
            d.pedidos.forEach((p) => {
                const b = el('button'); b.type = 'button';
                const izq = el('span'); izq.append(el('strong', null, `Pedido #${p.numero}`), el('small', null, `${p.fecha} · ${dinero(p.total)}`));
                b.append(izq, el('span', `fvbot-badge fvbot-badge--${p.estado.color}`, p.estado.texto));
                b.addEventListener('click', () => accion({ label: `Ver pedido #${p.numero}`, intent: 'pedido', numero: p.numero }));
                box.appendChild(b);
            });
            return box;
        },
        promos(d) {
            const card = el('div', 'fvbot-card');
            card.appendChild(el('div', 'fvbot-card-head')).appendChild(el('h3', null, 'Promociones vigentes'));
            if (!d.promociones.length) {
                card.appendChild(el('p', 'fvbot-detail', 'Por ahora no hay promociones vigentes. Vuelve pronto.'));
            }
            d.promociones.forEach((p) => {
                const b = el('div', 'fvbot-promo');
                b.append(el('strong', null, p.titulo), el('span', null, p.descripcion), el('small', null, p.vigencia));
                card.appendChild(b);
            });
            card.appendChild(el('p', 'fvbot-pref', d.preferencial.texto));
            return card;
        },
    };

    function pintar(msg) {
        const fila = el('div', `fvbot-row fvbot-row--${msg.de === 'yo' ? 'me' : 'bot'}`);
        if (msg.de !== 'yo') { const av = el('span', 'fvbot-avatar', 'F'); av.setAttribute('aria-hidden', 'true'); fila.appendChild(av); }
        const col = el('div', 'fvbot-col');
        col.appendChild((RENDER[msg.tipo] || RENDER.texto)(msg.datos || {}));
        if (msg.tipo !== 'chips') col.appendChild(el('span', 'fvbot-meta', `${msg.de === 'yo' ? 'Enviado' : 'Fruver'} · ${hora(msg.ts)}`));
        fila.appendChild(col);
        lista.appendChild(fila);
        lista.parentElement.scrollTop = lista.parentElement.scrollHeight;
        return fila;
    }

    function empujar(de, tipo, datos, persistir = true) {
        const msg = { de, tipo, datos, ts: Date.now() };
        if (persistir) {
            const c = convActual();
            if (c) { c.msgs.push(msg); if (c.msgs.length > MAX_MSG) c.msgs.splice(0, c.msgs.length - MAX_MSG); c.ts = msg.ts; guardar(); }
        }
        return pintar(msg);
    }
    const yo       = (t) => empujar('yo', 'texto', { texto: t });
    const bot      = (t) => empujar('bot', 'texto', { texto: t });
    const botChips = (ops) => empujar('bot', 'chips', { opciones: ops });

    function empezarChat(titulo) {
        if (!convActual()) {
            const c = { id: Date.now().toString(36), titulo: String(titulo).slice(0, 60), ts: Date.now(), msgs: [] };
            store.convs.unshift(c);
            store.convs.length = Math.min(store.convs.length, MAX_CONV);
            store.actual = c.id;
            guardar();
            lista.replaceChildren();
        }
        mostrarVista('chat');
    }

    function conversacionNueva() {
        store.actual = null; guardar();
        lista.replaceChildren();
        mostrarVista('bienvenida');
        campo.focus();
    }

    function abrirConversacion(id) {
        store.actual = id; guardar();
        lista.replaceChildren();
        (convActual()?.msgs || []).forEach(pintar);
        mostrarVista('chat');
        lista.parentElement.scrollTop = lista.parentElement.scrollHeight;
    }

    /* ───────────── Acciones ───────────── */
    const OTRAS = [
        { label: 'Hacer seguimiento de otro pedido', intent: 'seguimiento' },
        { label: 'Ver promociones', intent: 'promociones' },
        { label: 'Nueva conversación', intent: 'nueva' },
    ];

    function accion(op) {
        if (op.intent === 'nueva') { conversacionNueva(); return; }
        empezarChat(op.label);
        yo(op.label);
        if (op.intent === 'seguimiento') { formularioSeguimiento(); return; }
        consultar({ intent: op.intent, id: op.id || '', numero: op.numero || '' });
    }

    function enviarTexto(t) {
        empezarChat(t);
        yo(t);
        consultar({ texto: t });
    }

    async function consultar(datos) {
        const escribiendo = el('div', 'fvbot-row fvbot-row--bot');
        const t = el('span', 'fvbot-typing'); t.append(el('i'), el('i'), el('i')); t.setAttribute('aria-label', 'Escribiendo');
        escribiendo.append(t);
        lista.appendChild(escribiendo);
        lista.parentElement.scrollTop = lista.parentElement.scrollHeight;
        try {
            const d = await post('mensaje', datos);
            escribiendo.remove();
            responder(d);
        } catch (e) {
            escribiendo.remove();
            bot(textoError(e));
        }
    }

    function responder(d) {
        switch (d.tipo) {
            case 'seguimiento_form': formularioSeguimiento(); break;
            case 'promociones':
                empujar('bot', 'promos', d);
                botChips([{ label: 'Mis últimos pedidos', intent: 'historial' }, { label: 'Preguntas frecuentes', intent: 'faq_lista' }]);
                break;
            case 'historial':
                if (!d.pedidos.length) { bot('Aún no tienes pedidos. Cuando hagas el primero lo verás aquí.'); break; }
                bot('Estos son tus pedidos más recientes. Toca uno para ver su estado:');
                empujar('bot', 'lista_pedidos', d);
                break;
            case 'pedido':
                empujar('bot', 'pedido', d);
                botChips(OTRAS);
                break;
            case 'faq':
                bot(d.respuesta);
                if (d.relacionadas && d.relacionadas.length) {
                    botChips(d.relacionadas.map((r) => ({ label: r.pregunta, intent: 'faq', id: r.id })));
                }
                break;
            case 'faq_lista':
                bot(d.mensaje);
                botChips(d.preguntas.map((r) => ({ label: r.pregunta, intent: 'faq', id: r.id })));
                break;
            default:
                bot(d.mensaje || 'No pude procesar tu mensaje. Inténtalo de nuevo.');
        }
    }

    /* ───────────── Formulario de seguimiento (no se guarda en el historial) ───────────── */
    function formularioSeguimiento() {
        bot('Para ver el estado de tu pedido, indícame estos datos.');

        const card = el('form', 'fvbot-formcard');
        card.noValidate = true;
        const head = el('div', 'fvbot-formhead');
        const quien = el('div'); quien.append(el('strong', null, 'Hacer seguimiento de mi pedido'), el('small', null, 'Usa el número de Mis pedidos y el correo de tu cuenta.'));
        head.appendChild(quien);

        const campoDe = (etiqueta, tipo, nombre, extra) => {
            const l = el('label'); const s = el('span', null, etiqueta); const i = el('input');
            i.type = tipo; i.name = nombre; i.required = true; Object.assign(i, extra);
            l.append(s, i); return [l, i];
        };
        const [lNum, iNum] = campoDe('Número de pedido', 'text', 'numero', { inputMode: 'numeric', maxLength: 10, autocomplete: 'off' });
        const [lMail, iMail] = campoDe('Correo electrónico', 'email', 'email', { autocomplete: 'email', maxLength: 50 });
        const err = el('p', 'fvbot-error'); err.hidden = true; err.setAttribute('role', 'alert');

        const acciones = el('div', 'fvbot-formactions');
        const bCancel = el('button', 'fvbot-btn', 'Cancelar'); bCancel.type = 'button';
        const bOk = el('button', 'fvbot-btn fvbot-btn--primary', 'Hacer seguimiento'); bOk.type = 'submit';
        acciones.append(bCancel, bOk);
        card.append(head, lNum, lMail, err, acciones);

        const fila = el('div', 'fvbot-row fvbot-row--bot');
        const av = el('span', 'fvbot-avatar', 'F'); av.setAttribute('aria-hidden', 'true');
        const col = el('div', 'fvbot-col'); col.style.width = '100%'; col.appendChild(card);
        fila.append(av, col);
        lista.appendChild(fila);
        lista.parentElement.scrollTop = lista.parentElement.scrollHeight;
        iNum.focus();

        const bloquear = () => card.querySelectorAll('input,button').forEach((n) => { n.disabled = true; });
        const falla = (m) => { err.textContent = m; err.hidden = false; };

        bCancel.addEventListener('click', () => {
            fila.remove();
            yo('Cancelar');
            bot('Si tienes preguntas, escríbeme directamente. Con gusto te ayudo.');
            botChips(OTRAS);
        });

        card.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            err.hidden = true;
            if (!/^#?\d{1,9}$/.test(iNum.value.trim())) { falla('Escribe el número de pedido (solo dígitos).'); iNum.focus(); return; }
            if (!iMail.value.trim() || !iMail.validity.valid) { falla('Escribe un correo electrónico válido.'); iMail.focus(); return; }

            bOk.disabled = true; bOk.textContent = 'Consultando…';
            try {
                const d = await post('seguimiento', { numero: iNum.value.trim(), email: iMail.value.trim() });
                if (d.ok) {
                    bloquear(); fila.remove();
                    yo(`Seguimiento del pedido #${d.pedido.numero}`);
                    empujar('bot', 'pedido', { pedido: d.pedido });
                    botChips(OTRAS);
                } else {
                    falla(d.mensaje || 'No pudimos consultar el pedido.');
                    bOk.disabled = !!d.bloqueado; bOk.textContent = 'Hacer seguimiento';
                }
            } catch (e) {
                falla(textoError(e)); bOk.disabled = false; bOk.textContent = 'Hacer seguimiento';
            }
        });
    }

    /* ───────────── Historial de chat ───────────── */
    function pintarHistorial() {
        const caja = $('#fvbot-hist-lista');
        const hay = store.convs.some((c) => c.msgs.length);
        $('#fvbot-hist-borrar').hidden = !hay;
        if (!hay) {
            caja.replaceChildren(el('p', 'fvbot-hist-empty', 'No hay conversaciones anteriores'));
            return;
        }
        const box = el('div', 'fvbot-list');
        store.convs.filter((c) => c.msgs.length).forEach((c) => {
            const b = el('button'); b.type = 'button';
            const t = el('span'); t.append(el('strong', null, c.titulo || 'Conversación'), el('small', null, new Date(c.ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })));
            b.appendChild(t);
            b.addEventListener('click', () => abrirConversacion(c.id));
            box.appendChild(b);
        });
        caja.replaceChildren(box);
    }

    /* ───────────── Eventos ───────────── */
    launcher.addEventListener('click', () => abrir(true));
    $('#fvbot-btn-cerrar').addEventListener('click', cerrar);
    $('#fvbot-btn-nuevo').addEventListener('click', conversacionNueva);
    $('#fvbot-hist-nuevo').addEventListener('click', conversacionNueva);
    $('#fvbot-btn-historial').addEventListener('click', () => {
        if (root.dataset.vista === 'historial') mostrarVista(vistaPrev === 'historial' ? 'bienvenida' : vistaPrev);
        else mostrarVista('historial');
    });
    $('#fvbot-btn-ampliar').addEventListener('click', (e) => {
        const on = root.classList.toggle('is-wide');
        e.currentTarget.setAttribute('aria-pressed', String(on));
    });
    $('#fvbot-hist-borrar').addEventListener('click', () => {
        if (window.confirm('¿Borrar todo el historial de conversaciones de este dispositivo?')) {
            store = { convs: [], actual: null }; guardar();
            lista.replaceChildren();
            pintarHistorial();
        }
    });
    $('#fvbot-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const t = campo.value.trim();
        if (!t) return;
        campo.value = '';
        enviarTexto(t);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) cerrar(); });

    /* ───────────── Arranque ───────────── */
    pintarChipsInicio();
    let reabrir = false;
    try { reabrir = sessionStorage.getItem('fvbot:abierto') === '1'; } catch (e) {}
    if (reabrir) {
        abrir(false);
        if (convActual() && convActual().msgs.length) abrirConversacion(store.actual);
    }
})();