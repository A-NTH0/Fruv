<?php

use App\Libraries\ProductoCodigo;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ProductoCodigoTest extends CIUnitTestCase
{
    public function testGeneraTresConsonantesParaNombresSinColision(): void
    {
        $this->assertSame('MND', ProductoCodigo::generarBase('Mandarina'));
        $this->assertSame('MND-020', ProductoCodigo::generarCodigo('Mandarina', 20));
    }

    public function testAgregaOtraConsonanteCuandoLaBaseYaExiste(): void
    {
        $this->assertSame('MNDC', ProductoCodigo::generarBase('Mandioca', ['MND']));
        $this->assertSame('MNDC-021', ProductoCodigo::generarCodigo('Mandioca', 21, ['MND']));
    }

    public function testUsaLasPrimerasLetrasComoSilabaCuandoFaltanConsonantes(): void
    {
        $this->assertSame('PER', ProductoCodigo::generarBase('Pera'));
        $this->assertSame('PUR', ProductoCodigo::generarBase('Puré'));
    }

    public function testExtiendeUnaBaseCortaEnCasoDeColision(): void
    {
        $this->assertSame('PERA', ProductoCodigo::generarBase('Pera', ['PER']));
    }
}
