lucide.createIcons();

// ─────────────────────────────────────────────
//  NAVEGACIÓN DE PESTAÑAS (TABS)
// ─────────────────────────────────────────────
function cambiarPestaña(idSeccion, boton) {
    document.querySelectorAll('.tab-content').forEach(sec => {
        sec.classList.add('hidden');
        sec.classList.remove('block');
    });
    const seccion = document.getElementById(idSeccion);
    if (seccion) {
        seccion.classList.remove('hidden');
        seccion.classList.add('block');
    }
    document.querySelectorAll('.btn-tab').forEach(btn => {
        btn.classList.remove('active', 'bg-white', 'shadow-sm', 'text-gray-900');
        btn.classList.add('text-gray-500');
    });
    boton.classList.add('active', 'bg-white', 'shadow-sm', 'text-gray-900');
    boton.classList.remove('text-gray-500');
}

// ─────────────────────────────────────────────
//  PREGUNTA DINÁMICA Y CONVERSIÓN DE ENTRADAS
// ─────────────────────────────────────────────
const inputCantidad   = document.getElementById('cantidad_entrada');
const inputEquiv      = document.getElementById('equivalente');
const inputConversion = document.getElementById('conversion');
const selectUCompra   = document.getElementById('unidad_compra');
const selectUVenta    = document.getElementById('unidad_venta');
const labelPregunta   = document.getElementById('pregunta_dinamica');
const spanUnidadTexto = document.getElementById('unidad_resumen_texto');

function cambiarTextoConversion() {
    if (!selectUCompra || !selectUVenta) return;
    const uCompra = selectUCompra.options[selectUCompra.selectedIndex].text;
    const uVenta  = selectUVenta.options[selectUVenta.selectedIndex].text;
    if (labelPregunta)   labelPregunta.textContent   = `¿Cuántos ${uVenta} trae cada ${uCompra}?`;
    if (spanUnidadTexto) spanUnidadTexto.textContent = uVenta;
    calcularEquivalente();
}

function calcularEquivalente() {
    if (!inputCantidad || !inputEquiv || !inputConversion) return;
    const cantidad    = parseFloat(inputCantidad.value) || 0;
    const equivalente = parseFloat(inputEquiv.value) || 0;
    inputConversion.value = (cantidad * equivalente).toFixed(2);
}

inputCantidad?.addEventListener('input', calcularEquivalente);
inputEquiv?.addEventListener('input', calcularEquivalente);

// ─────────────────────────────────────────────
//  FECHAS DE ENTRADA Y CADUCIDAD (+5 DÍAS)
// ─────────────────────────────────────────────
const inputFechaEntrada   = document.getElementById('fecha_entrada');
const inputFechaCaducidad = document.getElementById('fecha_caducidad');

function configurarLimitesCalendario() {
    if (!inputFechaEntrada) return;
    const hoy = new Date();
    const fechaLocal = fecha => {
        const anio = fecha.getFullYear();
        const mes  = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia  = String(fecha.getDate()).padStart(2, '0');
        return `${anio}-${mes}-${dia}`;
    };
    const maxFecha      = fechaLocal(hoy);
    const haceUnaSemana = new Date(hoy);
    haceUnaSemana.setDate(hoy.getDate() - 7);
    const minFecha      = fechaLocal(haceUnaSemana);
    inputFechaEntrada.max = maxFecha;
    inputFechaEntrada.min = minFecha;

    if (!inputFechaEntrada.value) {
        inputFechaEntrada.value = maxFecha;
    }

    actualizarFechaCaducidad();
}
configurarLimitesCalendario();

function actualizarFechaCaducidad() {
    if (!inputFechaEntrada?.value || !inputFechaCaducidad) return;

    const partes = inputFechaEntrada.value.split('-');
    const fecha  = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));
    fecha.setDate(fecha.getDate() + 5);
    const anio = fecha.getFullYear();
    const mes  = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia  = String(fecha.getDate()).padStart(2, '0');
    inputFechaCaducidad.value = `${anio}-${mes}-${dia}`;
}

