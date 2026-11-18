<?php
namespace App\Controllers;

use App\Libraries\AccountCredentials;
use App\Libraries\Geocoder;
use App\Libraries\Osrm;
use App\Libraries\RutaDijkstra;
use App\Models\RepartidorModel;

class RepartidoresController extends BaseController
{
    private const MAX_PARADAS_MAPA    = 25;    // tope de paradas por cálculo (límite del OSRM público)
    private const MAX_CANDIDATOS_MAPA = 60;    // pedidos sin asignar que se evalúan por calle
    private const TOPE_MATRIZ         = 100;   // máx. de puntos en una consulta "table" de OSRM
    private const VELOCIDAD_KMH       = 28.0;  // respaldo en línea recta si OSRM no responde

    public function index()
    {
        if (session()->get('auth_role') === 'repartidor') {
            return $this->portalRepartidor();
        }

        $model    = new RepartidorModel();
        $busqueda = trim($this->request->getVar('buscar') ?? '');

        $repartidores = $model->getRepartidores($busqueda)->paginate(9, 'group1');

        $data = [
            'title'        => 'Catálogo de Repartidores',
            'repartidores' => $repartidores,
            'pager'        => $model->pager,
            'busqueda'     => $busqueda,
            'isAjax'       => $this->request->isAJAX(),
        ];

        return view('repartidores', $data);
    }

    /**
     * Vista operativa para el rol Repartidor (Funcionalidades 7 y 8).
     */
    private function portalRepartidor()
    {
        $idRepartidor = (int) session()->get('repartidor_id');
        $db           = db_connect();

        $repartidor = $db->table('repartidor')
            ->where('id_repartidor', $idRepartidor)
            ->get()
            ->getRowArray();

        $datos = $this->obtenerDatosEntregas($idRepartidor);

        return view('repartidor_entregas', [
            'repartidor'   => $repartidor,
            'asignados'    => $datos['asignados'],
            'disponibles'  => $datos['disponibles'],
            'entregados'   => $datos['entregados'],
            'stats'        => $datos['stats'],
            'firmaEstado'  => $datos['firma'],
            'vistaInicial' => $this->request->getGet('vista') ?? 'asignados',
        ]);
    }

    /**
     * Endpoint JSON ligero para avistamiento en tiempo real de nuevos pedidos (Funcionalidad 8 y RNF-08).
     */
    public function actualizaciones()
    {
        if (session()->get('auth_role') !== 'repartidor') {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        }

        $idRepartidor = (int) session()->get('repartidor_id');
        $datos        = $this->obtenerDatosEntregas($idRepartidor);

        // RNF-08: si nada cambió desde la última firma del cliente, respuesta mínima.
        $firmaCliente = (string) $this->request->getGet('firma');
        if ($firmaCliente !== '' && hash_equals((string) $datos['firma'], $firmaCliente)) {
            return $this->response->setJSON([
                'ok'         => true,
                'sinCambios' => true,
                'firma'      => $datos['firma'],
                'timestamp'  => date('H:i:s'),
            ]);
        }

        return $this->response->setJSON([
            'ok'          => true,
            'stats'       => $datos['stats'],
            'asignados'   => $datos['asignados'],
            'disponibles' => $datos['disponibles'],
            'entregados'  => $datos['entregados'],
            'firma'       => $datos['firma'],
            'timestamp'   => date('H:i:s'),
        ]);
    }

