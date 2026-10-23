<?php

use App\Libraries\MfaTotp;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class MfaTotpTest extends CIUnitTestCase
{
    public function testMatchesRfc6238SixDigitSha1Vector(): void
    {
        $totp = new MfaTotp();

        $this->assertSame('287082', $totp->currentCode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
        $this->assertTrue($totp->verifyCode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59));
        $this->assertFalse($totp->verifyCode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '000000', 59));
    }

    public function testEncryptedSecretCanBeRecovered(): void
    {
        $previous = $_ENV['MFA_ENCRYPTION_KEY'] ?? null;
        $previousServer = $_SERVER['MFA_ENCRYPTION_KEY'] ?? null;
        $testKey = base64_encode(random_bytes(32));
        $_ENV['MFA_ENCRYPTION_KEY'] = $testKey;
        $_SERVER['MFA_ENCRYPTION_KEY'] = $testKey;
        $totp = new MfaTotp();
        $secret = $totp->generateSecret();

        try {
            $this->assertSame($secret, $totp->decryptSecret($totp->encryptSecret($secret)));
        } finally {
            if ($previous === null) {
                unset($_ENV['MFA_ENCRYPTION_KEY']);
            } else {
                $_ENV['MFA_ENCRYPTION_KEY'] = $previous;
            }
            if ($previousServer === null) {
                unset($_SERVER['MFA_ENCRYPTION_KEY']);
            } else {
                $_SERVER['MFA_ENCRYPTION_KEY'] = $previousServer;
            }
        }
    }

    public function testApplicationEncryptionKeyIsConfigured(): void
    {
        $key = base64_decode((string) env('MFA_ENCRYPTION_KEY'), true);

        $this->assertIsString($key);
        $this->assertSame(32, strlen($key));
    }

    public function testRecoveryCodeIsConsumedOnce(): void
    {
        $totp = new MfaTotp();
        $codes = $totp->generateRecoveryCodes(1);
        $remaining = $totp->consumeRecoveryCode(strtolower($codes['plain'][0]), $codes['hashes']);

        $this->assertSame([], $remaining);
        $this->assertNull($totp->consumeRecoveryCode($codes['plain'][0], $remaining));
    }
}