inputFechaEntrada?.addEventListener('change', function() {
    if (!this.value || !inputFechaCaducidad) return;
    actualizarFechaCaducidad();
});

(function setFechaMermaHoy() {
    const input = document.getElementById('fecha_merma');
    if (!input) return;
    const hoy = new Date();
    input.value = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
})();

// ─────────────────────────────────────────────
//  MODAL DE MERMAS (CON + AGREGAR OTRA MERMA)
// ─────────────────────────────────────────────
const modal            = document.getElementById('myModal');
const btn              = document.getElementById('btnRegistrarMerma');
const span             = document.querySelector('.close');
const listaMermas      = document.getElementById('lista-mermas');
const contenedorMermas = document.getElementById('contenedor-filas-merma');

if (btn && modal) {
    btn.onclick = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };
}
if (span && modal) {
    span.onclick = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
}
window.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
});

function sincronizarFilaMerma(inputTexto) {
    const fila        = inputTexto.closest('.fila-merma-item');
    const hiddenId    = fila.querySelector('.input-id-entrada-merma');
    const inputCant   = fila.querySelector('.input-cantidad-merma');
    const val         = inputTexto.value.trim().toLowerCase();

    if (!val || !listaMermas) {
        hiddenId.value = '';
        return;
    }

    const opt = Array.from(listaMermas.options).find(o => o.value.toLowerCase().includes(val));
    if (opt) {
        hiddenId.value = opt.getAttribute('data-id');
        const stockMax = parseFloat(opt.getAttribute('data-stock')) || 0;
        if (stockMax > 0) {
            inputCant.max = stockMax;
        }
    } else {
        hiddenId.value = '';
    }
}

function agregarOtraFilaMerma() {
    if (!contenedorMermas) return;

    const div = document.createElement('div');
    div.className = 'fila-merma-item bg-gray-50/70 p-4 rounded-2xl border border-gray-100 space-y-3 relative';
    div.innerHTML = `
        <button type="button" onclick="this.closest('.fila-merma-item').remove()" 
                class="absolute top-3 right-3 text-gray-400 hover:text-rose-600 text-xs font-bold px-2 py-0.5 rounded-lg bg-white border border-gray-200"
                title="Quitar esta merma">
            ✕ Quitar
        </button>
        <div>
            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Buscar Producto en Existencia</label>
            <input list="lista-mermas" 
                   placeholder="Escribe el producto..." 
                   autocomplete="off"
                   oninput="sincronizarFilaMerma(this)"
                   class="input-busqueda-merma w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm text-gray-700 focus:ring-2 focus:ring-[#a1143a]/20 outline-none"
                   required>
            <input type="hidden" name="id_entrada[]" class="input-id-entrada-merma">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Cantidad</label>
            <input type="number" name="cantidad[]" min="0.01" step="0.01" placeholder="0.00" required 
                   class="input-cantidad-merma w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-[#a1143a]/20 outline-none">
        </div>
    `;
    contenedorMermas.appendChild(div);
    lucide.createIcons();
}

// Validación al enviar el formulario de mermas
const formRegistrarMerma = document.getElementById('formRegistrarMerma');
if (formRegistrarMerma) {
    formRegistrarMerma.addEventListener('submit', function(e) {
        const filas = contenedorMermas.querySelectorAll('.fila-merma-item');
        for (const fila of filas) {
            const inputTexto = fila.querySelector('.input-busqueda-merma');
            const hiddenId   = fila.querySelector('.input-id-entrada-merma');
            const val        = inputTexto.value.trim().toLowerCase();
            const opt        = Array.from(listaMermas.options).find(o => o.value.toLowerCase().includes(val));

            if (!opt || val === '') {
                e.preventDefault();
                Swal.fire('Selección inválida', 'Por favor selecciona un producto válido de la lista en todas las filas.', 'error');
                inputTexto.focus();
                return;
            }
            inputTexto.value = opt.value;
            hiddenId.value   = opt.getAttribute('data-id');
        }
    });
}

