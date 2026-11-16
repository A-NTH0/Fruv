<?php

namespace App\Libraries;

/**
 * Convierte la dirección del cliente en coordenadas.
 *
 * - Sin configuración usa Nominatim (OpenStreetMap): gratis y sin API key.
 * - Si en .env existe  google.mapsApiKey  usa la Geocoding API de Google.
 *
 * Nunca lanza excepciones: si algo falla devuelve null y el registro del
 * cliente continúa normalmente (las coordenadas se reintentan después).
 */
class Geocoder
{
    private const PLACEHOLDERS = ['', 'null', 'nnn'];

    /** @return array{lat: float, lng: float, precision: string}|null */
    public static function geocodificar(array $dir): ?array
    {
        $calle     = self::limpio($dir['calle'] ?? '');
        $numero    = (int) ($dir['numero'] ?? 0) > 0 ? (string) (int) $dir['numero'] : '';
        $colonia   = self::limpio($dir['colonia'] ?? '');
        $municipio = self::limpio($dir['municipio'] ?? '');
        $estado    = self::limpio($dir['estado'] ?? '');

        // De lo más exacto a lo más general; se detiene en la primera que responda.
        $intentos = [];
        if ($calle !== '' && $municipio !== '') {
            $intentos['calle'] = self::unir([trim($calle . ' ' . $numero), $colonia, $municipio, $estado, 'México']);
        }
        if ($colonia !== '' && $municipio !== '') {
            $intentos['colonia'] = self::unir([$colonia, $municipio, $estado, 'México']);
        }
        if ($municipio !== '') {
            $intentos['municipio'] = self::unir([$municipio, $estado, 'México']);
        }

        $primero = true;
        foreach ($intentos as $precision => $texto) {
            if (! $primero) {
                usleep(1100000); // Nominatim pide máx. 1 petición por segundo
            }
            $primero = false;

            try {
                $punto = self::buscar($texto);
            } catch (\Throwable $e) {
                log_message('error', 'Geocoder: ' . $e->getMessage());
                return null;
            }

            if ($punto !== null) {
                return $punto + ['precision' => $precision];
            }
        }

        return null;
    }

    /** @return array{lat: float, lng: float}|null */
    private static function buscar(string $texto): ?array
    {
        $cliente = \Config\Services::curlrequest([
            'timeout'         => 5,
            'connect_timeout' => 3,
            'http_errors'     => false,
            // En WAMP/Windows PHP suele no tener el paquete de certificados (cURL error 60).
            // Solo en desarrollo se puede desactivar con  geocoder.verifySsl = false  en .env
            'verify'          => filter_var(env('geocoder.verifySsl', true), FILTER_VALIDATE_BOOLEAN),
        ]);

        $apiKey = trim((string) env('google.mapsApiKey', ''));

        if ($apiKey !== '') {
            $resp = $cliente->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => ['address' => $texto, 'region' => 'mx', 'language' => 'es', 'key' => $apiKey],
            ]);
            if ($resp->getStatusCode() !== 200) {
                return null;
            }
            $json = json_decode((string) $resp->getBody(), true);
            $loc  = $json['results'][0]['geometry']['location'] ?? null;

            return $loc ? ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng']] : null;
        }

        $resp = $cliente->request('GET', 'https://nominatim.openstreetmap.org/search', [
            'query'   => ['q' => $texto, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'mx'],
            'headers' => [
                // Nominatim exige identificar la aplicación
                'User-Agent'      => 'Fruver/1.0 (' . env('email.fromEmail', 'fruver@example.com') . ')',
                'Accept-Language' => 'es',
            ],
        ]);
        if ($resp->getStatusCode() !== 200) {
            return null;
        }
        $json = json_decode((string) $resp->getBody(), true);

        return isset($json[0]['lat'], $json[0]['lon'])
            ? ['lat' => (float) $json[0]['lat'], 'lng' => (float) $json[0]['lon']]
            : null;
    }

    private static function limpio($valor): string
    {
        $v = trim((string) $valor);

        return in_array(strtolower($v), self::PLACEHOLDERS, true) ? '' : $v;
    }

    private static function unir(array $partes): string
    {
        return implode(', ', array_filter($partes, static fn ($p) => $p !== ''));
    }
}
