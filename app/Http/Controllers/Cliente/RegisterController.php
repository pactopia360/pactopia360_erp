<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Models\Cliente\UsuarioCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    /* ========================================================
     * Formularios
     * ======================================================== */
    public function showFree()
    {
        return view('cliente.auth.register_free');
    }

    public function showPro()
    {
        $priceMonthly = config('services.stripe.display_price_monthly', 990.00);
        $priceAnnual  = config('services.stripe.display_price_annual', 9990.00);

        return view('cliente.auth.register_pro', [
            'price_monthly' => $priceMonthly,
            'price_annual'  => $priceAnnual,
        ]);
    }

    /* ========================================================
     * Registro FREE
     * ======================================================== */
    public function storeFree(Request $req)
    {
        $this->validateFree($req);

        $email = strtolower(trim($req->email));
        $rfc   = $this->normalizeRfc($req->rfc);
        $tel   = $this->normalizePhone($req->telefono);
        $name  = trim((string)$req->nombre);

        // Duplicados amigables
        if ($this->rfcExiste($rfc)) {
            return back()->withErrors([
                'rfc' => 'Este RFC ya fue registrado previamente. Intenta con otro o contáctanos a soporte@pactopia.com',
            ])->withInput();
        }
        if ($this->emailExiste($email)) {
            return back()->withErrors([
                'email' => 'Este correo ya está en uso. Usa otro o recupera tu contraseña.',
            ])->withInput();
        }

        // Contraseña temporal (se guarda hasheada por el cast del modelo)
        $tempPassword = $this->generateTempPassword(12);

        DB::connection('mysql_admin')->beginTransaction();
        DB::connection('mysql_clientes')->beginTransaction();

        try {
            // 1) ADMIN
            $adminAccountId = DB::connection('mysql_admin')
                ->table('accounts')
                ->insertGetId($this->buildAdminAccountInsert([
                    'nombre'            => $name,
                    'email'             => $email,
                    'telefono'          => $tel,
                    'rfc'               => $rfc,
                    'plan'              => 'FREE',
                    'plan_actual'       => 'FREE',
                    'modo_cobro'        => 'free',
                    'is_blocked'        => 0,
                    'estado_cuenta'     => 'pendiente',
                    'email_verified_at' => null,
                    'phone_verified_at' => null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]));

            // 2) CLIENTES: cuenta
            $cuenta = new CuentaCliente();
            $cuenta->id             = (string) Str::uuid();
            $cuenta->codigo_cliente = $this->makeCodigoCliente();

            if ($this->cliHas('cuentas_cliente', 'customer_no')) {
                $maxLen = $this->cliVarcharLen('cuentas_cliente', 'customer_no') ?? 20;
                $cuenta->customer_no = $this->makeCustomerNo($maxLen);
            }

            $cuenta->rfc_padre     = $rfc;
            $cuenta->razon_social  = $name;
            $cuenta->plan_actual   = 'FREE';
            $cuenta->modo_cobro    = 'free';
            $cuenta->estado_cuenta = 'pendiente';

            if ($this->cliHas('cuentas_cliente', 'espacio_asignado_mb')) $cuenta->espacio_asignado_mb = 512;
            if ($this->cliHas('cuentas_cliente', 'hits_asignados'))      $cuenta->hits_asignados      = 5;
            if ($this->cliHas('cuentas_cliente', 'max_usuarios'))        $cuenta->max_usuarios        = 1;
            if ($this->cliHas('cuentas_cliente', 'max_empresas'))        $cuenta->max_empresas        = 9999;
            if ($this->cliHas('cuentas_cliente', 'max_mass_invoices_per_day')) {
                $cuenta->max_mass_invoices_per_day = 0;
                $cuenta->mass_invoices_used_today  = 0;
                $cuenta->mass_invoices_reset_at    = now()->startOfDay()->addDay();
            }
            if ($this->cliHas('cuentas_cliente', 'admin_account_id'))    $cuenta->admin_account_id    = $adminAccountId;

            $cuenta->setConnection('mysql_clientes');
            $cuenta->save();

            // 3) Usuario owner (inactivo hasta 2FA)
            $usuario = new UsuarioCuenta();
            $usuario->id        = (string) Str::uuid();
            $usuario->cuenta_id = $cuenta->id;
            $usuario->tipo      = 'owner';
            $usuario->rol       = 'owner';
            $usuario->nombre    = $name;
            $usuario->email     = $email;
            $usuario->password  = Hash::make($tempPassword);
            $usuario->password_temp  = \Illuminate\Support\Facades\Hash::make($tempPassword);
            $usuario->activo         = 0;
            if ($this->cliHas('usuarios_cuenta', 'must_change_password')) $usuario->must_change_password = 1;
            if ($this->cliHas('usuarios_cuenta', 'password_plain')) $usuario->password_plain = null;

            $usuario->setConnection('mysql_clientes');
            $usuario->save();

            // 4) CRM Carrito (dinámico)
            $this->insertCarrito(
                estado: 'oportunidad',
                titulo: "Registro FREE de {$rfc}",
                total: 0.0,
                cliente: $name,
                email: $email,
                telefono: $tel
            );

            DB::connection('mysql_clientes')->commit();
            DB::connection('mysql_admin')->commit();

            // 5) Correos (en cola si MAIL_QUEUE=true)
            $token = $this->createEmailVerificationToken($adminAccountId, $email);
            $this->sendEmailVerification($email, $token, $name);
            $this->sendCredentialsEmail($email, $name, $rfc, $tempPassword, false);

            return redirect()
                ->route('cliente.registro.free')
                ->with('popup_ok', '¡Felicidades! Te enviamos el enlace de verificación y tus credenciales.')
                ->with('clear_form', true);
        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();
            DB::connection('mysql_admin')->rollBack();
            Log::error('Error en registro FREE', ['error' => $e->getMessage()]);
            return back()->withErrors(['general' => 'Ocurrió un error al registrar. Intenta nuevamente.'])
                         ->withInput();
        }
    }

    /* ========================================================
     * Registro PRO
     * ======================================================== */
    public function storePro(Request $req)
    {
        $this->validatePro($req);

        $email = strtolower(trim($req->email));
        $rfc   = $this->normalizeRfc($req->rfc);
        $tel   = $this->normalizePhone($req->telefono);
        $name  = trim((string)$req->nombre);
        $modo  = ($req->plan === 'anual') ? 'anual' : 'mensual';

        if ($this->rfcExiste($rfc)) {
            return back()->withErrors([
                'rfc' => 'Este RFC ya fue registrado previamente. Intenta con otro o contáctanos a soporte@pactopia.com',
            ])->withInput();
        }
        if ($this->emailExiste($email)) {
            return back()->withErrors([
                'email' => 'Este correo ya está en uso. Usa otro o recupera tu contraseña.',
            ])->withInput();
        }

        $tempPassword = $this->generateTempPassword(12);

        DB::connection('mysql_admin')->beginTransaction();
        DB::connection('mysql_clientes')->beginTransaction();

        try {
            // 1) ADMIN
            $adminAccountId = DB::connection('mysql_admin')
                ->table('accounts')
                ->insertGetId($this->buildAdminAccountInsert([
                    'nombre'            => $name,
                    'email'             => $email,
                    'telefono'          => $tel,
                    'rfc'               => $rfc,
                    'plan'              => 'PRO',
                    'plan_actual'       => 'PRO',
                    'modo_cobro'        => $modo,
                    'is_blocked'        => 1,
                    'estado_cuenta'     => 'bloqueada_pago',
                    'email_verified_at' => null,
                    'phone_verified_at' => null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]));

            // 2) CLIENTES
            $cuenta = new CuentaCliente();
            $cuenta->id             = (string) Str::uuid();
            $cuenta->codigo_cliente = $this->makeCodigoCliente();

            if ($this->cliHas('cuentas_cliente', 'customer_no')) {
                $maxLen = $this->cliVarcharLen('cuentas_cliente', 'customer_no') ?? 20;
                $cuenta->customer_no = $this->makeCustomerNo($maxLen);
            }

            $cuenta->rfc_padre     = $rfc;
            $cuenta->razon_social  = $name;
            $cuenta->plan_actual   = 'PRO';
            $cuenta->modo_cobro    = $modo;
            $cuenta->estado_cuenta = 'bloqueada_pago';

            if ($this->cliHas('cuentas_cliente', 'max_mass_invoices_per_day')) {
                $cuenta->max_mass_invoices_per_day = 100;
                $cuenta->mass_invoices_used_today  = 0;
                $cuenta->mass_invoices_reset_at    = now()->startOfDay()->addDay();
            }
            if ($this->cliHas('cuentas_cliente', 'max_usuarios'))        $cuenta->max_usuarios        = 10;
            if ($this->cliHas('cuentas_cliente', 'max_empresas'))        $cuenta->max_empresas        = 9999;
            if ($this->cliHas('cuentas_cliente', 'espacio_asignado_mb')) $cuenta->espacio_asignado_mb = 15360;
            if ($this->cliHas('cuentas_cliente', 'admin_account_id'))    $cuenta->admin_account_id    = $adminAccountId;

            $cuenta->setConnection('mysql_clientes');
            $cuenta->save();

            // 3) Usuario owner (inactivo)
            $usuario = new UsuarioCuenta();
            $usuario->id        = (string) Str::uuid();
            $usuario->cuenta_id = $cuenta->id;
            $usuario->tipo      = 'owner';
            $usuario->rol       = 'owner';
            $usuario->nombre    = $name;
            $usuario->email     = $email;
            $usuario->password  = Hash::make($tempPassword);
            $usuario->password_temp  = \Illuminate\Support\Facades\Hash::make($tempPassword);
            $usuario->activo         = 0;
            if ($this->cliHas('usuarios_cuenta', 'must_change_password')) $usuario->must_change_password = 1;
            if ($this->cliHas('usuarios_cuenta', 'password_plain')) $usuario->password_plain = null;

            $usuario->setConnection('mysql_clientes');
            $usuario->save();

            // 4) CRM Carrito
            $priceMonthly = config('services.stripe.display_price_monthly', 990.00);
            $priceAnnual  = config('services.stripe.display_price_annual', 9990.00);
            $total        = $req->plan === 'mensual' ? $priceMonthly : $priceAnnual;

            $this->insertCarrito(
                estado: 'oportunidad',
                titulo: "Registro PRO de {$rfc} ({$req->plan})",
                total: (float) $total,
                cliente: $name,
                email: $email,
                telefono: $tel
            );

            DB::connection('mysql_clientes')->commit();
            DB::connection('mysql_admin')->commit();

            // 5) Credenciales e instrucciones de pago
            $this->sendCredentialsEmail($email, $name, $rfc, $tempPassword, true);

            return redirect()
                ->route('cliente.registro.pro')
                ->with('ok', 'Cuenta creada. Continúa con el pago para activar tu plan PRO.')
                ->with('popup_ok', 'Te enviamos credenciales y los pasos para completar el pago.')
                ->with('clear_form', true)
                ->with('checkout_ready', true)
                ->with('checkout_plan', $req->plan)
                ->with('account_id', $adminAccountId)
                ->withInput([
                    'email'    => $email,
                    'rfc'      => $rfc,
                    'telefono' => $tel,
                ]);
        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();
            DB::connection('mysql_admin')->rollBack();
            Log::error('Error en registro PRO', ['error' => $e->getMessage()]);
            return back()->withErrors(['general' => 'Ocurrió un error al registrar PRO. Intenta nuevamente.'])
                         ->withInput();
        }
    }

    /* ===================== Validaciones y helpers ===================== */

    private function validateFree(Request $req): void
    {
        $captchaRule = $this->captchaRule();

        $req->validate([
            'nombre'   => ['required','string','min:3','max:150'],
            'email'    => ['required','email:rfc,dns','max:150'],
            'rfc'      => ['required','string','max:20', function($attr,$val,$fail){
                $rfc = $this->normalizeRfc($val);
                if (!preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
                    $fail('RFC inválido. Revisa formato (13 caracteres para moral, 12 para física).');
                }
            }],
            'telefono' => ['required','string','max:25', function($attr,$val,$fail){
                $tel = $this->normalizePhone($val);
                if (!preg_match('/^\+?[0-9\s\-]{8,20}$/', $tel)) {
                    $fail('Teléfono inválido.');
                }
            }],
            'terms'    => ['accepted'],
            'g-recaptcha-response' => $captchaRule,
        ],[
            'terms.accepted' => 'Debes aceptar los términos y condiciones para continuar.',
            'g-recaptcha-response.required' => 'Completa el captcha para continuar.',
        ]);
    }

    private function validatePro(Request $req): void
    {
        $captchaRule = $this->captchaRule();

        $req->validate([
            'nombre'   => ['required','string','min:3','max:150'],
            'email'    => ['required','email:rfc,dns','max:150'],
            'rfc'      => ['required','string','max:20', function($attr,$val,$fail){
                $rfc = $this->normalizeRfc($val);
                if (!preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
                    $fail('RFC inválido. Revisa formato.');
                }
            }],
            'telefono' => ['required','string','max:25', function($attr,$val,$fail){
                $tel = $this->normalizePhone($val);
                if (!preg_match('/^\+?[0-9\s\-]{8,20}$/', $tel)) {
                    $fail('Teléfono inválido.');
                }
            }],
            'terms'    => ['accepted'],
            'plan'     => ['required', Rule::in(['mensual','anual'])],
            'g-recaptcha-response' => $captchaRule,
        ]);
    }

    private function normalizeRfc(string $rfc): string
    {
        $rfc = strtoupper(trim($rfc));
        $rfc = preg_replace('/\s+/', '', $rfc);
        return $rfc;
    }

    private function normalizePhone(string $tel): string
    {
        $tel = trim($tel);
        $tel = preg_replace('/[^\d\+\-\s]/', '', $tel);
        return preg_replace('/\s+/', ' ', $tel);
    }

    private function captchaRule()
    {
        $enabled = (bool) (config('services.recaptcha.enabled') ?? env('RECAPTCHA_ENABLED', false));
        return $enabled ? ['required'] : ['nullable'];
    }

    private function rfcExiste(string $rfc): bool
    {
        $existsAdmin   = DB::connection('mysql_admin')->table('accounts')->whereRaw('UPPER(COALESCE(rfc,"")) = ?', [strtoupper($rfc)])->exists();
        $existsCliente = DB::connection('mysql_clientes')->table('cuentas_cliente')->whereRaw('UPPER(COALESCE(rfc_padre,"")) = ?', [strtoupper($rfc)])->exists();
        return $existsAdmin || $existsCliente;
    }

    private function emailExiste(string $email): bool
    {
        $emailCol = $this->adminEmailColumn();
        $existsAdmin   = DB::connection('mysql_admin')->table('accounts')->where($emailCol, $email)->exists();
        $existsCliente = DB::connection('mysql_clientes')->table('usuarios_cuenta')->where('email', $email)->exists();
        return $existsAdmin || $existsCliente;
    }

    private function insertCarrito(string $estado, string $titulo, float $total, string $cliente, string $email, string $telefono): void
    {
        $table = 'crm_carritos';
        try {
            if (!Schema::connection('mysql_admin')->hasTable($table)) {
                Log::warning('crm_carritos no existe; se omite inserción.');
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo verificar tabla crm_carritos', ['e' => $e->getMessage()]);
            return;
        }

        try {
            $columns = Schema::connection('mysql_admin')->getColumnListing($table);
        } catch (\Throwable $e) {
            Log::warning('No se pudo listar columnas de crm_carritos', ['e' => $e->getMessage()]);
            return;
        }

        $map = [
            'titulo'     => $this->firstColumn($columns, ['titulo','title','subject','name','descripcion','detalle']),
            'estado'     => $this->firstColumn($columns, ['estado','status','state','fase']),
            'total'      => $this->firstColumn($columns, ['total','monto','amount','importe']),
            'moneda'     => $this->firstColumn($columns, ['moneda','currency','divisa']),
            'cliente'    => $this->firstColumn($columns, ['cliente','customer','razon_social','nombre_cliente','contacto']),
            'email'      => $this->firstColumn($columns, ['email','correo','correo_contacto']),
            'telefono'   => $this->firstColumn($columns, ['telefono','phone','telefono_contacto']),
            'origen'     => $this->firstColumn($columns, ['origen','source','canal']),
            'created_at' => $this->firstColumn($columns, ['created_at','creado_en','fecha_creacion']),
            'updated_at' => $this->firstColumn($columns, ['updated_at','actualizado_en','fecha_actualizacion']),
        ];

        $row = [];
        if ($map['titulo'])   $row[$map['titulo']]   = $titulo;
        if ($map['estado'])   $row[$map['estado']]   = $estado;
        if ($map['total'])    $row[$map['total']]    = $total;
        if ($map['moneda'])   $row[$map['moneda']]   = 'MXN';
        if ($map['cliente'])  $row[$map['cliente']]  = $cliente;
        if ($map['email'])    $row[$map['email']]    = $email;
        if ($map['telefono']) $row[$map['telefono']] = $telefono;
        if ($map['origen'])   $row[$map['origen']]   = 'registro_web';

        $now = now();
        if ($map['created_at']) $row[$map['created_at']] = $now;
        if ($map['updated_at']) $row[$map['updated_at']] = $now;

        if (($map['titulo'] && isset($row[$map['titulo']])) || ($map['estado'] && isset($row[$map['estado']]))) {
            try {
                DB::connection('mysql_admin')->table($table)->insert($row);
            } catch (\Throwable $e) {
                Log::error('No se pudo insertar en crm_carritos', ['e' => $e->getMessage(), 'row' => $row]);
            }
        } else {
            Log::warning('crm_carritos: no hubo columnas mínimas para insertar.', ['row' => $row, 'columns' => $columns]);
        }
    }

    private function firstColumn(array $columns, array $options): ?string
    {
        $lc = array_map('strtolower', $columns);
        foreach ($options as $opt) {
            if (in_array(strtolower($opt), $lc, true)) return $opt;
        }
        return null;
    }

    private function createEmailVerificationToken(int $accountId, string $email): string
    {
        $token = Str::random(40);

        DB::connection('mysql_admin')->table('email_verifications')->insert([
            'account_id' => $accountId,
            'email'      => $email,
            'token'      => $token,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    /* ======== Envío de correos (cola opcional) ======== */

    private function sendEmailVerification(string $email, string $token, string $nombre): void
    {
        $url  = route('cliente.verify.email.token', ['token' => $token]);
        $body = "Hola {$nombre},\n\n".
                "Confirma tu correo haciendo clic en el siguiente enlace (válido 24 h):\n{$url}\n\n".
                "Después te pediremos verificar tu teléfono.\n\n".
                "Si no fuiste tú, ignora este correo.";

        $this->mailRawQueued($email, 'Confirma tu correo - Pactopia360', $body);
    }

    private function sendCredentialsEmail(string $email, string $nombre, string $rfc, string $plainPassword, bool $isPro): void
    {
        $loginUrl = route('cliente.login');

        $extra = $isPro
            ? "Tu plan PRO requiere completar el pago para activarse. Una vez confirmado, podrás acceder normalmente.\n"
            : "Recuerda verificar tu correo desde el enlace que te enviamos para completar la activación.\n";

        $body = "Hola {$nombre},\n\n".
                "¡Tu cuenta en Pactopia360 fue creada correctamente!\n\n".
                "Puedes iniciar sesión con:\n".
                "• Correo: {$email}\n".
                "• RFC: {$rfc}\n".
                "• Contraseña temporal: {$plainPassword}\n\n".
                "Inicia sesión aquí: {$loginUrl}\n\n".
                "{$extra}".
                "Por seguridad, te pediremos cambiar la contraseña en tu primer acceso.\n\n".
                "— Equipo Pactopia360";

        $this->mailRawQueued($email, $isPro ? 'Tus credenciales PRO - Pactopia360' : 'Tus credenciales FREE - Pactopia360', $body);
    }

    private function mailRawQueued(string $to, string $subject, string $body): void
    {
        $useQueue = (bool) env('MAIL_QUEUE', false);

        if ($useQueue) {
            Bus::dispatch(function () use ($to, $subject, $body) {
                Mail::raw($body, function ($m) use ($to, $subject) {
                    $m->to($to)->subject($subject);
                });
            })->onQueue('default');
        } else {
            try {
                Mail::raw($body, function ($m) use ($to, $subject) {
                    $m->to($to)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::error('Fallo envío de correo', ['to' => $to, 'subject' => $subject, 'e' => $e->getMessage()]);
            }
        }
    }

    private function makeCodigoCliente(): string
    {
        return 'C'.strtoupper(Str::random(6));
    }

    private function makeCustomerNo(int $maxLen = 12): string
    {
        $base    = date('ymd') . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $numeric = preg_replace('/\D+/', '', $base);
        return substr($numeric, 0, max(4, $maxLen));
    }

    private function generateTempPassword(int $length = 12): string
    {
        $length = max(8, min(48, $length));
        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '.,;:!?@#$%&*+-_=',
        ];
        $all = implode('', $sets);
        $pwd = '';
        foreach ($sets as $set) { $pwd .= $set[random_int(0, strlen($set) - 1)]; }
        for ($i = strlen($pwd); $i < $length; $i++) { $pwd .= $all[random_int(0, strlen($all) - 1)]; }
        return str_shuffle($pwd);
    }

    /* ========================== Helpers de esquema (admin) ========================== */

    private function adminHas(string $col): bool
    {
        try { return Schema::connection('mysql_admin')->hasColumn('accounts', $col); }
        catch (\Throwable $e) { return false; }
    }

    private function adminEmailColumn(): string
    {
        foreach (['correo_contacto','email'] as $c) if ($this->adminHas($c)) return $c;
        return 'email';
    }

    private function adminPhoneColumn(): string
    {
        foreach (['telefono','phone'] as $c) if ($this->adminHas($c)) return $c;
        return 'telefono';
    }

    private function adminNameColumn(): string
    {
        foreach (['name','razon_social','nombre','nombre_cuenta'] as $c) if ($this->adminHas($c)) return $c;
        return 'name';
    }

    private function buildAdminAccountInsert(array $data): array
    {
        $insert = [];

        $nameCol = $this->adminNameColumn();
        $insert[$nameCol] = $data['nombre'];

        $emailCol = $this->adminEmailColumn();
        $phoneCol = $this->adminPhoneColumn();
        $insert[$emailCol] = $data['email'];
        $insert[$phoneCol] = $data['telefono'];

        if ($this->adminHas('rfc'))            $insert['rfc'] = $data['rfc'];
        if ($this->adminHas('plan'))           $insert['plan'] = $data['plan'];
        if ($this->adminHas('plan_actual'))    $insert['plan_actual'] = $data['plan_actual'];
        if ($this->adminHas('modo_cobro'))     $insert['modo_cobro'] = $data['modo_cobro'];
        if ($this->adminHas('is_blocked'))     $insert['is_blocked'] = $data['is_blocked'];

        if ($this->adminHas('estado_cuenta'))  $insert['estado_cuenta'] = $data['estado_cuenta'];
        elseif ($this->adminHas('status'))     $insert['status'] = $data['estado_cuenta'];

        if ($this->adminHas('email_verified_at')) $insert['email_verified_at'] = $data['email_verified_at'];
        if ($this->adminHas('phone_verified_at')) $insert['phone_verified_at'] = $data['phone_verified_at'];
        if ($this->adminHas('created_at'))        $insert['created_at']        = $data['created_at'];
        if ($this->adminHas('updated_at'))        $insert['updated_at']        = $data['updated_at'];

        return $insert;
    }

    /* ========================== Helpers de esquema (clientes) ========================== */

    private function cliHas(string $tabla, string $col): bool
    {
        try { return Schema::connection('mysql_clientes')->hasColumn($tabla, $col); }
        catch (\Throwable $e) { return false; }
    }

    private function cliVarcharLen(string $tabla, string $col): ?int
    {
        try {
            $conn = DB::connection('mysql_clientes');
            $db   = $conn->getDatabaseName();

            $row = $conn->table('information_schema.columns')
                ->select('DATA_TYPE','CHARACTER_MAXIMUM_LENGTH','NUMERIC_PRECISION')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $tabla)
                ->where('COLUMN_NAME', $col)
                ->first();

            if (!$row) return null;

            $dataType = strtolower($row->DATA_TYPE ?? '');
            if (in_array($dataType, ['varchar','char','text'])) {
                return (int) ($row->CHARACTER_MAXIMUM_LENGTH ?? 255);
            }

            if (in_array($dataType, ['int','bigint','mediumint','smallint','tinyint','decimal','numeric'])) {
                $prec = (int) ($row->NUMERIC_PRECISION ?? 10);
                return max(4, min(20, $prec));
            }

            return 20;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