// ─────────────────────────────────────────────
//  AUTOCOMPLETADO DE ENTRADAS CON LA ÚLTIMA COMPRA
// ─────────────────────────────────────────────
const formularioEntrada   = document.querySelector('form[action*="registrar-entrada"]');
const inputBusqueda       = document.getElementById('busqueda_producto');
const listaProductos      = document.getElementById('lista-productos');
const hiddenInput         = document.getElementById('id_producto_hidden');
const ayudaCompraAnterior = document.getElementById('ayuda-compra-anterior');

function normalizarNombreProducto(nombre) {
    return nombre.trim().toLocaleLowerCase();
}

function encontrarOpcionProducto(texto) {
    if (!texto || !listaProductos) return null;

    const valor = normalizarNombreProducto(texto);
    const opciones = Array.from(listaProductos.options);
    const exacta = opciones.find(opcion => normalizarNombreProducto(opcion.value) === valor);
    if (exacta) return exacta;

    const coincidencias = opciones.filter(opcion =>
        normalizarNombreProducto(opcion.value).startsWith(valor)
    );

    return coincidencias.length === 1 ? coincidencias[0] : null;
}

function limpiarCompraAutocompletada() {
    document.getElementById('precio_entrada').value = '';
    document.getElementById('equivalente').value = '';
    selectUCompra.selectedIndex = 0;
    selectUVenta.selectedIndex = 0;
    cambiarTextoConversion();
    calcularEquivalente();
}

function sincronizarProductoEntrada() {
    if (!inputBusqueda || !hiddenInput) return;

    const opcion = encontrarOpcionProducto(inputBusqueda.value);
    if (!opcion) {
        if (hiddenInput.value !== '') {
            hiddenInput.value = '';
            hiddenInput.dataset.productoSeleccionado = '';
            limpiarCompraAutocompletada();
        }
        if (ayudaCompraAnterior) {
            ayudaCompraAnterior.textContent = 'Selecciona un producto de la lista para cargar su última compra.';
        }
        return;
    }

    const idProducto = opcion.dataset.id;
    hiddenInput.value = idProducto;

    if (hiddenInput.dataset.productoSeleccionado === idProducto) return;
    hiddenInput.dataset.productoSeleccionado = idProducto;

    document.getElementById('precio_entrada').value = opcion.dataset.precio || '';
    document.getElementById('equivalente').value = opcion.dataset.equivalente || '';
    selectUCompra.value = opcion.dataset.unidadCompra || selectUCompra.options[0].value;
    selectUVenta.value = opcion.dataset.unidadVenta || selectUVenta.options[0].value;
    cambiarTextoConversion();
    calcularEquivalente();

    if (ayudaCompraAnterior) {
        ayudaCompraAnterior.textContent = opcion.dataset.tieneHistorial === '1'
            ? 'Datos de la última compra cargados. Puedes modificarlos para esta entrada.'
            : 'Este producto no tiene compras anteriores; completa los datos de compra.';
    }
}

inputBusqueda?.addEventListener('input', sincronizarProductoEntrada);
inputBusqueda?.addEventListener('change', sincronizarProductoEntrada);

if (formularioEntrada) {
    formularioEntrada.addEventListener('submit', function(e) {
        sincronizarProductoEntrada();
        if (!hiddenInput.value) {
            e.preventDefault();
            Swal.fire('Producto no válido', 'Por favor selecciona un producto activo de la lista.', 'error');
            hiddenInput.value = '';
            inputBusqueda.focus();
            return;
        }
        inputBusqueda.value = Array.from(listaProductos.options)
            .find(opcion => opcion.dataset.id === hiddenInput.value).value;

        if (inputFechaEntrada && inputFechaEntrada.value) {
            const fechaIng = new Date(inputFechaEntrada.value + 'T00:00:00');
            const hoy      = new Date(); hoy.setHours(0, 0, 0, 0);
            const hace7    = new Date(); hace7.setDate(hoy.getDate() - 7); hace7.setHours(0, 0, 0, 0);
            if (fechaIng > hoy || fechaIng < hace7) {
                e.preventDefault();
                Swal.fire('Fecha inválida', 'No puede ser futura ni mayor a una semana en el pasado.', 'error');
                inputFechaEntrada.focus();
            }
        }
    });
}

