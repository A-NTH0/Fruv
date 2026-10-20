<?php

namespace App\Libraries;

final class AccountCredentials
{
    public static function generatePassword(): string
    {
        $characterSets = [
            'abcdefghijkmnopqrstuvwxyz',
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            '23456789',
            '!@#$%*-_',
        ];
        $allCharacters = implode('', $characterSets);
        $password      = [];

        foreach ($characterSets as $characterSet) {
            $password[] = $characterSet[random_int(0, strlen($characterSet) - 1)];
        }

        for ($i = count($password); $i < 16; $i++) {
            $password[] = $allCharacters[random_int(0, strlen($allCharacters) - 1)];
        }

        for ($i = count($password) - 1; $i > 0; $i--) {
            $swapIndex        = random_int(0, $i);
            [$password[$i], $password[$swapIndex]] = [$password[$swapIndex], $password[$i]];
        }

        return implode('', $password);
    }

    public static function configurationError(): ?string
    {
        $config  = config('Email');
        $invalid = [];

        if (self::isPlaceholder($config->fromEmail) || ! filter_var($config->fromEmail, FILTER_VALIDATE_EMAIL)) {
            $invalid[] = 'email.fromEmail';
        }

        if ($config->protocol === 'smtp') {
            if (self::isPlaceholder($config->SMTPHost) || trim($config->SMTPHost) === '') {
                $invalid[] = 'email.SMTPHost';
            }
            if (self::isPlaceholder($config->SMTPUser) || ! filter_var($config->SMTPUser, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = 'email.SMTPUser';
            }
            if (self::isPlaceholder($config->SMTPPass) || trim($config->SMTPPass) === '') {
                $invalid[] = 'email.SMTPPass';
            }
        }

        if ($invalid === []) {
            return null;
        }

        return 'La configuración de correo aún tiene valores de ejemplo o inválidos: '
            . implode(', ', $invalid)
            . '. En .env coloca tu dirección real de Gmail en email.fromEmail y email.SMTPUser, '
            . 'y una contraseña de aplicación de Google en email.SMTPPass. No uses la contraseña normal de Gmail.';
    }

    private static function isPlaceholder(string $value): bool
    {
        return preg_match('/tu-|example\\.com|changeme|placeholder/i', $value) === 1;
    }

    public static function sendPassword(string $recipient, string $name, string $role, string $password): bool
    {
        $config = config('Email');

        if (self::configurationError() !== null) {
            log_message('error', 'No se envió la contraseña de acceso: ' . self::configurationError());

            return false;
        }

        $email = service('email');
        $email->setFrom($config->fromEmail, $config->fromName ?: 'Fruver');
        $email->setTo($recipient);
        $email->setSubject('Bienvenido a Fruver | Tus datos de acceso');
        $email->setMailType('html');
        $email->setAltMessage(
            "Hola {$name},\n\n"
            . "Tu cuenta de {$role} en Fruver ya está lista.\n\n"
            . "Correo: {$recipient}\n"
            . "Contraseña: {$password}\n\n"
            . "Guarda esta contraseña en un lugar seguro y no la compartas."
        );
        $email->setMessage(self::passwordEmailHtml($name, $recipient, $role, $password));

        $sent = $email->send(false);
        if (! $sent) {
            $smtpDebug = strip_tags($email->printDebugger([]));
            log_message('error', 'Falló el envío del correo de acceso. Respuesta SMTP: ' . $smtpDebug);
        }
        $email->clear(true);

        if (! $sent) {
            log_message('error', 'No se pudo enviar el correo de acceso de Fruver.');
        }

        return $sent;
    }

    private static function passwordEmailHtml(string $name, string $recipient, string $role, string $password): string
    {
        $safeName      = self::escapeHtml($name);
        $safeRecipient = self::escapeHtml($recipient);
        $safeRole      = self::escapeHtml(ucfirst($role));
        $safePassword  = self::escapeHtml($password);

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu acceso a Fruver</title>
</head>
<body style="margin:0; padding:32px 12px; background-color:#f4f7f5; font-family:Arial,Helvetica,sans-serif; color:#26332b;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background-color:#ffffff; border:1px solid #e5ece7; border-radius:16px; overflow:hidden;">
        <tr>
            <td style="padding:28px 36px; background-color:#800f2f; text-align:center;">
                <div style="font-size:28px; line-height:1.2; font-weight:700; letter-spacing:1px; color:#ffffff;">Fruver</div>
                <div style="margin-top:7px; font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#f9dce5;">Fresco y cerca de ti</div>
            </td>
        </tr>
        <tr>
            <td style="padding:36px 36px 12px;">
                <div style="font-size:13px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#54805e;">Cuenta {$safeRole}</div>
                <h1 style="margin:10px 0 14px; font-size:25px; line-height:1.3; color:#26332b;">¡Hola, {$safeName}!</h1>
                <p style="margin:0; font-size:16px; line-height:1.7; color:#5c6b61;">Tu cuenta de Fruver ya está lista. Usa estos datos para iniciar sesión:</p>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 36px 28px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f6faf7; border:1px solid #e2eee5; border-radius:12px;">
                    <tr>
                        <td style="padding:18px 20px 8px; font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#718078;">Correo electrónico</td>
                    </tr>
                    <tr>
                        <td style="padding:0 20px 18px; font-size:16px; line-height:1.5; color:#26332b; word-break:break-word;">{$safeRecipient}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 20px 8px; font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#718078;">Contraseña</td>
                    </tr>
                    <tr>
                        <td style="padding:0 20px 20px; font-family:Consolas,Monaco,monospace; font-size:20px; font-weight:700; letter-spacing:1px; color:#800f2f; word-break:break-word;">{$safePassword}</td>
                    </tr>
                </table>
                <div style="margin-top:20px; padding:14px 16px; border-left:4px solid #d31a38; background-color:#fff5f7; font-size:14px; line-height:1.6; color:#69434c;">
                    Por seguridad, guarda esta contraseña en un lugar seguro y no la compartas.
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 36px; border-top:1px solid #edf1ee; text-align:center; font-size:12px; line-height:1.6; color:#87938b;">
                Este mensaje fue enviado automáticamente por Fruver. No es necesario responder.
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
