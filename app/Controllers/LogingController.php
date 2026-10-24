<?php

namespace App\Controllers;

use App\Libraries\AccountCredentials;
use App\Models\AdminModel;
use App\Models\ClienteModel;
use App\Models\RepartidorModel;
use App\Models\VendedorModel;
use CodeIgniter\HTTP\RedirectResponse;

class LogingController extends BaseController
{
    public function login()
    {
        if (session()->has('email_verification_pending')) {
            return redirect()->to(base_url('login/verificar-correo'));
        }

        if (session()->get('isLoggedIn')) {
            return redirect()->to($this->homeForRole());
        }

        return view('login', ['error' => session()->getFlashdata('error')]);
    }

    public function autenticar()
    {
        if (session()->has('email_verification_pending')) {
            return redirect()->to(base_url('login/verificar-correo'));
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
            return $this->iniciarVerificacionCorreo('admin', $admin);
        }

        $vendedor = (new VendedorModel())->where('email', $email)->first();
        if ($vendedor !== null && isset($vendedor['hash']) && password_verify($contrasena, $vendedor['hash'])) {
            return $this->iniciarVerificacionCorreo('vendedor', $vendedor);
        }

        $repartidor = (new RepartidorModel())->where('email', $email)->first();
        if ($repartidor !== null && ! empty($repartidor['hash']) && password_verify($contrasena, $repartidor['hash'])) {
            return $this->iniciarVerificacionCorreo('repartidor', $repartidor);
        }

        $cliente = (new ClienteModel())
            ->where('email', $email)
            ->where('baja', 0)
            ->first();

        if ($cliente !== null && isset($cliente['hash']) && password_verify($contrasena, $cliente['hash'])) {
            return $this->iniciarVerificacionCorreo('cliente', $cliente);
        }

        return view('login', [
            'error' => 'El correo o la contraseña no son correctos.',
            'email' => $email,
        ]);
    }

    public function mostrarVerificacionCorreo()
    {
        $pending = session()->get('email_verification_pending');
        if (! is_array($pending) || ! isset($pending['role'], $pending['email'])) {
            return redirect()->to(base_url('login'));
        }

        if (time() - (int) ($pending['started_at'] ?? 0) > 600) {
            session()->remove('email_verification_pending');
            session()->setFlashdata('error', 'La verificación expiró. Inicia sesión de nuevo.');

            return redirect()->to(base_url('login'));
        }

        return view('email_verification', [
            'email'  => $pending['email'],
            'error'  => session()->getFlashdata('error'),
            'notice' => session()->getFlashdata('notice'),
        ]);
    }

    public function verificarCorreo(): RedirectResponse
    {
        $pending = session()->get('email_verification_pending');
        if (! is_array($pending) || ! isset($pending['role'], $pending['email'])) {
            return redirect()->to(base_url('login'));
        }

        if (time() - (int) ($pending['started_at'] ?? 0) > 600) {
            session()->remove('email_verification_pending');
            session()->setFlashdata('error', 'La verificación expiró. Inicia sesión de nuevo.');

            return redirect()->to(base_url('login'));
        }

        $account = $this->accountTable((string) $pending['role'])
            ->where('email', $pending['email'])
            ->get()
            ->getRowArray();
        if ($account === null) {
            session()->remove('email_verification_pending');
            session()->setFlashdata('error', 'No se pudo verificar el correo. Inicia sesión de nuevo.');

            return redirect()->to(base_url('login'));
        }

        if (! array_key_exists('email_verified_at', $account)) {
            log_message('error', 'Faltan las columnas de verificación de correo para la cuenta {email}.', ['email' => $pending['email']]);
            throw new \RuntimeException('La base de datos no está preparada para verificar el correo.');
        }

        if (! empty($account['email_verified_at'])) {
            $this->completarAutenticacion((string) $pending['role'], $account);

            return redirect()->to($this->homeForRole());
        }

        $expiresAt = strtotime((string) ($account['email_verification_expires_at'] ?? ''));
        $codeHash = (string) ($account['email_verification_code_hash'] ?? '');
        if ($expiresAt === false || $expiresAt <= time() || $codeHash === '') {
            $cleared = $this->accountTable((string) $pending['role'])
                ->where('email', $pending['email'])
                ->update([
                    'email_verification_code_hash' => null,
                    'email_verification_expires_at' => null,
                ]);
            if (! $cleared) {
                log_message('error', 'No se pudo limpiar el código vencido para {email}.', ['email' => $pending['email']]);
                throw new \RuntimeException('No se pudo limpiar el código de verificación vencido.');
            }
            session()->remove('email_verification_pending');
            session()->setFlashdata('error', 'El código expiró. Inicia sesión para solicitar uno nuevo.');

            return redirect()->to(base_url('login'));
        }

        $code = trim((string) $this->request->getPost('code'));
        if (preg_match('/^\d{6}$/D', $code) !== 1 || ! password_verify($code, $codeHash)) {
            return $this->falloCorreo((string) $pending['role'], (string) $pending['email']);
        }

        $updated = $this->accountTable((string) $pending['role'])
            ->where('email', $pending['email'])
            ->where('email_verified_at', null)
            ->where('email_verification_code_hash', $codeHash)
            ->where('email_verification_expires_at >', date('Y-m-d H:i:s'))
            ->update([
                'email_verified_at' => date('Y-m-d H:i:s'),
                'email_verification_code_hash' => null,
                'email_verification_expires_at' => null,
                'email_verification_attempts' => 0,
                'email_verification_locked_until' => null,
            ]);
        if (! $updated || db_connect()->affectedRows() !== 1) {
            return $this->falloCorreo((string) $pending['role'], (string) $pending['email']);
        }

        $account['email_verified_at'] = date('Y-m-d H:i:s');
        $this->completarAutenticacion((string) $pending['role'], $account);

        return redirect()->to($this->homeForRole());
    }

