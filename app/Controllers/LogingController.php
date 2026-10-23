<?php

namespace App\Controllers;

use App\Libraries\MfaTotp;
use App\Models\AdminModel;
use App\Models\ClienteModel;
use App\Models\VendedorModel;
use CodeIgniter\HTTP\RedirectResponse;

class LogingController extends BaseController
{
    public function login()
    {
        if (session()->has('mfa_pending')) {
            return redirect()->to(base_url('login/mfa'));
        }

        if (session()->get('isLoggedIn')) {
            return redirect()->to($this->homeForRole());
        }

        return view('login', ['error' => session()->getFlashdata('error')]);
    }

    public function autenticar()
    {
        if (session()->has('mfa_pending')) {
            return redirect()->to(base_url('login/mfa'));
        }

        $rules = [
            'email'      => 'required|valid_email|max_length[50]',
            'contrasena' => 'required|max_length[255]',
        ];
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $contrasena = (string) $this->request->getPost('contrasena');

        if (! $this->validate($rules, [
            'email'      => $email,
            'contrasena' => $contrasena,
        ])) {
            return view('login', [
                'validation' => $this->validator,
                'email'      => $email,
            ]);
        }

        $admin = (new AdminModel())->where('email', $email)->first();
        if ($admin !== null && isset($admin['hash']) && password_verify($contrasena, $admin['hash'])) {
            return $this->iniciarMfa('admin', $admin);
        }

        $vendedor = (new VendedorModel())->where('email', $email)->first();
        if ($vendedor !== null && isset($vendedor['hash']) && password_verify($contrasena, $vendedor['hash'])) {
            return $this->iniciarMfa('vendedor', $vendedor);
        }

        $cliente = (new ClienteModel())
            ->where('email', $email)
            ->where('baja', 0)
            ->first();

        if ($cliente !== null && isset($cliente['hash']) && password_verify($contrasena, $cliente['hash'])) {
            session()->regenerate(true);
            session()->remove(['admin_email', 'admin_nombre', 'vendedor_id', 'vendedor_nombre']);
            session()->set([
                'auth_role'   => 'cliente',
                'cliente_id'  => $cliente['id_cliente'],
                'cliente_nombre' => $cliente['nombre'],
                'isLoggedIn'  => true,
            ]);

            return redirect()->to(base_url('productos'));
        }

        return view('login', [
            'error' => 'El correo o la contraseña no son correctos.',
            'email' => $email,
        ]);
    }

    public function mostrarMfa()
    {
        $pending = session()->get('mfa_pending');
        if (! is_array($pending) || ! isset($pending['role'], $pending['email'], $pending['mode'])) {
            return redirect()->to(base_url('login'));
        }

        if (time() - (int) ($pending['started_at'] ?? 0) > 300) {
            session()->remove(['mfa_pending', 'mfa_setup_secret']);
            session()->setFlashdata('error', 'La verificación expiró. Inicia sesión de nuevo.');

            return redirect()->to(base_url('login'));
        }

        $data = [
            'mode'  => $pending['mode'],
            'error' => session()->getFlashdata('error'),
            'notice' => session()->getFlashdata('notice'),
            'email' => $pending['email'],
        ];
        if ($pending['mode'] === 'setup') {
            $data['secret'] = (string) session()->get('mfa_setup_secret');
            $data['provisioningUri'] = (new MfaTotp())->provisioningUri($data['secret'], $pending['email']);
        }

        return view('mfa', $data);
    }

    public function verificarMfa(): RedirectResponse
    {
        $pending = session()->get('mfa_pending');
        if (! is_array($pending) || ! isset($pending['role'], $pending['email'], $pending['mode'])) {
            return redirect()->to(base_url('login'));
        }

        if (time() - (int) ($pending['started_at'] ?? 0) > 300) {
            session()->remove(['mfa_pending', 'mfa_setup_secret']);
            session()->setFlashdata('error', 'La verificación expiró. Inicia sesión de nuevo.');

            return redirect()->to(base_url('login'));
        }

        $attempts = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= 5) {
            session()->remove(['mfa_pending', 'mfa_setup_secret']);
            session()->setFlashdata('error', 'Se excedió el número de intentos. Inicia sesión de nuevo.');

            return redirect()->to(base_url('login'));
        }

