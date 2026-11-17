<?php

namespace App\Libraries;

use SplPriorityQueue;

/**
 * Planificación de ruta del repartidor con Dijkstra.
 *
 * 1. Se decide si conviene trazar ruta:
 *      - ZONA:    3 o más pedidos a <= RADIO_ZONA_KM entre sí (encadenados), o
 *      - DE PASO: un pedido queda "en el camino" hacia otro (desvío pequeño).
 * 2. Con las paradas elegidas se arma un grafo (nodo 0 = repartidor/tienda).
 *    Aristas: árbol de expansión mínima (conexo) + los K vecinos más cercanos.
 *    Peso = distancia Haversine * FACTOR_VIAL (aprox. de calles).
 * 3. Dijkstra desde cada nodo da la distancia más corta entre todos los pares.
 * 4. Con esa matriz se ordena la visita: exacto (permutaciones) si hay
 *    <= MAX_EXACTO paradas; vecino más cercano si hay más.
 *
 * Para usar distancias reales por calle basta cambiar el cálculo de $peso
 * en construirGrafo() (p. ej. con la Distance Matrix API de Google).
 */
class RutaDijkstra
{
    public const RADIO_ZONA_KM    = 2.0;
    public const MIN_PEDIDOS_ZONA = 3;
    public const DESVIO_MAX_KM    = 1.0;
    public const DESVIO_MAX_PCT   = 0.15;
    public const FACTOR_VIAL      = 1.3;
    public const VECINOS_K        = 3;
    public const MAX_EXACTO       = 8;
    public const MAX_PARADAS_GOOGLE = 10; // destino + 9 waypoints
    public const CERCA_PARADA_KM  = 1.5;   // un pedido a <= esto de tu ubicación o de una parada se ofrece

