<?php

namespace App\Commands;

use App\Libraries\Geocoder;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Obtiene latitud/longitud de las direcciones que aún no las tienen.
 *
 *   php spark direcciones:geocodificar           (solo las que nunca se intentaron o fallaron por error técnico)
 *   php spark direcciones:geocodificar --todas   (reintenta también las que no se encontraron antes)
 */
class GeocodificarDirecciones extends BaseCommand
{
    protected $group       = 'Fruver';
    protected $name        = 'direcciones:geocodificar';
    protected $description = 'Guarda las coordenadas de las direcciones de clientes que no las tienen.';
    protected $usage       = 'direcciones:geocodificar [--todas]';
    protected $options     = ['--todas' => 'Reintenta también las direcciones que ya se intentaron sin éxito.'];

    public function run(array $params)
    {
        $db = db_connect();

        $consulta = $db->table('direccion')->where('latitud', null);
        if (CLI::getOption('todas') === null) {
            $consulta->where('geo_intento_at', null);
        }
        $pendientes = $consulta->get()->getResultArray();

        if (! $pendientes) {
            CLI::write('No hay direcciones pendientes.', 'green');

            return;
        }

        $bien = 0;
        foreach ($pendientes as $i => $d) {
            if ($i > 0) {
                usleep(1100000); // Nominatim: máx. 1 petición por segundo
            }

            $campos = Geocoder::camposParaGuardar($d);
            $db->table('direccion')->where('id_direccion', $d['id_direccion'])->update($campos);

            if ($campos['latitud'] !== null) {
                $bien++;
                CLI::write("#{$d['id_direccion']} {$d['calle']}, {$d['municipio']}: {$campos['latitud']}, {$campos['longitud']} ({$campos['geo_precision']})", 'green');
            } elseif (Geocoder::huboError()) {
                CLI::error("#{$d['id_direccion']}: falló la conexión con el servicio de mapas. Revisa writable/logs (¿certificado SSL?).");
                break;
            } else {
                CLI::write("#{$d['id_direccion']} {$d['calle']}, {$d['municipio']}: no se encontró.", 'yellow');
            }
        }

        CLI::write("Listo: {$bien} de " . count($pendientes) . ' direcciones con coordenadas.');
    }
}
