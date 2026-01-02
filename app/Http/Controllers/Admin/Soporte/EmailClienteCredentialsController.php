<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Soporte;

use App\Http\Controllers\Controller;
use App\Mail\Admin\ClienteCredentialsMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EmailClienteCredentialsController extends Controller
{
    private string $adminConn = 'mysql_admin';

    public function __construct()
    {
        $this->middleware(['auth:admin']);
    }

    public function send(Request $request, string $accountId): RedirectResponse
    {
        $accountId = Str::of($accountId)->upper()->trim()->value();

        // ✅ validar destinatarios
        $data = validator($request->all(), [
            'to'         => 'required|string|max:5000', // CSV
            'usuario'    => 'nullable|string|max:190',
            'password'   => 'nullable|string|max:190',
            'access_url' => 'nullable|string|max:2000',
            'rfc'        => 'nullable|string|max:60',
            'rs'         => 'nullable|string|max:255',
        ])->validate();

        $emails = $this->normalizeEmails($data['to'] ?? '');
        if (empty($emails)) {
            return back()->with('error', 'No hay correos válidos para enviar credenciales.');
        }

        // ✅ cargar cuenta
        $emailCol = $this->colEmail();
        $phoneCol = $this->colPhone();
        $rfcCol   = $this->colRfcAdmin();

        $select = [
            'id',
            DB::raw("$rfcCol as rfc"),
            'razon_social',
            DB::raw("$emailCol as email"),
            DB::raw("$phoneCol as phone"),
        ];

        $acc = DB::connection($this->adminConn)
            ->table('accounts')
            ->where('id', $accountId)
            ->first($select);

        if (!$acc) {
            return back()->with('error', 'Cuenta no encontrada para enviar credenciales.');
        }

        // ✅ Datos de credencial (prioridad: request -> DB fallback)
        $rfc = trim((string)($data['rfc'] ?? $acc->rfc ?? $acc->id));
        $rs  = trim((string)($data['rs'] ?? $acc->razon_social ?? 'Cliente'));

        $usuario = trim((string)($data['usuario'] ?? ''));
        if ($usuario === '') {
            // En tu modal estabas mostrando RFC como "solicitud/usuario", por defecto usamos RFC.
            $usuario = $rfc !== '' ? $rfc : (string)$acc->id;
        }

        $password = trim((string)($data['password'] ?? ''));
        // password suele venir como OTP o temporal (lo mandamos desde el modal)

        $accessUrl = trim((string)($data['access_url'] ?? ''));
        if ($accessUrl === '') {
            $accessUrl = rtrim((string)config('app.url'), '/') . '/cliente';
        }

        // ✅ Armar payload para el correo
        $payload = [
            'brand' => [
                'name' => 'Pactopia360',
                // ✅ Tu logo real
                'logo_url' => rtrim((string)config('app.url'), '/') . '/assets/brand/pactopia-logo.png',
            ],
            'account' => [
                'id' => (string)$acc->id,
                'rfc' => $rfc,
                'razon_social' => $rs,
            ],
            'credentials' => [
                'usuario' => $usuario,
                'password' => $password,
                'access_url' => $accessUrl,
            ],
            'meta' => [
                'sent_by' => (string)auth('admin')->id(),
                'sent_at' => now()->toDateTimeString(),
            ],
        ];

        $sent = 0;
        $fails = [];

        foreach ($emails as $to) {
            try {
                Mail::to($to)->send(new ClienteCredentialsMail($payload));
                $sent++;
            } catch (\Throwable $e) {
                $fails[] = $to;
                Log::warning('EmailClienteCredentialsController.send mail error: ' . $e->getMessage(), [
                    'accountId' => $acc->id,
                    'to' => $to,
                ]);
            }
        }

        if ($sent <= 0) {
            return back()->with('error', 'No se pudo enviar el correo. Revisa el mailer/logs.');
        }

        $msg = "Credenciales enviadas: {$sent}";
        if (!empty($fails)) {
            $msg .= " · Fallaron: " . implode(', ', array_slice($fails, 0, 5)) . (count($fails) > 5 ? '…' : '');
        }

        return back()->with('ok', $msg);
    }

    private function normalizeEmails(string $input): array
    {
        $s = trim($input);
        if ($s === '') return [];

        $s = str_replace([';', "\n", "\r", "\t"], [',', ',', ',', ' '], $s);
        $parts = array_filter(array_map('trim', explode(',', $s)));

        $out = [];
        foreach ($parts as $p) {
            $e = strtolower(trim((string)$p));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $out[] = $e;
        }
        return array_values(array_unique($out));
    }

    private function colEmail(): string
    {
        foreach (['correo_contacto', 'email'] as $c) {
            if (Schema::connection($this->adminConn)->hasColumn('accounts', $c)) return $c;
        }
        return 'email';
    }

    private function colPhone(): string
    {
        foreach (['telefono', 'phone', 'tel', 'celular'] as $c) {
            if (Schema::connection($this->adminConn)->hasColumn('accounts', $c)) return $c;
        }
        return 'phone';
    }

    private function colRfcAdmin(): string
    {
        foreach (['rfc', 'rfc_padre', 'tax_id', 'rfc_cliente'] as $c) {
            if (Schema::connection($this->adminConn)->hasColumn('accounts', $c)) return $c;
        }
        return 'id';
    }
}