    public function cancelarVerificacionCorreo(): RedirectResponse
    {
        session()->remove(['email_verification_pending', 'mfa_pending', 'mfa_setup_secret']);

        return redirect()->to(base_url('login'));
    }

    public function cerrarSesion()
    {
        session()->destroy();

        return redirect()->to(base_url('login'));
    }

    private function homeForRole(): string
    {
        return match (session()->get('auth_role')) {
            'cliente'    => base_url('productos'),
            'vendedor'   => base_url('clientes'),
            'repartidor' => base_url('repartidores'),
            default      => base_url('dashboard'),
        };
    }

    private function iniciarVerificacionCorreo(string $role, array $account): RedirectResponse
    {
        if (! array_key_exists('email_verified_at', $account)) {
            log_message('error', 'Faltan las columnas de verificación de correo para la cuenta {email}.', ['email' => $account['email']]);
            throw new \RuntimeException('La base de datos no está preparada para verificar el correo.');
        }

        if (! empty($account['email_verified_at'])) {
            $this->completarAutenticacion($role, $account);

            return redirect()->to($this->homeForRole());
        }

        $lockedUntil = strtotime((string) ($account['email_verification_locked_until'] ?? ''));
        if ($lockedUntil !== false && $lockedUntil > time()) {
            session()->setFlashdata('error', 'La verificación está bloqueada temporalmente. Intenta más tarde.');

            return redirect()->to(base_url('login'));
        }

        $expiresAt = strtotime((string) ($account['email_verification_expires_at'] ?? ''));
        $sentAt = strtotime((string) ($account['email_verification_sent_at'] ?? ''));
        if (! empty($account['email_verification_code_hash'])
            && $expiresAt !== false
            && $expiresAt > time()
            && $sentAt !== false
            && $sentAt > time() - 60) {
            $this->iniciarSesionPendiente($role, (string) $account['email']);
            session()->setFlashdata('notice', 'Ya se envió un código recientemente. Revisa tu correo.');

            return redirect()->to(base_url('login/verificar-correo'));
        }

        $configurationError = AccountCredentials::configurationError();
        if ($configurationError !== null) {
            session()->setFlashdata('error', $configurationError);

            return redirect()->to(base_url('login'));
        }

        $code = AccountCredentials::generateVerificationCode();
        $updated = $this->accountTable($role)
            ->where('email', $account['email'])
            ->where('email_verified_at', null)
            ->update([
                'email_verification_code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'email_verification_expires_at' => date('Y-m-d H:i:s', time() + 600),
                'email_verification_sent_at' => date('Y-m-d H:i:s'),
                'email_verification_attempts' => 0,
            ]);
        if (! $updated) {
            log_message('error', 'No se pudo guardar el código de verificación para {email}.', ['email' => $account['email']]);
            throw new \RuntimeException('No se pudo iniciar la verificación por correo.');
        }

        $name = trim((string) ($account['nombre'] ?? ''));
        if (! AccountCredentials::sendVerificationCode((string) $account['email'], $name, $code)) {
            $cleared = $this->accountTable($role)
                ->where('email', $account['email'])
                ->where('email_verified_at', null)
                ->update([
                    'email_verification_code_hash' => null,
                    'email_verification_expires_at' => null,
                ]);
            if (! $cleared) {
                log_message('error', 'No se pudo limpiar el código no enviado para {email}.', ['email' => $account['email']]);
                throw new \RuntimeException('No se pudo limpiar el código de verificación no enviado.');
            }
            session()->setFlashdata('error', 'No se pudo enviar el código. Revisa la configuración del correo e inténtalo de nuevo.');

            return redirect()->to(base_url('login'));
        }

        $this->iniciarSesionPendiente($role, (string) $account['email']);
        session()->setFlashdata('notice', 'Enviamos un código de 6 dígitos a tu correo. Es válido durante 10 minutos.');

        return redirect()->to(base_url('login/verificar-correo'));
    }

