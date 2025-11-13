<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Envía un OTP por el canal indicado.
     * $channel: 'whatsapp' | 'sms'
     */
    public static function send(string $digitsPhone, string $code, string $channel = 'whatsapp'): bool
    {
        $driver = config('services.otp.driver', 'whatsapp'); // 'whatsapp' | 'twilio'
        $digits = self::onlyDigits($digitsPhone);

        if ($digits === '' || strlen($digits) < 8) {
            Log::warning('[OTP SEND] teléfono inválido', ['raw' => $digitsPhone]);
            return false;
        }

        $text = self::renderText($code);

        try {
            if ($driver === 'whatsapp') {
                // Opción A: WhatsApp Cloud (Meta)
                $provider = config('services.otp.whatsapp.provider', 'meta'); // 'meta' | 'twilio'
                if ($provider === 'meta') {
                    return self::sendWhatsAppCloud($digits, $text);
                }
                // Opción B: WhatsApp vía Twilio
                return self::sendTwilioWhatsApp($digits, $text);
            }

            // Driver Twilio (SMS plano)
            if ($driver === 'twilio') {
                return self::sendTwilioSms($digits, $text);
            }

            Log::warning('[OTP SEND] driver desconocido', ['driver' => $driver]);
            return false;
        } catch (\Throwable $e) {
            Log::error('[OTP SEND] excepción', ['e' => $e->getMessage()]);
            return false;
        }
    }

    /* =======================
     * Implementaciones
     * ======================= */

    /** WhatsApp Cloud API (Meta) */
    private static function sendWhatsAppCloud(string $digits, string $text): bool
    {
        $token   = config('services.whatsapp_cloud.token');
        $phoneId = config('services.whatsapp_cloud.phone_number_id');
        if (!$token || !$phoneId) {
            Log::error('[OTP SEND][WA-CLOUD] falta configuración');
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $digits,              // E.164 sin '+'
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $text],
        ];

        $resp = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/v20.0/{$phoneId}/messages", $payload);

        $ok = $resp->successful();
        if (!$ok) {
            Log::error('[OTP SEND][WA-CLOUD] fallo', ['status' => $resp->status(), 'body' => $resp->body()]);
        } else {
            Log::info('[OTP SEND][WA-CLOUD] OK', ['to' => $digits]);
        }
        return $ok;
    }

    /** WhatsApp por Twilio (requiere nº WhatsApp activo en Twilio) */
    private static function sendTwilioWhatsApp(string $digits, string $text): bool
    {
        $sid   = config('services.twilio.sid');
        $tok   = config('services.twilio.token');
        $from  = config('services.twilio.whatsapp_from'); // ej: 'whatsapp:+14155238886'
        if (!$sid || !$tok || !$from) {
            Log::error('[OTP SEND][TW-WA] falta configuración');
            return false;
        }

        $to = 'whatsapp:+' . ltrim($digits, '+');

        $resp = Http::withBasicAuth($sid, $tok)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To'   => $to,
                'Body' => $text,
            ]);

        $ok = $resp->successful();
        if (!$ok) {
            Log::error('[OTP SEND][TW-WA] fallo', ['status' => $resp->status(), 'body' => $resp->body()]);
        } else {
            Log::info('[OTP SEND][TW-WA] OK', ['to' => $to]);
        }
        return $ok;
    }

    /** SMS por Twilio */
    private static function sendTwilioSms(string $digits, string $text): bool
    {
        $sid  = config('services.twilio.sid');
        $tok  = config('services.twilio.token');
        $from = config('services.twilio.from'); // ej: '+12025550123'
        if (!$sid || !$tok || !$from) {
            Log::error('[OTP SEND][TW-SMS] falta configuración');
            return false;
        }

        $to = '+' . ltrim($digits, '+');

        $resp = Http::withBasicAuth($sid, $tok)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To'   => $to,
                'Body' => $text,
            ]);

        $ok = $resp->successful();
        if (!$ok) {
            Log::error('[OTP SEND][TW-SMS] fallo', ['status' => $resp->status(), 'body' => $resp->body()]);
        } else {
            Log::info('[OTP SEND][TW-SMS] OK', ['to' => $to]);
        }
        return $ok;
    }

    /* =======================
     * Utilidades
     * ======================= */

    private static function renderText(string $code): string
    {
        $brand = config('app.name', 'Pactopia360');
        return "Tu código de verificación {$brand} es: {$code}\nNo lo compartas. Expira en 10 minutos.";
    }

    private static function onlyDigits(string $s): string
    {
        return preg_replace('/\D+/', '', $s) ?: '';
    }
}
