<?php

namespace App\Libraries;

use RuntimeException;

class MfaTotp
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD);
        $message = pack('N2', intdiv($counter, 0x100000000), $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $message, $this->base32Decode($secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $binary = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function verifyCode(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $timestamp ??= time();
        foreach ([-1, 0, 1] as $window) {
            if (hash_equals($this->currentCode($secret, $timestamp + ($window * self::PERIOD)), $code)) {
                return true;
            }
        }

        return false;
    }

    public function encryptSecret(string $secret): string
    {
        $this->ensureSodium();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($secret, $nonce, $this->encryptionKey());

        return base64_encode($nonce . $ciphertext);
    }

    public function decryptSecret(string $encrypted): string
    {
        $this->ensureSodium();
        $payload = base64_decode($encrypted, true);
        if ($payload === false || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('El secreto MFA almacenado no tiene un formato válido.');
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open(
            substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $nonce,
            $this->encryptionKey()
        );
        if ($plaintext === false) {
            throw new RuntimeException('No se pudo descifrar el secreto MFA. Verifica MFA_ENCRYPTION_KEY.');
        }

        return $plaintext;
    }

    public function provisioningUri(string $secret, string $account, string $issuer = 'Fruver App'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashes = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(4)));
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
            $plain[] = $code;
            $hashes[] = password_hash($this->normalizeRecoveryCode($code), PASSWORD_DEFAULT);
        }

        return ['plain' => $plain, 'hashes' => $hashes];
    }

    public function consumeRecoveryCode(string $code, array $hashes): ?array
    {
        $normalized = $this->normalizeRecoveryCode($code);
        if (strlen($normalized) !== 8) {
            return null;
        }

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && password_verify($normalized, $hash)) {
                unset($hashes[$index]);

                return array_values($hashes);
            }
        }

        return null;
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $code) ?? '');
    }

    private function encryptionKey(): string
    {
        $this->ensureSodium();

        $encoded = (string) env('MFA_ENCRYPTION_KEY');
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Configura MFA_ENCRYPTION_KEY como una clave Base64 de 32 bytes.');
        }

        return $key;
    }

    private function ensureSodium(): void
    {
        if (! function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('La extensión Sodium es necesaria para proteger MFA.');
        }
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0');
            }
            $encoded .= self::BASE32[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim($secret, '='));
        if ($secret === '' || preg_match('/[^A-Z2-7]/', $secret)) {
            throw new RuntimeException('El secreto TOTP no es válido.');
        }

        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin(strpos(self::BASE32, $character)), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
