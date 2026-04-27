<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin\Cuenta;
use App\Support\CustomerCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegistroController extends Controller
{
    public function showForm()
    {
        return view('admin.auth.register');
    }

    public function store(Request $r)
    {
        $r->validate([
            'rfc_padre'     => 'required|alpha_num|size:13|unique:mysql_admin.cuentas,rfc_padre',
            'razon_social'  => 'required|string|max:191',
            'email'         => 'required|email:rfc,dns|unique:mysql_admin.cuentas,email_principal',
            'password'      => 'required|min:8|confirmed',
            'licencia'      => 'in:free,pro',
            'ciclo'         => 'in:mensual,anual',
        ]);

        $id = (string) Str::uuid();
        $codigo = CustomerCode::make($r->rfc_padre);

        DB::connection('mysql_admin')->transaction(function () use ($r, $id, $codigo) {
            Cuenta::on('mysql_admin')->create([
                'id'              => $id,
                'rfc_padre'       => strtoupper($r->rfc_padre),
                'razon_social'    => $r->razon_social,
                'codigo_cliente'  => $codigo,
                'email_principal' => $r->email,
                'plan_id'         => null,
                'estado'          => 'free',
                'timbres'         => 0,
                'espacio_mb'      => $r->licencia === 'pro' ? 51200 : 1024,
                'licencia'        => $r->licencia ?? 'free',
                'ciclo'           => $r->ciclo ?? 'mensual',
                'proximo_corte'   => now()->startOfMonth()->addMonth(),
            ]);

            DB::connection('mysql_clientes')->table('clientes')->updateOrInsert(
                ['id' => $id],
                [
                    'empresa'     => $r->razon_social,
                    'rfc'         => strtoupper($r->rfc_padre),
                    'estado'      => 'activo',
                    'plan'        => $r->licencia ?? 'free',
                    'timbres'     => 0,
                    'baja_at'     => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );

            DB::connection('mysql_clientes')->table('usuarios')->updateOrInsert(
                ['email' => $r->email],
                [
                    'id'                => (string) Str::uuid(),
                    'cuenta_id'         => $id,
                    'nombre'            => $r->razon_social . ' (Owner)',
                    'password'          => bcrypt($r->password),
                    'rol'               => 'owner',
                    'email'             => $r->email,
                    'email_verified_at' => null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
        });

        $this->notifySupportFacturotopia($r, $id, $codigo);

        return redirect()->route('admin.login')
            ->with('status', 'Cuenta creada. Revisa tu correo para verificar y acceder.');
    }

    private function notifySupportFacturotopia(Request $r, string $accountId, string $codigoCliente): void
    {
        try {
            $rfc = strtoupper((string) $r->rfc_padre);
            $licencia = strtoupper((string) ($r->licencia ?? 'free'));
            $ciclo = strtoupper((string) ($r->ciclo ?? 'mensual'));

            $subject = "Alta Facturotopia requerida · {$rfc} · {$licencia}";

            $body = <<<MAIL
Nueva cuenta registrada en Pactopia360.

Se requiere alta/configuración en Facturotopia para habilitar API de timbrado.

DATOS DE CUENTA
ID cuenta Pactopia360: {$accountId}
Código cliente: {$codigoCliente}
RFC: {$rfc}
Razón social: {$r->razon_social}
Email principal: {$r->email}
Licencia solicitada: {$licencia}
Ciclo: {$ciclo}
Fecha registro: {now()->format('Y-m-d H:i:s')}

ACCIONES REQUERIDAS EN FACTUROTOPIA
1. Crear/registrar cliente en Facturotopia.
2. Generar credenciales API de pruebas.
3. Generar credenciales API de producción.
4. Registrar endpoint/base URL, usuario, token/API key y estatus.
5. Capturar paquete de timbres/hits asignado.
6. Guardar estos datos desde Admin Pactopia360.
7. El cliente solo podrá consultarlos en Timbres / Hits, sin editarlos.

IMPORTANTE
No enviar contraseñas del usuario por correo.
No capturar credenciales API desde el módulo cliente.
MAIL;

            Mail::raw($body, function ($message) use ($subject) {
                $message->to('soporte@pactopia.com')
                    ->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar correo de alta Facturotopia desde registro inicial.', [
                'error' => $e->getMessage(),
                'email' => $r->email,
                'rfc' => strtoupper((string) $r->rfc_padre),
            ]);
        }
    }
}