        $code = trim((string) $this->request->getPost('code'));
        $totp = new MfaTotp();
        if ($pending['mode'] === 'setup') {
            $secret = (string) session()->get('mfa_setup_secret');
            if (! $totp->verifyCode($secret, $code)) {
                return $this->falloMfa($pending, 'El código no es válido. Revisa la hora de tu dispositivo e inténtalo de nuevo.');
            }

            $recovery = $totp->generateRecoveryCodes();
            $updated = $this->accountTable($pending['role'])
                ->where('email', $pending['email'])
                ->update([
                    'mfa_enabled'        => 1,
                    'mfa_secret'         => $totp->encryptSecret($secret),
                    'mfa_recovery_codes' => json_encode($recovery['hashes'], JSON_THROW_ON_ERROR),
                ]);

            if (! $updated) {
                log_message('error', 'No se pudo guardar MFA para la cuenta {email}.', ['email' => $pending['email']]);
                throw new \RuntimeException('No se pudo guardar la configuración MFA. Intenta de nuevo más tarde.');
            }

            $this->completarAutenticacion($pending);
            session()->setFlashdata('mfa_recovery_codes', $recovery['plain']);

            return redirect()->to(base_url('seguridad'));
        }

        $account = $this->accountTable($pending['role'])
            ->where('email', $pending['email'])
            ->get()
            ->getRowArray();
        if ($account === null || (int) ($account['mfa_enabled'] ?? 0) !== 1) {
            session()->remove(['mfa_pending', 'mfa_setup_secret']);
            session()->setFlashdata('error', 'No se pudo verificar la configuración MFA. Inicia sesión de nuevo.');

            return redirect()->to(base_url('login'));
        }

        $secret = $totp->decryptSecret((string) $account['mfa_secret']);
        if ($totp->verifyCode($secret, $code)) {
            $this->completarAutenticacion($pending);

            return redirect()->to($this->homeForRole());
        }

        $storedRecoveryCodes = $account['mfa_recovery_codes'] ?? null;
        $recoveryHashes = json_decode((string) ($storedRecoveryCodes ?? '[]'), true);
        if (! is_array($recoveryHashes)) {
            $recoveryHashes = [];
        }
        $remainingCodes = $totp->consumeRecoveryCode($code, $recoveryHashes);
        if ($remainingCodes !== null) {
            $updated = $this->accountTable($pending['role'])
                ->where('email', $pending['email'])
                ->where('mfa_recovery_codes', $storedRecoveryCodes)
                ->update([
                    'mfa_recovery_codes' => json_encode($remainingCodes, JSON_THROW_ON_ERROR),
                    'mfa_failed_attempts' => 0,
                    'mfa_locked_until' => null,
                ]);
            if (! $updated || db_connect()->affectedRows() !== 1) {
                return $this->falloMfa($pending, 'El código de recuperación ya fue utilizado. Prueba con otro código.');
            }
            $pending['mode'] = 'setup';
            $pending['attempts'] = 0;
            $pending['started_at'] = time();
            session()->set('mfa_pending', $pending);
            session()->set('mfa_setup_secret', $totp->generateSecret());
            session()->setFlashdata('notice', 'Se usó un código de recuperación. Configura de nuevo tu aplicación autenticadora para proteger la cuenta.');

            return redirect()->to(base_url('login/mfa'));
        }