// ─────────────────────────────────────────────
//  RADIOGRAFÍA DE STOCK Y KARDEX
// ─────────────────────────────────────────────
function abrirRadiografia(idProducto, nombreProducto) {
    document.getElementById('rad_nombre').innerText    = nombreProducto;
    document.getElementById('rad_fisico').innerText    = '...';
    document.getElementById('rad_bloqueado').innerText = '...';
    document.getElementById('rad_libre').innerText     = '...';
    document.getElementById('rad_ventas').innerText    = '...';
    document.getElementById('rad_mermas').innerText    = '...';
    document.getElementById('tablaKardex').innerHTML   = '<tr><td colspan="4" class="text-center py-6 text-gray-400">Cargando movimientos...</td></tr>';

    const modalRad = document.getElementById('modalRadiografia');
    modalRad.classList.remove('hidden');
    modalRad.classList.add('flex');

    fetch(`${BASE_URL}inventario/detalles-stock/${idProducto}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('rad_fisico').innerText    = data.stock ? data.stock.exis_total_general : 0;
                document.getElementById('rad_bloqueado').innerText = data.stock ? data.stock.exis_bloqueo : 0;
                document.getElementById('rad_libre').innerText     = data.stock ? data.stock.exis_venta : 0;
                document.getElementById('rad_ventas').innerText    = data.ventas ? data.ventas.total_ventas : 0;
                document.getElementById('rad_mermas').innerText    = data.mermas ? parseFloat(data.mermas.total_mermas || 0).toFixed(2) : 0;

                const tbody = document.getElementById('tablaKardex');
                tbody.innerHTML = '';

                if (data.kardex && data.kardex.length > 0) {
                    data.kardex.forEach(mov => {
                        const color = mov.movimiento.includes('Entrada') 
                            ? 'text-emerald-600' 
                            : (mov.movimiento.includes('Venta') ? 'text-blue-600' : 'text-rose-600');

                        tbody.innerHTML += `
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 text-gray-500">${(mov.fecha || '').substring(0, 10)}</td>
                                <td class="p-3 font-bold ${color}">${mov.movimiento}</td>
                                <td class="p-3 font-extrabold text-center text-gray-800">${parseFloat(mov.cant || 0).toFixed(2)}</td>
                                <td class="p-3 text-[11px] text-gray-500">${mov.detalle || '-'}</td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-6 text-gray-400 italic">Sin movimientos registrados</td></tr>';
                }
            } else {
                Swal.fire('Error', data.error || 'No se pudo cargar la radiografía', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'No se pudo consultar el historial del producto', 'error');
        });
}

function cerrarRadiografia() {
    const modalRad = document.getElementById('modalRadiografia');
    modalRad.classList.add('hidden');
    modalRad.classList.remove('flex');
}

// ─────────────────────────────────────────────
//  BUSCADORES DE TABLAS EN TIEMPO REAL
// ─────────────────────────────────────────────
document.getElementById('filtro-entradas')?.addEventListener('input', function() {
    const b     = this.value.toLowerCase();
    const filas = document.querySelectorAll('#tbody-entradas .fila-entrada');
    let hayResultados = false;
    filas.forEach(fila => {
        const coincide = fila.querySelector('td:first-child').textContent.toLowerCase().includes(b);
        fila.style.display = coincide ? '' : 'none';
        if (coincide) hayResultados = true;
    });
    document.getElementById('sin-resultados-entradas')?.classList.toggle('hidden', hayResultados);
});

document.getElementById('filtro-existencias')?.addEventListener('input', function() {
    const b = this.value.toLowerCase();
    document.querySelectorAll('#seccion-existencias tbody tr').forEach(fila => {
        const celda = fila.querySelector('.prod-nombre');
        if (!celda) return;
        fila.style.display = celda.textContent.toLowerCase().includes(b) ? '' : 'none';
    });
});

document.getElementById('filtro-mermas')?.addEventListener('keyup', function() {
    const b = this.value.toLowerCase();
    document.querySelectorAll('#seccion-mermas tbody tr').forEach(fila => {
        fila.style.display = fila.querySelector('td:first-child').textContent.toLowerCase().includes(b) ? '' : 'none';
    });
});