<?php

namespace App\Libraries;

/**
 * Ruteo por calles reales con OSRM (datos de OpenStreetMap): gratis y sin API key.
 *
 * - matrices(): distancias y tiempos por calle entre todos los puntos (servicio "table").
 * - ruta():     trazo sobre las calles, km y minutos (servicio "route").
 *
 * Por defecto usa el servidor público https://router.project-osrm.org (uso ligero).
 * Para usar uno propio:  osrm.baseUrl = http://localhost:5000  en .env
 * La verificación SSL respeta geocoder.verifySsl / geocoder.caBundle (igual que Geocoder).
 *
 * Nunca lanza excepciones: si falla devuelve null y el llamador usa línea recta.
 */
class Osrm
{
    private const BASE_DEFECTO = 'https://router.project-osrm.org';

    /**
     * Distancias (km) y tiempos (min) por calle entre todos los puntos (servicio "table").
     *
     * @param list<array{lat: float, lng: float}> $puntos
     * @return array{km: array<int, array<int, float>>, min: array<int, array<int, float>>}|null
     */
    public static function matrices(array $puntos): ?array
    {
        $json = self::consultar('table', $puntos, ['annotations' => 'duration,distance']);
        if ($json === null || ($json['code'] ?? '') !== 'Ok' || empty($json['distances']) || empty($json['durations'])) {
            return null;
        }

        $km = [];
        $mn = [];
        foreach ($json['distances'] as $i => $fila) {
            foreach ($fila as $j => $metros) {
                $seg = $json['durations'][$i][$j] ?? null;
                if ($metros === null || $seg === null) {
                    return null; // OSRM no encontró camino entre dos puntos
                }
                $km[(int) $i][(int) $j] = (float) $metros / 1000;
                $mn[(int) $i][(int) $j] = (float) $seg / 60;
            }
        }

        return (count($km) === count($puntos) && count($mn) === count($puntos)) ? ['km' => $km, 'min' => $mn] : null;
    }

    /**
     * @param list<array{lat: float, lng: float}> $puntos en orden de visita (el primero es la salida)
     * @return array{km: float, min: int, trazo: list<array{0: float, 1: float}>, tramos: list<array{km: float, min: int}>}|null
     */
    public static function ruta(array $puntos): ?array
    {
        $json = self::consultar('route', $puntos, [
            'overview'   => 'full',
            'geometries' => 'geojson',
            'steps'      => 'false',
        ]);
        $r = $json['routes'][0] ?? null;
        if ($json === null || ($json['code'] ?? '') !== 'Ok' || $r === null) {
            return null;
        }

        $trazo = [];
        foreach ($r['geometry']['coordinates'] ?? [] as $c) {
            $trazo[] = [(float) $c[1], (float) $c[0]]; // GeoJSON es [lng, lat]; Leaflet usa [lat, lng]
        }

        $tramos = [];
        foreach ($r['legs'] ?? [] as $leg) {
            $tramos[] = [
                'km'  => round((float) $leg['distance'] / 1000, 2),
                'min' => (int) round((float) $leg['duration'] / 60),
            ];
        }

        return [
            'km'     => round((float) $r['distance'] / 1000, 2),
            'min'    => (int) round((float) $r['duration'] / 60),
            'trazo'  => $trazo,
            'tramos' => $tramos,
        ];
    }

    private static function consultar(string $servicio, array $puntos, array $query): ?array
    {
        if (count($puntos) < 2) {
            return null;
        }

        $coords = implode(';', array_map(
            static fn (array $p): string => sprintf('%.6F,%.6F', $p['lng'], $p['lat']),
            $puntos
        ));
        $base = rtrim(trim((string) env('osrm.baseUrl', '')) ?: self::BASE_DEFECTO, '/');

        try {
            $cliente = \Config\Services::curlrequest([
                'timeout'         => 8,
                'connect_timeout' => 4,
                'http_errors'     => false,
                'verify'          => Geocoder::verificarSsl(),
            ]);
            $resp = $cliente->request('GET', $base . '/' . $servicio . '/v1/driving/' . $coords, [
                'query'   => $query,
                'headers' => ['User-Agent' => 'Fruver/1.0 (' . env('email.fromEmail', 'fruver@example.com') . ')'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'OSRM ' . $servicio . ': ' . $e->getMessage());

            return null;
        }

        if ($resp->getStatusCode() !== 200) {
            log_message('error', 'OSRM ' . $servicio . ' HTTP ' . $resp->getStatusCode() . ' ' . substr((string) $resp->getBody(), 0, 200));

            return null;
        }

        $json = json_decode((string) $resp->getBody(), true);

        return is_array($json) ? $json : null;
    }
}