        return $this->falloMfa($pending, 'El código no es válido o ya fue utilizado.');
    }

    public function cerrarSesion()
    {
        session()->destroy();

        return redirect()->to(base_url('login'));
    }

    private function homeForRole(): string
    {
        return match (session()->get('auth_role')) {
            'cliente'   => base_url('productos'),
            'vendedor'  => base_url('clientes'),
            default     => base_url('dashboard'),
        };
    }

    private function iniciarMfa(string $role, array $account): RedirectResponse
    {
        if ((int) ($account['mfa_enabled'] ?? 0) === 1
            && ! empty($account['mfa_locked_until'])
            && strtotime((string) $account['mfa_locked_until']) > time()) {
            session()->setFlashdata('error', 'La verificación está bloqueada temporalmente. Intenta más tarde.');

            return redirect()->to(base_url('login'));
        }

        session()->regenerate(true);
        session()->remove([
            'isLoggedIn', 'auth_role', 'admin_email', 'admin_nombre',
            'vendedor_id', 'vendedor_nombre', 'cliente_id', 'cliente_nombre', 'carrito',
            'mfa_setup_secret',
        ]);

        $enabled = (int) ($account['mfa_enabled'] ?? 0) === 1;
        session()->set('mfa_pending', [
            'role'       => $role,
            'email'      => (string) $account['email'],
            'mode'       => $enabled ? 'challenge' : 'setup',
            'started_at' => time(),
            'attempts'   => 0,
        ]);
        if (! $enabled) {
            session()->set('mfa_setup_secret', (new MfaTotp())->generateSecret());
        }

        return redirect()->to(base_url('login/mfa'));
    }

    private function falloMfa(array $pending, string $message): RedirectResponse
    {
        $pending['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
        session()->set('mfa_pending', $pending);
        if ($pending['mode'] === 'challenge') {
            $account = $this->accountTable($pending['role'])
                ->where('email', $pending['email'])
                ->get()
                ->getRowArray();
            if ($account === null) {
                throw new \RuntimeException('No se encontró la cuenta para registrar el intento MFA.');
            }
            $failures = min(5, (int) ($account['mfa_failed_attempts'] ?? 0) + 1);
            $updated = $this->accountTable($pending['role'])
                ->where('email', $pending['email'])
                ->update([
                    'mfa_failed_attempts' => $failures,
                    'mfa_locked_until'     => $failures >= 5 ? date('Y-m-d H:i:s', time() + 600) : null,
                ]);
            if (! $updated) {
                log_message('error', 'No se pudo guardar el intento MFA para {email}.', ['email' => $pending['email']]);
                throw new \RuntimeException('No se pudo registrar el intento MFA.');
            }
        }
        session()->setFlashdata('error', $message);

        return redirect()->to(base_url('login/mfa'));
    }

    private function completarAutenticacion(array $pending): void
    {
        $account = $this->accountTable($pending['role'])
            ->where('email', $pending['email'])
            ->get()
            ->getRowArray();
        if ($account === null) {
            throw new \RuntimeException('La cuenta no está disponible para completar el inicio de sesión.');
        }

        $resetAttempts = $this->accountTable($pending['role'])
            ->where('email', $pending['email'])
            ->update(['mfa_failed_attempts' => 0, 'mfa_locked_until' => null]);
        if (! $resetAttempts) {
            log_message('error', 'No se pudo restablecer el contador MFA para {email}.', ['email' => $pending['email']]);
            throw new \RuntimeException('No se pudo completar de forma segura la autenticación MFA.');
        }

        session()->regenerate(true);
        session()->remove([
            'mfa_pending', 'mfa_setup_secret', 'isLoggedIn', 'auth_role',
            'admin_email', 'admin_nombre', 'vendedor_id', 'vendedor_nombre',
            'cliente_id', 'cliente_nombre', 'carrito',
        ]);
        $identity = [
            'auth_role'  => $pending['role'],
            'isLoggedIn' => true,
        ];
        if ($pending['role'] === 'admin') {
            $identity['admin_email'] = $account['email'];
            $identity['admin_nombre'] = $account['nombre'];
        } else {
            $identity['vendedor_id'] = $account['id'];
            $identity['vendedor_nombre'] = $account['nombre'];
        }
        session()->set($identity);
    }

    private function accountTable(string $role): \CodeIgniter\Database\BaseBuilder
    {
        if (! in_array($role, ['admin', 'vendedor'], true)) {
            throw new \InvalidArgumentException('Rol no válido para MFA.');
        }

        return db_connect()->table($role);
    }
}