    /**
     * Ruta sugerida con Dijkstra para los pedidos activos del repartidor.
     * Opcional: ?lat=&lng= (GPS del repartidor). Sin GPS, sale de la tienda (.env).
     */
    public function ruta()
    {
        if (session()->get('auth_role') !== 'repartidor') {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        }

        $idRepartidor = (int) session()->get('repartidor_id');
        $db           = db_connect();

        // ?pedidos=12,15  → recorrido con esos pedidos (modo "Iniciar recorrido")
        $ids = array_values(array_filter(
            array_map('intval', explode(',', (string) $this->request->getGet('pedidos'))),
            static fn ($i) => $i > 0
        ));

        $filas = $db->query("
            SELECT p.id_pedido, p.id_repartidor,
                   c.nombre, c.ap_paterno, c.ap_materno,
                   d.id_direccion, d.calle, d.numero, d.colonia, d.municipio, d.estado AS dir_estado,
                   d.latitud, d.longitud, d.geo_precision, d.geo_intento_at,
                   p.fecha_entrega_programada, p.horario_entrega,
                   (
                       SELECT s.estado FROM status s
                       WHERE s.id_pedido2 = p.id_pedido
                         AND s.estado IN ('pedido','confirmado','en_transito','entregado','cancelado')
                       ORDER BY s.id_status DESC LIMIT 1
                   ) AS estado_logistico
            FROM pedido p
            INNER JOIN cliente c ON c.id_cliente = p.id_cliente1
            LEFT JOIN direccion d ON d.id_cliente = c.id_cliente
            WHERE p.id_repartidor = ?
               OR (? = 1 AND p.id_repartidor IS NULL)
            ORDER BY p.id_pedido ASC, (d.latitud IS NULL) ASC, d.id_direccion ASC
        ", [$idRepartidor, 1])->getResultArray();

        // Un registro por pedido, solo los que siguen activos.
        // Un registro por pedido. Los míos: confirmados o en tránsito.
        // Los que nadie ha tomado (solo en modo recorrido): únicamente confirmados.
        $unicos = [];
        foreach ($filas as $f) {
            $id      = (int) $f['id_pedido'];
            $propio  = (int) $f['id_repartidor'] === $idRepartidor;
            $validos = $propio ? ['confirmado', 'en_transito'] : ['confirmado'];
            if (isset($unicos[$id]) || ! in_array($f['estado_logistico'], $validos, true)) {
                continue;
            }
            $f['disponible'] = ! $propio;
            $unicos[$id]     = $f;
        }

        // Los pedidos elegidos se geocodifican primero.
        // Orden: elegidos, luego los míos, luego los sin asignar.
        $rango = static fn ($id) => in_array($id, $ids, true) ? 0 : ($unicos[$id]['disponible'] ? 2 : 1);
        uksort($unicos, static fn ($a, $b) => $rango($a) <=> $rango($b));

        // Direcciones sin coordenadas: se geocodifican ahora (máx. 2 por consulta) y se guardan.
        $geocodificadas = 0;
        foreach ($unicos as $id => $f) {
            if ($f['id_direccion'] === null || ($f['latitud'] !== null && $f['longitud'] !== null) || $geocodificadas >= 2) {
                continue;
            }
            // En modo recorrido (pedidos elegidos) se reintenta siempre; en el panel, máx. 1 vez al día.
            $elegido  = in_array($id, $ids, true);
            $reciente = ! $elegido && $f['geo_intento_at'] !== null && strtotime($f['geo_intento_at']) > time() - 86400;
            if ($reciente) {
                continue; // ya se intentó hoy y falló
            }

            $geocodificadas++;
            $campos = Geocoder::camposParaGuardar([
                'calle' => $f['calle'], 'numero' => $f['numero'], 'colonia' => $f['colonia'],
                'municipio' => $f['municipio'], 'estado' => $f['dir_estado'],
            ]);
            $db->table('direccion')->where('id_direccion', $f['id_direccion'])->update($campos);
            if ($campos['latitud'] !== null) {
                $unicos[$id]['latitud']       = $campos['latitud'];
                $unicos[$id]['longitud']      = $campos['longitud'];
                $unicos[$id]['geo_precision'] = $campos['geo_precision'] ?? null;
            }
            if (Geocoder::huboError()) {
                break; // el servicio no responde: no se hacen más intentos en esta consulta
            }
        }

        $pedidos = [];
        foreach ($unicos as $id => $f) {
            $partes = [];
            foreach ([['calle', 'numero'], ['colonia'], ['municipio'], ['dir_estado']] as $campos) {
                $txt = trim((string) ($f[$campos[0]] ?? ''));
                if ($txt === '' || in_array(strtolower($txt), ['null', 'nnn'], true)) {
                    continue;
                }
                if ($campos[0] === 'calle' && (int) $f['numero'] > 0) {
                    $txt .= ' #' . (int) $f['numero'];
                }
                $partes[] = $txt;
            }

            $pedidos[] = [
                'id_pedido'      => $id,
                'cliente_nombre' => trim($f['nombre'] . ' ' . $f['ap_paterno']),
                'direccion'      => $partes ? implode(', ', $partes) : 'Dirección sin confirmar',
                'estado'         => $f['estado_logistico'],
                'disponible'     => (bool) $f['disponible'],
                'lat'            => $f['latitud'] !== null ? (float) $f['latitud'] : null,
                'lng'            => $f['longitud'] !== null ? (float) $f['longitud'] : null,
                'precision'      => $f['geo_precision'] ?? null,
                'limite_ts'      => $this->horaLimite($f['fecha_entrega_programada'] ?? null, $f['horario_entrega'] ?? null),
            ];
        }

        // Punto de salida: GPS del repartidor si lo mandó; si no, la tienda.
        $lat = filter_var($this->request->getGet('lat'), FILTER_VALIDATE_FLOAT);
        $lng = filter_var($this->request->getGet('lng'), FILTER_VALIDATE_FLOAT);
        $conGps = $lat !== false && $lng !== false && abs($lat) <= 90 && abs($lng) <= 180;
        $origen = $conGps
            ? ['lat' => (float) $lat, 'lng' => (float) $lng]
            : ['lat' => (float) env('fruver.origenLat', 19.3139), 'lng' => (float) env('fruver.origenLng', -97.9206)];

        if ($ids) {
            $seleccion = array_values(array_filter($pedidos, static fn ($p) => in_array($p['id_pedido'], $ids, true)));
            $resto     = array_values(array_filter($pedidos, static fn ($p) => ! in_array($p['id_pedido'], $ids, true)));
            $calc      = $this->planYCercanos($origen, $seleccion, $resto);
            $plan      = $calc['plan'];
            $cercanos  = $calc['cercanos'];

            // Resto de pedidos activos que NO quedan de paso: se muestran en el mapa para
            // dar contexto, pero no se pueden agregar (los sin asignar) ni alteran la ruta.
            $idsCercanos  = array_column($cercanos, 'id_pedido');
            $otros        = [];
            $sinUbicacion = [];
            foreach ($resto as $p) {
                if (in_array($p['id_pedido'], $idsCercanos, true)) {
                    continue;
                }
                if ($p['lat'] === null || $p['lng'] === null) {
                    $sinUbicacion[] = $p['id_pedido'];
                    continue;
                }
                $otros[] = $p;
            }

            return $this->response->setJSON([
                'ok'            => true,
                'origen_gps'    => $conGps,
                'ruta'          => $plan,
                'cercanos'      => $cercanos,
                'otros'         => $otros,
                'sin_ubicacion' => $sinUbicacion,
                'timestamp'     => date('H:i:s'),
            ]);
        }

        // Siempre ofrecer pedidos cercanos (de cualquier usuario), no solo en modo recorrido.
        $misPedidos  = array_values(array_filter($pedidos, static fn ($p) => ! $p['disponible']));
        $otroPedidos = array_values(array_filter($pedidos, static fn ($p) => $p['disponible']));
        $plan        = RutaDijkstra::planificar($origen, $misPedidos);
        $cercanos    = ($plan['hay_ruta'] && $otroPedidos)
            ? RutaDijkstra::cercanos($origen, $plan['paradas'], $otroPedidos)
            : [];

        return $this->response->setJSON([
            'ok'         => true,
            'origen_gps' => $conGps,
            'ruta'       => $plan,
            'cercanos'   => $cercanos,
            'mapa'       => $this->armarMapa($origen, $misPedidos, $otroPedidos),
            'timestamp'  => date('H:i:s'),
        ]);
    }

    // ───────────────────────── Mapa fijo: calles, "de paso" y horarios ─────────────────────────

    /**
     * Hora límite prometida (timestamp unix) a partir de fecha_entrega_programada + horario_entrega.
     * Se interpreta en la zona horaria del negocio (fruver.timezone). Sin horario: null.
     */
    private function horaLimite($fecha, $horario): ?int
    {
        $fecha   = trim((string) $fecha);
        $horario = trim((string) $horario);
        if ($fecha === '' || $fecha === '0000-00-00' || $horario === '' || $horario === '00:00:00') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($fecha . ' ' . $horario, RutaDijkstra::zona()))->getTimestamp();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function horaTexto(int $ts): string
    {
        return (new \DateTimeImmutable('@' . $ts))->setTimezone(RutaDijkstra::zona())->format('H:i');
    }

    /**
     * Distancias (km) y tiempos (min) entre puntos: por calles (OSRM) o, si no responde,
     * línea recta × FACTOR_VIAL a VELOCIDAD_KMH.
     *
     * @return array{km: array, min: array, fuente: string}
     */
    private function matrizPuntos(array $puntos): array
    {
        $m = count($puntos) <= self::TOPE_MATRIZ ? Osrm::matrices($puntos) : null;
        if ($m !== null) {
            return $m + ['fuente' => 'calles'];
        }

        $km = [];
        $mn = [];
        foreach ($puntos as $i => $a) {
            foreach ($puntos as $j => $b) {
                $d          = $i === $j ? 0.0 : RutaDijkstra::haversine($a, $b) * RutaDijkstra::FACTOR_VIAL;
                $km[$i][$j] = $d;
                $mn[$i][$j] = $d / self::VELOCIDAD_KMH * 60;
            }
        }

        return ['km' => $km, 'min' => $mn, 'fuente' => 'recta'];
    }

    /**
     * Descarta a los candidatos que obviamente no quedan de paso (en línea recta, con margen)
     * para no gastar puntos de la consulta a OSRM, y deja los mejores.
     */
    private function candidatosPlausibles(array $origen, array $seleccion, array $candidatos): array
    {
        $base = [['lat' => $origen['lat'], 'lng' => $origen['lng']]];
        foreach ($seleccion as $p) {
            $base[] = ['lat' => $p['lat'], 'lng' => $p['lng']];
        }

        $puntuados = [];
        foreach ($candidatos as $c) {
            if (! isset($c['lat'], $c['lng'])) {
                continue;
            }
            $minPunto = INF;
            $mejor    = INF;
            foreach ($base as $i => $a) {
                $dAC      = RutaDijkstra::haversine($a, $c);
                $minPunto = min($minPunto, $dAC);
                foreach ($base as $j => $b) {
                    if ($j <= $i) {
                        continue;
                    }
                    $ab     = RutaDijkstra::haversine($a, $b);
                    $desv   = $dAC + RutaDijkstra::haversine($c, $b) - $ab;
                    $limite = max(RutaDijkstra::DESVIO_MAX_KM, RutaDijkstra::DESVIO_MAX_PCT * $ab);
                    $mejor  = min($mejor, $desv / $limite);
                }
            }
            // Margen 1.5: por calles el desvío puede ser menor que en línea recta.
            if ($minPunto <= RutaDijkstra::CERCA_PARADA_KM * 1.5 || $mejor <= 1.5) {
                $puntuados[] = [min($minPunto / RutaDijkstra::CERCA_PARADA_KM, $mejor), $c];
            }
        }
        usort($puntuados, static fn ($x, $y) => $x[0] <=> $y[0]);

        $tope = max(0, min(self::MAX_CANDIDATOS_MAPA, self::TOPE_MATRIZ - 1 - count($seleccion)));

        return array_values(array_map(static fn ($x) => $x[1], array_slice($puntuados, 0, $tope)));
    }

    /**
     * Ruta (Dijkstra) de la selección + pedidos que quedan de paso, ambos con distancias por calle.
     * Es el mismo cálculo para la lista, el mapa y la validación al tomar un pedido.
     *
     * @return array{plan: array, cercanos: array, matriz: ?array, idx_sel: array, idx_cand: array, cands: array}
     */
    private function planYCercanos(array $origen, array $seleccion, array $candidatos): array
    {
        $sel = array_values(array_filter($seleccion, static fn ($p) => isset($p['lat'], $p['lng'])));
        if (! $sel) {
            return [
                'plan'     => RutaDijkstra::planificarSeleccion($origen, $seleccion),
                'cercanos' => [], 'matriz' => null, 'idx_sel' => [], 'idx_cand' => [], 'cands' => [],
            ];
        }

        $cands  = $this->candidatosPlausibles($origen, $sel, $candidatos);
        $n      = count($sel);
        $puntos = [['lat' => $origen['lat'], 'lng' => $origen['lng']]];
        foreach (array_merge($sel, $cands) as $p) {
            $puntos[] = ['lat' => $p['lat'], 'lng' => $p['lng']];
        }

        $m      = $this->matrizPuntos($puntos);
        $calles = $m['fuente'] === 'calles';

        // Bloque [salida + paradas] de la matriz grande, para ordenar la visita con Dijkstra.
        $bloque = static function (array $nodos) use ($m, $n): ?array {
            if (count($nodos) !== $n + 1) {
                return null;
            }
            $km = [];
            for ($i = 0; $i <= $n; $i++) {
                for ($j = 0; $j <= $n; $j++) {
                    $km[$i][$j] = $m['km'][$i][$j];
                }
            }

            return ['km' => $km];
        };
        $plan = RutaDijkstra::planificarSeleccion($origen, $seleccion, $calles ? $bloque : null);

        $idxSel = [];
        foreach ($sel as $i => $p) {
            $idxSel[(int) $p['id_pedido']] = $i + 1;
        }
        $idxCand = [];
        foreach ($cands as $k => $c) {
            $idxCand[(int) $c['id_pedido']] = $n + 1 + $k;
        }

        $cercanos = [];
        if ($plan['hay_ruta'] && $cands) {
            $perm = [0];
            foreach ($plan['paradas'] as $p) {
                $perm[] = $idxSel[(int) $p['id_pedido']];
            }
            foreach ($cands as $k => $_) {
                $perm[] = $n + 1 + $k;
            }
            $kmP = null;
            if ($calles) {
                $kmP = [];
                foreach ($perm as $a => $ia) {
                    foreach ($perm as $b => $ib) {
                        $kmP[$a][$b] = $m['km'][$ia][$ib];
                    }
                }
            }
            $cercanos = RutaDijkstra::cercanos($origen, $plan['paradas'], $cands, $kmP);
        }

        return [
            'plan' => $plan, 'cercanos' => $cercanos, 'matriz' => $m,
            'idx_sel' => $idxSel, 'idx_cand' => $idxCand, 'cands' => $cands,
        ];
    }

    /**
     * Datos del mapa fijo del panel: todas mis entregas activas en ruta (con horarios), los pedidos
     * sin asignar que quedan de paso (con el efecto de agregarlos) y el resto como contexto.
     */
    private function armarMapa(array $origen, array $mios, array $disponibles): array
    {
        $sinUbicacion = [];
        $misCoord     = [];
        foreach ($mios as $p) {
            if ($p['lat'] === null || $p['lng'] === null) {
                $sinUbicacion[] = $p['id_pedido'];
            } else {
                $misCoord[] = $p;
            }
        }
        $omitidas = [];
        if (count($misCoord) > self::MAX_PARADAS_MAPA) {
            $omitidas = array_column(array_slice($misCoord, self::MAX_PARADAS_MAPA), 'id_pedido');
            $misCoord = array_slice($misCoord, 0, self::MAX_PARADAS_MAPA);
        }
        $dispCoord = [];
        foreach ($disponibles as $p) {
            if ($p['lat'] === null || $p['lng'] === null) {
                $sinUbicacion[] = $p['id_pedido'];
            } else {
                $dispCoord[] = $p;
            }
        }

        $resumen = static fn (array $p): array => [
            'id_pedido'      => $p['id_pedido'],
            'cliente_nombre' => $p['cliente_nombre'],
            'direccion'      => $p['direccion'],
            'lat'            => $p['lat'],
            'lng'            => $p['lng'],
            'precision'      => $p['precision'] ?? null,
        ];

        $vacio = [
            'hay_ruta' => false, 'motivo' => '', 'origen' => $origen, 'paradas' => [], 'trazo' => [],
            'trazo_calles' => false, 'distancia_km' => 0.0, 'duracion_min' => 0, 'llegada_final' => null,
            'google_url' => null, 'truncada' => false, 'fuente' => null, 'omitidas' => $omitidas,
            'sin_ubicacion' => $sinUbicacion, 'de_paso' => [], 'otros' => [],
        ];

        $calc = $this->planYCercanos($origen, $misCoord, $dispCoord);
        $plan = $calc['plan'];
        if (! $plan['hay_ruta']) {
            $vacio['motivo'] = $mios
                ? 'Tus entregas aún no tienen ubicación en el mapa.'
                : 'No tienes entregas activas; abajo ves los pedidos sin asignar.';
            $vacio['otros'] = array_map($resumen, $dispCoord);

            return $vacio;
        }

        $m        = $calc['matriz'];
        $servicio = (float) env('fruver.minutosPorParada', 4);
        $ahora    = time();

        $limPorIdx = [];
        $idPorIdx  = [];
        foreach ($misCoord as $p) {
            $ix             = $calc['idx_sel'][(int) $p['id_pedido']];
            $limPorIdx[$ix] = $p['limite_ts'] ?? null;
            $idPorIdx[$ix]  = (int) $p['id_pedido'];
        }
        foreach ($calc['cands'] as $c) {
            $ix             = $calc['idx_cand'][(int) $c['id_pedido']];
            $limPorIdx[$ix] = $c['limite_ts'] ?? null;
            $idPorIdx[$ix]  = (int) $c['id_pedido'];
        }

        // Llegadas (timestamps) siguiendo un orden de nodos de la matriz.
        $simular = static function (array $orden) use ($m, $servicio, $ahora): array {
            $t = $ahora;
            $prev = 0;
            $res = [];
            foreach ($orden as $ix) {
                $t += $m['min'][$prev][$ix] * 60;
                $res[] = $t;
                $t += $servicio * 60;
                $prev = $ix;
            }

            return $res;
        };

        $orden = [];
        foreach ($plan['paradas'] as $p) {
            $orden[] = $calc['idx_sel'][(int) $p['id_pedido']];
        }
        $llegadas = $simular($orden);

        $paradas  = [];
        $prev     = 0;
        $minTotal = 0.0;
        $tardeBase = [];
        foreach ($plan['paradas'] as $pos => $p) {
            $ix      = $orden[$pos];
            $minTr   = $m['min'][$prev][$ix];
            $minTotal += $minTr;
            $lim     = $limPorIdx[$ix] ?? null;
            $tarde   = $lim !== null ? max(0.0, ($llegadas[$pos] - $lim) / 60) : 0.0;
            $tardeBase[$ix] = $tarde;
            $paradas[] = [
                'orden'          => $p['orden'],
                'id_pedido'      => $p['id_pedido'],
                'cliente_nombre' => $p['cliente_nombre'],
                'direccion'      => $p['direccion'],
                'lat'            => $p['lat'],
                'lng'            => $p['lng'],
                'precision'      => $p['precision'] ?? null,
                'km_tramo'       => $p['km_tramo'],
                'min_tramo'      => (int) round($minTr),
                'llegada'        => $this->horaTexto((int) $llegadas[$pos]),
                'limite'         => $lim !== null ? $this->horaTexto($lim) : null,
                'tarde_min'      => (int) round($tarde),
            ];
            $prev = $ix;
        }

        // Pedidos sin asignar que quedan de paso: mejor posición de inserción y a quién atrasarían.
        $dePaso   = [];
        $idsPaso  = [];
        foreach ($calc['cercanos'] as $c) {
            $ci        = $calc['idx_cand'][(int) $c['id_pedido']];
            $mejorK    = 0;
            $mejorExtra = INF;
            for ($k = 0, $n = count($orden); $k <= $n; $k++) {
                $a     = $k === 0 ? 0 : $orden[$k - 1];
                $extra = $m['min'][$a][$ci] + $servicio;
                if ($k < $n) {
                    $extra += $m['min'][$ci][$orden[$k]] - $m['min'][$a][$orden[$k]];
                }
                if ($extra < $mejorExtra) {
                    $mejorExtra = $extra;
                    $mejorK     = $k;
                }
            }

            $nuevo = $orden;
            array_splice($nuevo, $mejorK, 0, [$ci]);
            $lleg   = $simular($nuevo);
            $atrasa = [];
            $tardePropio = 0.0;
            foreach ($nuevo as $pos => $ix) {
                $lim = $limPorIdx[$ix] ?? null;
                if ($lim === null) {
                    continue;
                }
                $tarde = max(0.0, ($lleg[$pos] - $lim) / 60);
                if ($ix === $ci) {
                    $tardePropio = $tarde;
                } elseif ($tarde - ($tardeBase[$ix] ?? 0.0) >= 1.0) {
                    $atrasa[] = [
                        'id_pedido' => $idPorIdx[$ix],
                        'min_extra' => (int) round($tarde - ($tardeBase[$ix] ?? 0.0)),
                        'tarde_min' => (int) round($tarde),
                    ];
                }
            }

            $lim = $limPorIdx[$ci] ?? null;
            $dePaso[] = $resumen($c) + [
                'desvio_km'      => $c['desvio_km'],
                'dist_parada_km' => $c['dist_parada_km'],
                'extra_min'      => (int) round($mejorExtra),
                'limite'         => $lim !== null ? $this->horaTexto($lim) : null,
                'tarde_min'      => (int) round($tardePropio),
                'atrasa'         => $atrasa,
            ];
            $idsPaso[] = (int) $c['id_pedido'];
        }

        $otros = [];
        foreach ($dispCoord as $p) {
            if (! in_array((int) $p['id_pedido'], $idsPaso, true)) {
                $otros[] = $resumen($p);
            }
        }

        // Trazo sobre las calles (OSRM); sin respuesta, líneas rectas.
        $puntos = [['lat' => $origen['lat'], 'lng' => $origen['lng']]];
        foreach ($plan['paradas'] as $p) {
            $puntos[] = ['lat' => $p['lat'], 'lng' => $p['lng']];
        }
        $calle = $m['fuente'] === 'calles' ? Osrm::ruta($puntos) : null;
        $trazo = $calle['trazo'] ?? array_map(static fn (array $q): array => [$q['lat'], $q['lng']], $puntos);

        return [
            'hay_ruta'      => true,
            'motivo'        => 'Ruta más corta por todas tus entregas activas.',
            'origen'        => $origen,
            'paradas'       => $paradas,
            'trazo'         => $trazo,
            'trazo_calles'  => $calle !== null,
            'distancia_km'  => $plan['distancia_km'],
            'duracion_min'  => (int) round($minTotal),
            'llegada_final' => $paradas ? end($paradas)['llegada'] : null,
            'google_url'    => RutaDijkstra::urlGoogleMaps($origen, $plan['paradas']),
            'truncada'      => count($plan['paradas']) > RutaDijkstra::MAX_PARADAS_GOOGLE,
            'fuente'        => $plan['fuente'] ?? 'recta',
            'omitidas'      => $omitidas,
            'sin_ubicacion' => $sinUbicacion,
            'de_paso'       => $dePaso,
            'otros'         => $otros,
        ];
    }

    /**
     * Permite a un repartidor tomar un pedido confirmado que aún no tiene repartidor asignado.
     */
    public function tomarPedido()
    {
        if (session()->get('auth_role') !== 'repartidor') {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'mensaje' => 'No autorizado.']);
        }

