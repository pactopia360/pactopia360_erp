<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin\Cuenta;
use App\Support\CustomerCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistroController extends Controller
{
    public function showForm(){ return view('admin.auth.register'); }

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

        DB::connection('mysql_admin')->transaction(function () use ($r,$id,$codigo) {
            // 1) Crear cuenta en ADMIN
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

            // 2) Espejo mínimo en CLIENTES (tabla clientes)
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

            // 3) Usuario padre (admin de la cuenta) en CLIENTES
            DB::connection('mysql_clientes')->table('usuarios')->updateOrInsert(
                ['email' => $r->email],
                [
                    'id'           => (string) Str::uuid(),
                    'cuenta_id'    => $id,
                    'nombre'       => $r->razon_social.' (Owner)',
                    'password'     => bcrypt($r->password),
                    'rol'          => 'owner',
                    'email'        => $r->email,
                    'email_verified_at' => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        });

        // 4) Envío de verificación (Mailgun vía notificación estándar)
        // Usa MustVerifyEmail en tu modelo de usuario del admin o envía un mail ad-hoc aquí.
        // (Mantengo breve: la infra de email ya la tienes configurada a nivel mailer)

        return redirect()->route('admin.login')
            ->with('status', 'Cuenta creada. Revisa tu correo para verificar y acceder.');
    }
}
