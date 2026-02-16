<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tracking;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

final class BillingEmailTrackingController extends Controller
{
    /**
     * Pixel 1x1 para registrar OPEN
     * GET /t/billing/{token}.gif
     */
    public function open(Request $r, string $token): Response
    {
        $this->recordEvent($r, $token, 'open');

        // 1x1 GIF transparente
        $gif = base64_decode(
            'R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==',
            true
        ) ?: '';

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Redirect para registrar CLICK
     * GET /t/billing/{token}/c?u=<urlencoded>
     */
    public function click(Request $r, string $token): \Illuminate\Http\RedirectResponse
    {
        $u = (string) $r->query('u', '');
        $u = trim($u);

        // Si no viene URL, no rompemos: mandamos al home
        if ($u === '') {
            $this->recordEvent($r, $token, 'click', ['url' => null]);
            return redirect('/');
        }

        // Defensivo: solo http/https
        if (!preg_match('#^https?://#i', $u)) {
            $this->recordEvent($r, $token, 'click', ['url' => $u, 'blocked' => true]);
            return redirect('/');
        }

        $this->recordEvent($r, $token, 'click', ['url' => $u]);

        return redirect()->away($u);
    }

    // ==========================================================
    // Internals
    // ==========================================================

    private function tokenHash(string $token): string
    {
        // sha256 hex
        return hash('sha256', $token);
    }

    /**
     * Registra evento + actualiza contadores en billing_email_messages.
     * No falla si token no existe: simplemente no hace nada (no rompe correos).
     */
    private function recordEvent(Request $r, string $token, string $type, array $extra = []): void
    {
        try {
            $hash = $this->tokenHash($token);

            $msg = DB::table('billing_email_messages')
                ->where('token_hash', $hash)
                ->first(['id', 'open_count', 'click_count', 'opened_at']);

            if (!$msg) return;

            $messageId = (int) $msg->id;

            $ip = (string) ($r->ip() ?? '');
            $ua = (string) ($r->userAgent() ?? '');
            $ref = (string) ($r->headers->get('referer') ?? '');

            $url = $extra['url'] ?? null;

            DB::table('billing_email_events')->insert([
                'message_id' => $messageId,
                'type'       => $type,
                'ip'         => $ip !== '' ? $ip : null,
                'ua'         => $ua !== '' ? $ua : null,
                'url'        => is_string($url) && $url !== '' ? $url : null,
                'ref'        => $ref !== '' ? $ref : null,
                'meta'       => !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
            ]);

            $updates = ['last_event_at' => now()];

            if ($type === 'open') {
                $updates['open_count'] = (int)($msg->open_count ?? 0) + 1;
                // primera apertura
                if (empty($msg->opened_at)) $updates['opened_at'] = now();
            }

            if ($type === 'click') {
                $updates['click_count'] = (int)($msg->click_count ?? 0) + 1;
            }

            DB::table('billing_email_messages')
                ->where('id', $messageId)
                ->update($updates);
        } catch (\Throwable $e) {
            // silencioso intencional: tracking jamás debe romper la UI/correos
        }
    }
}