        $idPedido     = (int) $this->request->getPost('id_pedido');
        $idRepartidor = (int) session()->get('repartidor_id');

        if ($idPedido <= 0 || $idRepartidor <= 0) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Pedido no válido.']);
        }

        $db     = db_connect();
        $pedido = $db->table('pedido')->where('id_pedido', $idPedido)->get()->getRowArray();

        if (! $pedido) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'El pedido no existe.']);
        }

        if (! empty($pedido['id_repartidor']) && (int) $pedido['id_repartidor'] !== $idRepartidor) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Este pedido ya fue tomado por otro repartidor.']);
        }

        $estadoActual = $this->obtenerEstadoLogistico($idPedido);
        if (! in_array($estadoActual, ['confirmado', 'en_transito'], true)) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Solo se pueden tomar pedidos confirmados.']);
        }

        // Si se toma desde el recorrido, solo se permite cuando el pedido queda de paso
        // (mismo cálculo de ruta más corta que ve el repartidor). Sin ruta (pestaña
        // "Disponibles") el comportamiento es el de siempre.
        $rutaIds = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) $this->request->getPost('ruta_pedidos'))),
            static fn ($i) => $i > 0
        )));
        if ($rutaIds && empty($pedido['id_repartidor'])) {
            $oLat = filter_var($this->request->getPost('origen_lat'), FILTER_VALIDATE_FLOAT);
            $oLng = filter_var($this->request->getPost('origen_lng'), FILTER_VALIDATE_FLOAT);
            if ($oLat === false || $oLng === false || abs($oLat) > 90 || abs($oLng) > 180
                || ! $this->pedidoQuedaDePaso($idPedido, $rutaIds, $idRepartidor, ['lat' => (float) $oLat, 'lng' => (float) $oLng])) {
                return $this->response->setJSON([
                    'ok'      => false,
                    'mensaje' => 'Este pedido no queda de paso en tu ruta, así que no se puede agregar desde el recorrido.',
                ]);
            }
        }

        if (empty($pedido['id_repartidor'])) {
            $db->table('pedido')
                ->where('id_pedido', $idPedido)
                ->where('id_repartidor', null)
                ->update(['id_repartidor' => $idRepartidor]);

            if ($db->affectedRows() !== 1) {
                return $this->response->setJSON([
                    'ok'      => false,
                    'mensaje' => 'Este pedido ya fue tomado por otro repartidor. Actualiza la lista e inténtalo con otro.',
                ]);
            }
        }

        return $this->response->setJSON([
            'ok'      => true,
            'mensaje' => 'Pedido #' . $idPedido . ' asignado a tu ruta exitosamente.',
        ]);
    }

    /**
     * ¿El pedido candidato queda de paso en la ruta (Dijkstra) formada por $rutaIds?
     * Las paradas deben ser del repartidor; el candidato, un pedido sin asignar.
     */
    private function pedidoQuedaDePaso(int $idCandidato, array $rutaIds, int $idRepartidor, array $origen): bool
    {
        $ids          = array_values(array_unique(array_merge($rutaIds, [$idCandidato])));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $filas        = db_connect()->query("
            SELECT p.id_pedido, p.id_repartidor, d.latitud, d.longitud
            FROM pedido p
            INNER JOIN cliente c ON c.id_cliente = p.id_cliente1
            LEFT JOIN direccion d ON d.id_cliente = c.id_cliente
            WHERE p.id_pedido IN ($placeholders)
            ORDER BY p.id_pedido ASC, (d.latitud IS NULL) ASC, d.id_direccion ASC
        ", $ids)->getResultArray();

        $porPedido = [];
        foreach ($filas as $f) {
            $id = (int) $f['id_pedido'];
            if (! isset($porPedido[$id])) {
                $porPedido[$id] = $f;
            }
        }

        $seleccion = [];
        foreach ($rutaIds as $id) {
            $f = $porPedido[$id] ?? null;
            if (! $f || (int) $f['id_repartidor'] !== $idRepartidor || $f['latitud'] === null || $f['longitud'] === null) {
                return false;
            }
            $seleccion[] = ['id_pedido' => $id, 'lat' => (float) $f['latitud'], 'lng' => (float) $f['longitud']];
        }

        $cand = $porPedido[$idCandidato] ?? null;
        if (! $seleccion || ! $cand || $cand['latitud'] === null || $cand['longitud'] === null) {
            return false;
        }

        $calc = $this->planYCercanos($origen, $seleccion, [[
            'id_pedido' => $idCandidato,
            'lat'       => (float) $cand['latitud'],
            'lng'       => (float) $cand['longitud'],
        ]]);

        return $calc['plan']['hay_ruta'] && $calc['cercanos'] !== [];
    }

    /**
     * Avanza el estado del pedido: confirmado -> en_transito -> entregado.
     */
    public function cambiarEstado()
    {
        if (session()->get('auth_role') !== 'repartidor') {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'mensaje' => 'No autorizado.']);
        }

        $idPedido     = (int) $this->request->getPost('id_pedido');
        $nuevoEstado  = trim((string) $this->request->getPost('nuevo_estado'));
        $idRepartidor = (int) session()->get('repartidor_id');

        if ($idPedido <= 0 || ! in_array($nuevoEstado, ['en_transito', 'entregado'], true)) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Parámetros no válidos.']);
        }

        $db     = db_connect();
        $pedido = $db->table('pedido')
            ->where('id_pedido', $idPedido)
            ->where('id_repartidor', $idRepartidor)
            ->get()
            ->getRowArray();

        if (! $pedido) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Este pedido no está asignado a tu cuenta.']);
        }

        $estadoActual = $this->obtenerEstadoLogistico($idPedido);

        if ($estadoActual === 'cancelado') {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'No se puede actualizar un pedido cancelado.']);
        }

        if ($nuevoEstado === 'en_transito' && $estadoActual !== 'confirmado') {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'El pedido debe estar Confirmado para iniciar ruta.']);
        }

        if ($nuevoEstado === 'entregado' && ! in_array($estadoActual, ['confirmado', 'en_transito'], true)) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'El pedido ya fue marcado como entregado.']);
        }

        $db->transBegin();

        $db->table('status')->insert([
            'estado'     => $nuevoEstado,
            'fecha'      => date('Y-m-d H:i:s'),
            'id_pedido2' => $idPedido,
        ]);

        if ($nuevoEstado === 'entregado') {
            $db->table('pedido')
                ->where('id_pedido', $idPedido)
                ->update(['fecha_entrega' => date('Y-m-d H:i:s')]);

            // Si es venta a crédito y aún tiene deuda restante, registrar también estado 'credito'
            if (($pedido['modalidad_pago'] ?? '') === 'credito' && (float) ($pedido['deuda_restante'] ?? 0) > 0) {
                $db->table('status')->insert([
                    'estado'     => 'credito',
                    'fecha'      => date('Y-m-d H:i:s'),
                    'id_pedido2' => $idPedido,
                ]);
            }
        }

        if ($db->transStatus() === false) {
            $db->transRollback();

            return $this->response->setJSON(['ok' => false, 'mensaje' => 'No se pudo actualizar el estado del pedido.']);
        }

        $db->transCommit();

        $texto = $nuevoEstado === 'en_transito'
            ? 'Pedido #' . $idPedido . ' marcado En Camino.'
            : '¡Pedido #' . $idPedido . ' entregado con éxito!';

        return $this->response->setJSON([
            'ok'      => true,
            'mensaje' => $texto,
        ]);
    }

    private function obtenerEstadoLogistico(int $idPedido): string
    {
        $row = db_connect()->query(
            "SELECT estado FROM status 
             WHERE id_pedido2 = ? 
               AND estado IN ('pedido','confirmado','en_transito','entregado','cancelado')
             ORDER BY id_status DESC LIMIT 1",
            [$idPedido]
        )->getRowArray();

        return $row['estado'] ?? 'pedido';
    }

    private function obtenerDatosEntregas(int $idRepartidor): array
    {
        $db = db_connect();

        // Consulta optimizada de pedidos relevantes para el rol Repartidor
        $sql = "
            SELECT 
                p.id_pedido,
                p.fecha,
                COALESCE(p.total, 0) AS total,
                COALESCE(p.modalidad_pago, 'contado') AS modalidad_pago,
                COALESCE(p.deuda_restante, 0) AS deuda_restante,
                COALESCE(p.pago_inicial, 0) AS pago_inicial,
                p.fecha_entrega,
                p.fecha_entrega_programada,
                p.horario_entrega,
                p.id_repartidor,
                c.nombre AS cli_nombre,
                c.ap_paterno AS cli_paterno,
                c.ap_materno AS cli_materno,
                c.telefono AS cli_telefono,
                d.calle,
                d.numero,
                d.colonia,
                d.municipio,
                d.estado AS dir_estado,
                d.latitud,
                d.longitud,
                d.geo_precision,
                (
                    SELECT s.estado 
                    FROM status s 
                    WHERE s.id_pedido2 = p.id_pedido 
                      AND s.estado IN ('pedido','confirmado','en_transito','entregado','cancelado')
                    ORDER BY s.id_status DESC 
                    LIMIT 1
                ) AS estado_logistico
            FROM pedido p
            INNER JOIN cliente c ON c.id_cliente = p.id_cliente1
            LEFT JOIN direccion d ON d.id_cliente = c.id_cliente
            WHERE p.id_repartidor = ? 
               OR p.id_repartidor IS NULL
            ORDER BY p.id_pedido DESC, (d.latitud IS NULL) ASC, d.id_direccion ASC
        ";

        $rows = $db->query($sql, [$idRepartidor])->getResultArray();

        // Evitar duplicados si un cliente tiene más de una fila en dirección
        $pedidosUnicos = [];
        foreach ($rows as $r) {
            $id = (int) $r['id_pedido'];
            if (! isset($pedidosUnicos[$id])) {
                $pedidosUnicos[$id] = $r;
            }
        }

        $ids = array_keys($pedidosUnicos);
        $productosPorPedido = [];

        if (! empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $prods = $db->query(
                "SELECT pp.id_pedido1, pp.cantidad, pp.precio_venta, pp.unidad_venta, pp.total, pr.nombre
                 FROM producto_pedido pp
                 INNER JOIN producto pr ON pr.id_producto = pp.id_producto3
                 WHERE pp.id_pedido1 IN ($placeholders)",
                $ids
            )->getResultArray();

            foreach ($prods as $pr) {
                $productosPorPedido[(int) $pr['id_pedido1']][] = [
                    'nombre'       => $pr['nombre'],
                    'cantidad'     => (float) $pr['cantidad'],
                    'unidad_venta' => $pr['unidad_venta'],
                    'precio_venta' => (float) $pr['precio_venta'],
                    'total'        => (float) $pr['total'],
                ];
            }
        }

        $asignados   = [];
        $disponibles = [];
        $entregados  = [];

        $porRecoger   = 0;
        $enCamino     = 0;
        $montoEnRuta  = 0.0;

        foreach ($pedidosUnicos as $id => $p) {
            $estado = $p['estado_logistico'] ?: 'pedido';
            $esMio  = (int) ($p['id_repartidor'] ?? 0) === $idRepartidor;

            $nombreCliente = trim(($p['cli_nombre'] ?? '') . ' ' . ($p['cli_paterno'] ?? '') . ' ' . ($p['cli_materno'] ?? ''));
            $calleLimpia   = (! empty($p['calle']) && strtolower($p['calle']) !== 'null') ? $p['calle'] : '';
            $coloniaLimpia = (! empty($p['colonia']) && strtolower($p['colonia']) !== 'null') ? $p['colonia'] : '';
            $munLimpio     = (! empty($p['municipio']) && strtolower($p['municipio']) !== 'null') ? $p['municipio'] : '';
            $estLimpio     = (! empty($p['dir_estado']) && ! in_array(strtolower($p['dir_estado']), ['null', 'nnn'], true)) ? $p['dir_estado'] : '';

            $partesDir = [];
            if ($calleLimpia !== '') {
                $partesDir[] = $calleLimpia . (! empty($p['numero']) ? ' #' . $p['numero'] : '');
            }
            if ($coloniaLimpia !== '') {
                $partesDir[] = 'Col. ' . $coloniaLimpia;
            }
            if ($munLimpio !== '') {
                $partesDir[] = $munLimpio;
            }
            if ($estLimpio !== '') {
                $partesDir[] = $estLimpio;
            }

            $direccionCompleta = ! empty($partesDir) ? implode(', ', $partesDir) : 'Dirección pendiente de confirmar en tienda';

            $total         = (float) $p['total'];
            $modalidad     = $p['modalidad_pago'] ?: 'contado';
            $deudaRestante = (float) $p['deuda_restante'];
            $pagoInicial   = (float) $p['pago_inicial'];
            $montoCobrar   = $modalidad === 'credito' ? $deudaRestante : $total;

            $item = [
                'id_pedido'                => $id,
                'fecha'                    => $p['fecha'],
                'estado'                   => $estado,
                'cliente_nombre'           => $nombreCliente ?: 'Cliente Fruver',
                'cliente_telefono'         => preg_replace('/[^0-9]/', '', (string) ($p['cli_telefono'] ?? '')),
                'direccion'                => $direccionCompleta,
                'tiene_mapa'               => ! empty($partesDir),
                'lat'                      => $p['latitud'] !== null ? (float) $p['latitud'] : null,
                'lng'                      => $p['longitud'] !== null ? (float) $p['longitud'] : null,
                'precision'                => $p['geo_precision'] ?? null,
                'total'                    => $total,
                'modalidad_pago'           => $modalidad,
                'deuda_restante'           => $deudaRestante,
                'pago_inicial'             => $pagoInicial,
                'monto_cobrar'             => $montoCobrar,
                'fecha_entrega'            => ($p['fecha_entrega'] && $p['fecha_entrega'] !== '0000-00-00 00:00:00') ? $p['fecha_entrega'] : null,
                'fecha_entrega_programada' => ($p['fecha_entrega_programada'] && $p['fecha_entrega_programada'] !== '0000-00-00') ? $p['fecha_entrega_programada'] : null,
                'horario_entrega'          => ($p['horario_entrega'] && $p['horario_entrega'] !== '00:00:00') ? substr($p['horario_entrega'], 0, 5) : null,
                'productos'                => $productosPorPedido[$id] ?? [],
            ];

            if ($esMio && in_array($estado, ['confirmado', 'en_transito'], true)) {
                $asignados[] = $item;
                if ($estado === 'confirmado') {
                    $porRecoger++;
                } else {
                    $enCamino++;
                }
                $montoEnRuta += $montoCobrar;
            } elseif ($esMio && $estado === 'pedido') {
                // Asignado por el admin antes de confirmarse: visible, sin sumar a ruta ni cobro.
                $asignados[] = $item;
            } elseif (! $esMio && empty($p['id_repartidor']) && in_array($estado, ['confirmado', 'pedido'], true)) {
                $disponibles[] = $item;
            } elseif ($esMio && $estado === 'entregado') {
                $entregados[] = $item;
            }
        }

        $stats = [
            'activos'      => $porRecoger + $enCamino,
            'por_recoger'  => $porRecoger,
            'en_camino'    => $enCamino,
            'disponibles'  => count($disponibles),
            'entregados'   => count($entregados),
            'monto_ruta'   => round($montoEnRuta, 2),
        ];

        $firma = md5(json_encode([
            array_column($asignados, 'estado', 'id_pedido'),
            array_column($disponibles, 'estado', 'id_pedido'),
            count($entregados),
        ]));

        return [
            'asignados'   => $asignados,
            'disponibles' => $disponibles,
            'entregados'  => $entregados,
            'stats'       => $stats,
            'firma'       => $firma,
        ];
    }

    /**
     * Verifica si el repartidor se desvió de su ruta activa.
     * GET /repartidores/verificar-desvio?lat=&lng=&pedidos=12,15
     */
    public function verificarDesvio()
    {
        if (session()->get('auth_role') !== 'repartidor') {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        }

        $lat = filter_var($this->request->getGet('lat'), FILTER_VALIDATE_FLOAT);
        $lng = filter_var($this->request->getGet('lng'), FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Coordenadas no válidas.']);
        }

        $posicion = ['lat' => (float) $lat, 'lng' => (float) $lng];
        $ids = array_values(array_filter(
            array_map('intval', explode(',', (string) $this->request->getGet('pedidos'))),
            static fn ($i) => $i > 0
        ));
        $ids = array_values(array_unique($ids));

        if (! $ids) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Sin paradas.']);
        }

        $origenLat = filter_var($this->request->getGet('origen_lat'), FILTER_VALIDATE_FLOAT);
        $origenLng = filter_var($this->request->getGet('origen_lng'), FILTER_VALIDATE_FLOAT);
        if ($origenLat === false || $origenLng === false || abs($origenLat) > 90 || abs($origenLng) > 180) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Origen de ruta no válido.']);
        }
        $indiceParada = filter_var($this->request->getGet('progreso'), FILTER_VALIDATE_INT);
        if ($indiceParada === false || $indiceParada < 0 || $indiceParada >= count($ids)) {
            return $this->response->setJSON(['ok' => false, 'mensaje' => 'Progreso de ruta no válido.']);
        }

        $idRepartidor = (int) session()->get('repartidor_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $filas = db_connect()->query("
            SELECT p.id_pedido, d.latitud, d.longitud,
                   (
                       SELECT s.estado FROM status s
                       WHERE s.id_pedido2 = p.id_pedido
                         AND s.estado IN ('pedido','confirmado','en_transito','entregado','cancelado')
                       ORDER BY s.id_status DESC LIMIT 1
                   ) AS estado_logistico
            FROM pedido p
            INNER JOIN cliente c ON c.id_cliente = p.id_cliente1
            LEFT JOIN direccion d ON d.id_cliente = c.id_cliente
            WHERE p.id_repartidor = ?
              AND p.id_pedido IN ($placeholders)
            ORDER BY p.id_pedido ASC, (d.latitud IS NULL) ASC, d.id_direccion ASC
        ", array_merge([$idRepartidor], $ids))->getResultArray();

        $porPedido = [];
        foreach ($filas as $fila) {
            $id = (int) $fila['id_pedido'];
            if (! isset($porPedido[$id]) && in_array($fila['estado_logistico'], ['confirmado', 'en_transito'], true)
                && $fila['latitud'] !== null && $fila['longitud'] !== null) {
                $porPedido[$id] = [
                    'id_pedido' => $id,
                    'lat'       => (float) $fila['latitud'],
                    'lng'       => (float) $fila['longitud'],
                ];
            }
        }

        $paradas = [];
        foreach ($ids as $id) {
            if (! isset($porPedido[$id])) {
                return $this->response->setStatusCode(403)->setJSON([
                    'ok'      => false,
                    'mensaje' => 'La ruta contiene pedidos no asignados a tu cuenta o sin ubicación.',
                ]);
            }
            $paradas[] = $porPedido[$id];
        }

        $origen = ['lat' => (float) $origenLat, 'lng' => (float) $origenLng];

        return $this->response->setJSON([
            'ok' => true,
        ] + RutaDijkstra::verificarDesvio($posicion, $origen, $paradas, 0.2, $indiceParada));
    }

    public function guardar()
    {
        $input = [
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'ap_paterno' => trim((string) $this->request->getPost('ap_paterno')),
            'ap_materno' => trim((string) $this->request->getPost('ap_materno')),
            'telefono'   => trim((string) $this->request->getPost('telefono')),
            'direccion'  => trim((string) $this->request->getPost('direccion')),
            'notas'      => trim((string) $this->request->getPost('notas')),
            'email'      => strtolower(trim((string) $this->request->getPost('email'))),
        ];
        $rules = [
            'nombre'     => 'required|max_length[100]',
            'ap_paterno' => 'required|max_length[100]',
            'ap_materno' => 'permit_empty|max_length[100]',
            'telefono'   => 'required|max_length[12]',
            'direccion'  => 'permit_empty|max_length[500]',
            'notas'      => 'permit_empty|max_length[500]',
            'email'      => 'required|valid_email|max_length[50]|is_unique[repartidor.email]',
        ];

        if (! $this->validate($rules, $input)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $emailConfigurationError = AccountCredentials::configurationError();
        if ($emailConfigurationError !== null) {
            return redirect()->back()->withInput()->with('error', $emailConfigurationError);
        }

        $password = AccountCredentials::generatePassword();
        $model = new RepartidorModel();
        $db    = db_connect();
        $db->transBegin();

        $repartidorId = $model->insert([
            ...$input,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if ($repartidorId === false) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'No se pudo guardar el repartidor.');
        }

        $nombre = trim($input['nombre'] . ' ' . $input['ap_paterno'] . ' ' . $input['ap_materno']);
        if (! $db->transStatus() || ! AccountCredentials::sendPassword($input['email'], $nombre, 'repartidor', $password)) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'No se pudo enviar el correo de acceso. Verifica los datos del servidor SMTP y vuelve a intentarlo.');
        }

        $db->transCommit();
        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'No se pudo completar el registro del repartidor.');
        }

        return redirect()->to(base_url('repartidores'))->with('credential', [
            'nombre'    => $nombre,
            'email'     => $input['email'],
            'password'  => $password,
            'tipo'      => 'Repartidor',
        ]);
    }

    public function actualizar()
    {
        $model = new RepartidorModel();
        $id    = (int) $this->request->getPost('id_repartidor');
        $email = strtolower(trim((string) $this->request->getPost('email')));

        if (! $this->validate([
            'email' => "required|valid_email|max_length[50]|is_unique[repartidor.email,id_repartidor,{$id}]",
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $model->update($id, [
            'nombre'     => $this->request->getPost('nombre'),
            'ap_paterno' => $this->request->getPost('ap_paterno'),
            'ap_materno' => $this->request->getPost('ap_materno'),
            'telefono'   => $this->request->getPost('telefono'),
            'direccion'  => $this->request->getPost('direccion'),
            'notas'      => $this->request->getPost('notas'),
            'email'      => $email,
        ]);

        return redirect()->to(base_url('repartidores'));
    }

    public function verificarEliminar($id = null)
    {
        if (!$id) {
            return $this->response->setJSON(['puede' => false, 'mensaje' => 'ID no válido']);
        }

        $model = new RepartidorModel();

        if ($model->tienePedidos((int) $id)) {
            return $this->response->setJSON([
                'puede'   => false,
                'mensaje' => 'Este repartidor tiene pedidos asignados y no puede ser eliminado. Si deseas eliminarlo, primero reasigna o elimina todos sus pedidos.',
            ]);
        }

        return $this->response->setJSON(['puede' => true]);
    }

    public function eliminar($id = null)
    {
        if ($id) {
            $model = new RepartidorModel();
            $model->delete($id);
        }
        return redirect()->to(base_url('repartidores'));
    }
}