<?php

use App\Libraries\RutaDijkstra;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class RutaDijkstraTest extends CIUnitTestCase
{
    public function testIdentificaSiElRepartidorPermaneceEnLaRutaYRespetaElOrdenDeParadas(): void
    {
        $origen = ['lat' => 0.0, 'lng' => 0.0];
        $paradas = [
            ['id_pedido' => 7, 'lat' => 0.0, 'lng' => 0.01],
            ['id_pedido' => 8, 'lat' => 0.01, 'lng' => 0.01],
        ];

        $resultado = RutaDijkstra::verificarDesvio(
            ['lat' => 0.0001, 'lng' => 0.005],
            $origen,
            $paradas
        );

        $this->assertTrue($resultado['en_ruta']);
        $this->assertSame(7, $resultado['siguiente_parada']['id_pedido']);
        $this->assertLessThan(0.2, $resultado['desvio_km']);
    }

    public function testDetectaCuandoLaPosicionSeAlejaDeLaRuta(): void
    {
        $resultado = RutaDijkstra::verificarDesvio(
            ['lat' => 0.01, 'lng' => 0.0],
            ['lat' => 0.0, 'lng' => 0.0],
            [['id_pedido' => 7, 'lat' => 0.0, 'lng' => 0.01]]
        );

        $this->assertFalse($resultado['en_ruta']);
        $this->assertGreaterThan(0.2, $resultado['desvio_km']);
    }

    public function testNoConfundeUnTramoFuturoConElTramoActual(): void
    {
        $origen = ['lat' => 0.0, 'lng' => 0.0];
        $paradas = [
            ['id_pedido' => 7, 'lat' => 0.0, 'lng' => 0.01],
            ['id_pedido' => 8, 'lat' => 0.01, 'lng' => 0.01],
        ];
        $posicionEnTramoFuturo = ['lat' => 0.01, 'lng' => 0.01];

        $antesDeLaPrimera = RutaDijkstra::verificarDesvio($posicionEnTramoFuturo, $origen, $paradas);
        $cercaDeLaSegunda = RutaDijkstra::verificarDesvio(
            $posicionEnTramoFuturo,
            $origen,
            $paradas,
            0.2,
            1
        );

        $this->assertFalse($antesDeLaPrimera['en_ruta']);
        $this->assertTrue($cercaDeLaSegunda['en_ruta']);
        $this->assertSame(8, $cercaDeLaSegunda['siguiente_parada']['id_pedido']);
    }
}
