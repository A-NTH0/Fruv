<?php

namespace App\Controllers;

use App\Libraries\MfaTotp;

class MfaController extends BaseController
{
    public function index()
    {
        $account = $this->currentAccount();
        if ((int) ($account['mfa_enabled'] ?? 0) !== 1) {
            session()->destroy();

            return redirect()->to(base_url('login'));
        }

        return view('seguridad_mfa', [
            'recoveryCodes' => session()->getFlashdata('mfa_recovery_codes'),
            'success'       => session()->getFlashdata('success'),
            'warning'       => session()->getFlashdata('warning'),
            'error'         => session()->getFlashdata('error'),
        ]);
    }

    public function regenerarCodigos()
    {
        $account = $this->currentAccount();
        $totp = new MfaTotp();
        $code = trim((string) $this->request->getPost('code'));
        $secret = $totp->decryptSecret((string) ($account['mfa_secret'] ?? ''));
        if (! $totp->verifyCode($secret, $code)) {
            session()->setFlashdata('error', 'El código de autenticación no es válido.');

            return redirect()->to(base_url('seguridad'));
        }

        $recovery = $totp->generateRecoveryCodes();
        $updated = $this->accountBuilder()
            ->where('email', $account['email'])
            ->update(['mfa_recovery_codes' => json_encode($recovery['hashes'], JSON_THROW_ON_ERROR)]);
        if (! $updated) {
            log_message('error', 'No se pudieron regenerar los códigos MFA para {email}.', ['email' => $account['email']]);
            throw new \RuntimeException('No se pudieron guardar los códigos de recuperación.');
        }

        session()->setFlashdata('success', 'Los códigos anteriores dejaron de funcionar. Guarda los nuevos en un lugar seguro.');
        session()->setFlashdata('mfa_recovery_codes', $recovery['plain']);

        return redirect()->to(base_url('seguridad'));
    }

    private function currentAccount(): array
    {
        $role = session()->get('auth_role');
        $builder = $this->accountBuilder();
        if ($role === 'admin') {
            $builder->where('email', session()->get('admin_email'));
        } else {
            $builder->where('id', (int) session()->get('vendedor_id'));
        }

        $account = $builder->get()->getRowArray();
        if ($account === null) {
            session()->destroy();
            throw new \RuntimeException('No se encontró la cuenta autenticada.');
        }

        return $account;
    }

    private function accountBuilder(): \CodeIgniter\Database\BaseBuilder
    {
        $role = session()->get('auth_role');
        if (! in_array($role, ['admin', 'vendedor'], true)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException();
        }

        return db_connect()->table($role);
    }
}
