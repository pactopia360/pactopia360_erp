<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OtpService;

class P360OtpTest extends Command
{
    /**
     * Uso:
     *  php artisan p360:otp-test --to=+5255XXXXXXXX --driver=whatsapp
     *  php artisan p360:otp-test --to=+5255XXXXXXXX --driver=twilio
     */
    protected $signature = 'p360:otp-test
        {--to= : Número destino en formato E.164 (ej: +525512345678)}
        {--driver= : Canal a usar: whatsapp|twilio (opcional; usa OTP_DRIVER si no se indica)}';

    protected $description = 'Envía un OTP de prueba por WhatsApp Cloud o SMS (Twilio)';

    public function handle(): int
    {
        $to     = (string) $this->option('to');
        $driver = (string) ($this->option('driver') ?: config('services.otp.driver', 'whatsapp'));

        if (!$to) {
            $this->error('Debes indicar --to=+5255XXXXXXX (incluye LADA y + si aplica).');
            return self::FAILURE;
        }

        // Normalizamos a solo dígitos para el envío (tanto Twilio como WhatsApp aceptan sin + en payload actual)
        $digits = preg_replace('/\D+/', '', $to);

        // Validación básica de longitud (10-15 dígitos)
        $len = strlen($digits);
        if ($len < 10 || $len > 15) {
            $this->error("Número inválido: '{$to}' → dígitos '{$digits}' (longitud {$len}). Usa E.164 (+5255...).");
            return self::FAILURE;
        }

        // Validar driver
        $driver = strtolower($driver);
        if (!in_array($driver, ['whatsapp', 'twilio'], true)) {
            $this->error("Driver inválido: '{$driver}'. Usa: whatsapp | twilio");
            return self::FAILURE;
        }

        // Código aleatorio
        $code = (string) random_int(100000, 999999);

        // Enviar
        try {
            $ok = OtpService::send($digits, $code, $driver);
            if ($ok) {
                $this->info("✅ OTP enviado a {$digits} con código: {$code} vía {$driver}");
                return self::SUCCESS;
            }
            $this->error('❌ Falló el envío. Revisa logs y credenciales.');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('❌ Excepción durante el envío: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