    private function iniciarSesionPendiente(string $role, string $email): void
    {
        session()->regenerate(true);
        session()->remove([
            'isLoggedIn', 'auth_role', 'admin_email', 'admin_nombre',
            'vendedor_id', 'vendedor_nombre', 'repartidor_id', 'repartidor_nombre',
            'cliente_id', 'cliente_nombre', 'carrito',
            'mfa_pending', 'mfa_setup_secret',
        ]);
        session()->set('email_verification_pending', [
            'role'       => $role,
            'email'      => $email,
            'started_at' => time(),
        ]);
    }

    private function falloCorreo(string $role, string $email): RedirectResponse
    {
        $account = $this->accountTable($role)
            ->where('email', $email)
            ->get()
            ->getRowArray();
        if ($account === null) {
            throw new \RuntimeException('No se encontró la cuenta para registrar el intento de verificación.');
        }

        $attempts = (int) ($account['email_verification_attempts'] ?? 0) + 1;
        $locked = $attempts >= 5;
        $updated = $this->accountTable($role)
            ->where('email', $email)
            ->update([
                'email_verification_attempts' => $locked ? 0 : $attempts,
                'email_verification_code_hash' => $locked ? null : $account['email_verification_code_hash'],
                'email_verification_expires_at' => $locked ? null : $account['email_verification_expires_at'],
                'email_verification_locked_until' => $locked ? date('Y-m-d H:i:s', time() + 600) : null,
            ]);
        if (! $updated) {
            log_message('error', 'No se pudo guardar el intento de verificación para {email}.', ['email' => $email]);
            throw new \RuntimeException('No se pudo registrar el intento de verificación.');
        }

        if ($locked) {
            session()->remove('email_verification_pending');
            session()->setFlashdata('error', 'Se excedió el número de intentos. Intenta de nuevo en 10 minutos.');

            return redirect()->to(base_url('login'));
        }

        session()->setFlashdata('error', 'El código no es válido. Revisa el correo e inténtalo de nuevo.');

        return redirect()->to(base_url('login/verificar-correo'));
    }

    private function completarAutenticacion(string $role, array $account): void
    {
        session()->regenerate(true);
        session()->remove([
            'email_verification_pending', 'mfa_pending', 'mfa_setup_secret', 'isLoggedIn', 'auth_role',
            'admin_email', 'admin_nombre', 'vendedor_id', 'vendedor_nombre',
            'repartidor_id', 'repartidor_nombre', 'cliente_id', 'cliente_nombre', 'carrito',
        ]);
        $identity = [
            'auth_role'  => $role,
            'isLoggedIn' => true,
        ];
        if ($role === 'admin') {
            $identity['admin_email']  = $account['email'];
            $identity['admin_nombre'] = $account['nombre'];
        } elseif ($role === 'vendedor') {
            $identity['vendedor_id']     = $account['id'];
            $identity['vendedor_nombre'] = $account['nombre'];
        } elseif ($role === 'repartidor') {
            $identity['repartidor_id']     = $account['id_repartidor'];
            $identity['repartidor_nombre'] = trim(($account['nombre'] ?? '') . ' ' . ($account['ap_paterno'] ?? ''));
        } else {
            $identity['cliente_id']     = $account['id_cliente'];
            $identity['cliente_nombre'] = $account['nombre'];
        }
        session()->set($identity);
    }

    private function accountTable(string $role): \CodeIgniter\Database\BaseBuilder
    {
        if (! in_array($role, ['admin', 'vendedor', 'repartidor', 'cliente'], true)) {
            throw new \InvalidArgumentException('Rol no válido para verificación por correo.');
        }

        return db_connect()->table($role);
    }
}