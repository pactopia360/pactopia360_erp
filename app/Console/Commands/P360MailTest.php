<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class P360MailTest extends Command
{
    protected $signature = 'p360:mail-test 
        {--to= : Correo destino}
        {--name=Usuario : Nombre para el template}
        {--loginUrl= : URL de login (opcional)}';

    protected $description = 'Envía un correo de prueba usando la plantilla de verificación/bienvenida';

    public function handle(\Illuminate\Contracts\Mail\Mailer $mail)
    {
        $to       = (string) $this->option('to');
        $name     = (string) $this->option('name') ?: 'Usuario';
        $loginUrl = (string) $this->option('loginUrl') ?: route('cliente.login');

        if (!$to) {
            $this->error('Debes indicar --to=correo@dominio.com');
            return self::FAILURE;
        }

        // Datos de demo
        $viewData = [
            'nombre'       => $name,                 // <- usa "nombre"
            'email'        => $to,
            'rfc'          => 'TEST010101AAA',
            'tempPassword' => '8deuF-*-s7KK',
            'loginUrl'     => $loginUrl,
            'is_pro'       => false,
            'soporte'      => 'soporte@pactopia.com',
            // fuerza logo absoluto (si quieres bypass de ASSET_URL)
            'logoUrl'      => rtrim(config('app.asset_url') ?: config('app.url'), '/') . '/assets/client/logop360light.png',
        ];

        try {
            $mail->send(
                [
                    'html' => 'emails.cliente.welcome_account_activated',
                    'text' => 'emails.cliente.welcome_account_activated_text',
                ],
                $viewData,
                function ($m) use ($to) {
                    $m->to($to)->subject('Prueba de correo · Pactopia360');
                }
            );
            \Log::info('[MAIL-TEST] Vista welcome_account_activated enviada', $viewData);
            $this->info("✅ Correo de prueba enviado a: {$to}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            \Log::error('[MAIL-TEST][ERROR]', ['e' => $e->getMessage()]);
            $this->error('❌ Error enviando el correo: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

}