    /** Distancia en km entre dos puntos ['lat'=>, 'lng'=>]. */
    public static function haversine(array $a, array $b): float
    {
        $r    = 6371.0088;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h    = sin($dLat / 2) ** 2
              + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($h)));
    }

    /**
     * @param array $origen  ['lat'=>float,'lng'=>float]
     * @param array $pedidos cada uno con id_pedido, lat, lng (+ campos extra que se conservan)
     */
    public static function planificar(array $origen, array $pedidos): array
    {
        $conCoord = [];
        $sinCoord = [];
        foreach ($pedidos as $p) {
            if (isset($p['lat'], $p['lng']) && $p['lat'] !== null && $p['lng'] !== null) {
                $conCoord[] = $p;
            } else {
                $sinCoord[] = (int) $p['id_pedido'];
            }
        }

        $vacio = [
            'hay_ruta'        => false,
            'motivo'          => '',
            'origen'          => $origen,
            'paradas'         => [],
            'distancia_km'    => 0.0,
            'metodo'          => null,
            'sin_coordenadas' => $sinCoord,
            'google_url'      => null,
            'truncada'        => false,
        ];

        if (count($conCoord) < 2) {
            return $vacio;
        }

        // ── 1. ¿Conviene trazar ruta? ─────────────────────────────
        $motivos = []; // indice => 'zona' | 'de_paso'

        foreach (self::zonas($conCoord) as $grupo) {
            if (count($grupo) >= self::MIN_PEDIDOS_ZONA) {
                foreach ($grupo as $i) {
                    $motivos[$i] = 'zona';
                }
            }
        }

        $n = count($conCoord);
        for ($t = 0; $t < $n; $t++) {
            $dOT = self::haversine($origen, $conCoord[$t]);
            for ($p = 0; $p < $n; $p++) {
                if ($p === $t) {
                    continue;
                }
                $dOP = self::haversine($origen, $conCoord[$p]);
                if ($dOP >= $dOT) {
                    continue; // el pedido "de paso" debe quedar antes que el destino
                }
                $desvio = $dOP + self::haversine($conCoord[$p], $conCoord[$t]) - $dOT;
                $limite = max(self::DESVIO_MAX_KM, self::DESVIO_MAX_PCT * $dOT);
                if ($desvio <= $limite) {
                    $motivos[$p] ??= 'de_paso';
                    $motivos[$t] ??= 'de_paso';
                }
            }
        }

        if (count($motivos) < 2) {
            $vacio['motivo'] = 'Tus pedidos están en zonas distintas y ninguno queda de paso; no hay ruta combinada.';

            return $vacio;
        }

        $hayZona = in_array('zona', $motivos, true);
        $texto   = $hayZona
            ? 'Tienes ' . count(array_filter($motivos, static fn ($m) => $m === 'zona')) . ' pedidos en la misma zona.'
            : 'Uno de tus pedidos te queda de paso hacia otro.';

        return self::construir($origen, $conCoord, $motivos, $sinCoord, $texto);
    }

    /**
     * Ruta (Dijkstra) para pedidos que el repartidor eligió; sin condiciones de zona.
     */
    public static function planificarSeleccion(array $origen, array $pedidos): array
    {
        $conCoord = [];
        $sinCoord = [];
        foreach ($pedidos as $p) {
            if (isset($p['lat'], $p['lng'])) {
                $conCoord[] = $p;
            } else {
                $sinCoord[] = (int) $p['id_pedido'];
            }
        }

        if (! $conCoord) {
            return [
                'hay_ruta' => false, 'motivo' => 'No se pudo ubicar la dirección en el mapa.',
                'origen' => $origen, 'paradas' => [], 'distancia_km' => 0.0, 'metodo' => null,
                'sin_coordenadas' => $sinCoord, 'google_url' => null, 'truncada' => false,
            ];
        }

        $motivos = array_fill_keys(array_keys($conCoord), 'seleccion');
        $texto   = 'Recorrido de ' . count($conCoord) . (count($conCoord) === 1 ? ' pedido.' : ' pedidos.');

        return self::construir($origen, $conCoord, $motivos, $sinCoord, $texto);
    }

    /**
     * Pedidos que quedan cerca de la ruta ya trazada (candidatos a agregar).
     * "Cerca" = desvío pequeño en algún tramo, o a <= 1 km de alguna parada.
     */
    public static function cercanos(array $origen, array $paradas, array $candidatos): array
    {
        $puntos = [['lat' => $origen['lat'], 'lng' => $origen['lng']]];
        foreach ($paradas as $p) {
            $puntos[] = ['lat' => $p['lat'], 'lng' => $p['lng']];
        }

        $res = [];
        foreach ($candidatos as $c) {
            if (! isset($c['lat'], $c['lng'])) {
                continue;
            }
            $desvio   = INF;
            $enCamino = false;
            for ($i = 0, $m = count($puntos) - 1; $i < $m; $i++) {
                $ab     = self::haversine($puntos[$i], $puntos[$i + 1]);
                $desv   = self::haversine($puntos[$i], $c) + self::haversine($c, $puntos[$i + 1]) - $ab;
                $limite = max(self::DESVIO_MAX_KM, self::DESVIO_MAX_PCT * $ab);
                $desvio = min($desvio, $desv);
                if ($desv <= $limite) {
                    $enCamino = true;
                }
            }
            $distParada = INF;
            for ($k = 0, $m = count($puntos); $k < $m; $k++) {   // k = 0 → también cerca de tu ubicación
                $distParada = min($distParada, self::haversine($puntos[$k], $c));
            }

            if ($enCamino || $distParada <= self::CERCA_PARADA_KM) {
                $res[] = $c + [
                    'desvio_km'      => round($desvio, 2),
                    'dist_parada_km' => round($distParada, 2),
                ];
            }
        }
        usort($res, static fn ($a, $b) => $a['desvio_km'] <=> $b['desvio_km']);

        return $res;
    }

    /** Grafo + Dijkstra + orden de visita para las paradas elegidas. */
    private static function construir(array $origen, array $conCoord, array $motivos, array $sinCoord, string $textoMotivo): array
    {
        $indices = array_keys($motivos);
        sort($indices);

        $nodos = [['lat' => $origen['lat'], 'lng' => $origen['lng']]];
        foreach ($indices as $i) {
            $nodos[] = ['lat' => $conCoord[$i]['lat'], 'lng' => $conCoord[$i]['lng']];
        }

        $grafo = self::construirGrafo($nodos);
        $D     = [];
        for ($i = 0, $m = count($nodos); $i < $m; $i++) {
            $D[$i] = self::dijkstra($grafo, $i)['dist'];
        }

        [$orden, $total, $metodo] = self::ordenarVisita($D, count($nodos) - 1);

        $paradas = [];
        $previo  = 0;
        $acum    = 0.0;
        foreach ($orden as $pos => $nodo) {
            $pedido = $conCoord[$indices[$nodo - 1]];
            $tramo  = $D[$previo][$nodo];
            $acum  += $tramo;
            $paradas[] = $pedido + [
                'orden'    => $pos + 1,
                'motivo'   => $motivos[$indices[$nodo - 1]],
                'km_tramo' => round($tramo, 2),
                'km_acum'  => round($acum, 2),
            ];
            $previo = $nodo;
        }

        return [
            'hay_ruta'        => true,
            'motivo'          => $textoMotivo,
            'origen'          => $origen,
            'paradas'         => $paradas,
            'distancia_km'    => round($total, 2),
            'metodo'          => $metodo,
            'sin_coordenadas' => $sinCoord,
            'google_url'      => self::urlGoogleMaps($origen, $paradas),
            'truncada'        => count($paradas) > self::MAX_PARADAS_GOOGLE,
        ];
    }

    // ───────────────────────── Zonas ─────────────────────────

    /** Agrupa pedidos por cercanía encadenada (<= RADIO_ZONA_KM). @return int[][] */
    private static function zonas(array $pedidos): array
    {
        $n       = count($pedidos);
        $visto   = array_fill(0, $n, false);
        $grupos  = [];

        for ($i = 0; $i < $n; $i++) {
            if ($visto[$i]) {
                continue;
            }
            $grupo = [];
            $cola  = [$i];
            $visto[$i] = true;
            while ($cola) {
                $a       = array_shift($cola);
                $grupo[] = $a;
                for ($j = 0; $j < $n; $j++) {
                    if (! $visto[$j] && self::haversine($pedidos[$a], $pedidos[$j]) <= self::RADIO_ZONA_KM) {
                        $visto[$j] = true;
                        $cola[]    = $j;
                    }
                }
            }
            $grupos[] = $grupo;
        }

        return $grupos;
    }

    // ───────────────────────── Grafo y Dijkstra ─────────────────────────

    /** @return array<int, array<int, float>> lista de adyacencia [i][j] = peso */
    private static function construirGrafo(array $nodos): array
    {
        $n     = count($nodos);
        $peso  = static fn (int $i, int $j): float => self::haversine($nodos[$i], $nodos[$j]) * self::FACTOR_VIAL;
        $grafo = array_fill(0, $n, []);

        $conectar = static function (int $i, int $j) use (&$grafo, $peso): void {
            $w = $peso($i, $j);
            $grafo[$i][$j] = $w;
            $grafo[$j][$i] = $w;
        };

        // Árbol de expansión mínima (Prim): garantiza que el grafo sea conexo.
        $enArbol = array_fill(0, $n, false);
        $mejor   = array_fill(0, $n, INF);
        $desde   = array_fill(0, $n, 0);
        $mejor[0] = 0.0;
        for ($k = 0; $k < $n; $k++) {
            $u = -1;
            for ($v = 0; $v < $n; $v++) {
                if (! $enArbol[$v] && ($u === -1 || $mejor[$v] < $mejor[$u])) {
                    $u = $v;
                }
            }
            $enArbol[$u] = true;
            if ($k > 0) {
                $conectar($desde[$u], $u);
            }
            for ($v = 0; $v < $n; $v++) {
                if (! $enArbol[$v] && $peso($u, $v) < $mejor[$v]) {
                    $mejor[$v] = $peso($u, $v);
                    $desde[$v] = $u;
                }
            }
        }

        // K vecinos más cercanos de cada nodo.
        for ($i = 0; $i < $n; $i++) {
            $cercanos = [];
            for ($j = 0; $j < $n; $j++) {
                if ($j !== $i) {
                    $cercanos[$j] = $peso($i, $j);
                }
            }
            asort($cercanos);
            foreach (array_slice(array_keys($cercanos), 0, self::VECINOS_K) as $j) {
                $conectar($i, $j);
            }
        }

        return $grafo;
    }

    /** Dijkstra clásico con cola de prioridad. @return array{dist: float[], prev: int[]} */
    public static function dijkstra(array $grafo, int $origen): array
    {
        $n    = count($grafo);
        $dist = array_fill(0, $n, INF);
        $prev = array_fill(0, $n, -1);
        $dist[$origen] = 0.0;

        $cola = new SplPriorityQueue();
        $cola->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $cola->insert($origen, 0.0);

        while (! $cola->isEmpty()) {
            $item = $cola->extract();
            $u    = $item['data'];
            $d    = -$item['priority'];
            if ($d > $dist[$u]) {
                continue; // entrada vieja
            }
            foreach ($grafo[$u] as $v => $w) {
                if ($dist[$u] + $w < $dist[$v]) {
                    $dist[$v] = $dist[$u] + $w;
                    $prev[$v] = $u;
                    $cola->insert($v, -$dist[$v]);
                }
            }
        }

        return ['dist' => $dist, 'prev' => $prev];
    }

    // ───────────────────────── Orden de visita ─────────────────────────

    /**
     * @param float[][] $D matriz de distancias mínimas (nodo 0 = origen)
     * @return array{0: int[], 1: float, 2: string} [orden de nodos, km totales, método]
     */
    private static function ordenarVisita(array $D, int $paradas): array
    {
        // Vecino más cercano: en cada paso se va a la parada pendiente más próxima.
        $pendientes = range(1, $paradas);
        $orden      = [];
        $actual     = 0;
        $total      = 0.0;
        while ($pendientes) {
            $mejorK = array_key_first($pendientes);
            foreach ($pendientes as $k => $j) {
                if ($D[$actual][$j] < $D[$actual][$pendientes[$mejorK]]) {
                    $mejorK = $k;
                }
            }
            $j        = $pendientes[$mejorK];
            $total   += $D[$actual][$j];
            $orden[]  = $j;
            $actual   = $j;
            unset($pendientes[$mejorK]);
        }

        if ($paradas > self::MAX_EXACTO) {
            return [$orden, $total, 'vecino_mas_cercano'];
        }

        // Pocas paradas: se prueba todo con poda (la ruta más corta real).
        $mejor      = $total + 1e-9;
        $mejorOrden = $orden;
        $buscar = function (int $actual, array $restantes, float $acum, array $camino) use (&$buscar, &$mejor, &$mejorOrden, $D): void {
            if ($acum >= $mejor) {
                return;
            }
            if (! $restantes) {
                $mejor      = $acum;
                $mejorOrden = $camino;

                return;
            }
            foreach ($restantes as $k => $j) {
                $resto = $restantes;
                unset($resto[$k]);
                $buscar($j, $resto, $acum + $D[$actual][$j], array_merge($camino, [$j]));
            }
        };
        $buscar(0, range(1, $paradas), 0.0, []);

        return [$mejorOrden, min($mejor, $total), 'exacto'];
    }

    // ───────────────────────── Google Maps ─────────────────────────

    /** Enlace que abre la ruta ya ordenada en Google Maps (no requiere API key). */
    private static function urlGoogleMaps(array $origen, array $paradas): string
    {
        $paradas = array_slice($paradas, 0, self::MAX_PARADAS_GOOGLE);
        $punto   = static fn (array $p): string => $p['lat'] . ',' . $p['lng'];
        $destino = array_pop($paradas);

        $query = [
            'api'         => 1,
            'origin'      => $origen['lat'] . ',' . $origen['lng'],
            'destination' => $punto($destino),
            'travelmode'  => 'driving',
        ];
        if ($paradas) {
            $query['waypoints'] = implode('|', array_map($punto, $paradas));
        }

        return 'https://www.google.com/maps/dir/?' . http_build_query($query);
    }
}
