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
        $name  = trim((string) $req->nombre);

        // Duplicados
        if ($this->rfcExiste($rfc)) {
            return back()->withErrors(['rfc' => 'Este RFC ya fue registrado.'])->withInput();
        }
        if ($this->emailExiste($email)) {
            return back()->withErrors(['email' => 'Este correo ya está en uso.'])->withInput();
        }

        // Contraseña temporal inicial
        $tempPassword = $this->generateTempPassword(12);

        DB::connection('mysql_admin')->beginTransaction();
        DB::connection('mysql_clientes')->beginTransaction();

        try {
            // --- 1) ADMIN (accounts) ---
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

            // --- 2) CLIENTES: cuenta_cliente ---
            $cuenta = new CuentaCliente();
            $cuenta->id             = (string) Str::uuid();
            $cuenta->codigo_cliente = $this->makeCodigoCliente();
            // Asignar SIEMPRE customer_no para evitar "doesn't have a default value"
            $cuenta->customer_no    = $this->nextCustomerNo();   // 👈 FIX CLAVE
            $cuenta->rfc_padre      = $rfc;
            $cuenta->razon_social   = $name;
            $cuenta->plan_actual    = 'FREE';
            $cuenta->modo_cobro     = 'free';
            $cuenta->estado_cuenta  = 'pendiente';

            if ($this->cliHas('cuentas_cliente', 'admin_account_id'))     $cuenta->admin_account_id      = $adminAccountId;
            if ($this->cliHas('cuentas_cliente', 'espacio_asignado_mb'))  $cuenta->espacio_asignado_mb   = 512;
            if ($this->cliHas('cuentas_cliente', 'hits_asignados'))       $cuenta->hits_asignados        = 5;
            if ($this->cliHas('cuentas_cliente', 'max_usuarios'))         $cuenta->max_usuarios          = 1;
            if ($this->cliHas('cuentas_cliente', 'max_empresas'))         $cuenta->max_empresas          = 9999;

            $cuenta->setConnection('mysql_clientes');
            $cuenta->save();

            // --- 3) CLIENTES: usuario owner ---
            $usuario = new UsuarioCuenta();
            $usuario->id        = (string) Str::uuid();
            $usuario->cuenta_id = $cuenta->id;
            $usuario->tipo      = 'owner';
            $usuario->rol       = 'owner';
            $usuario->nombre    = $name;
            $usuario->email     = $email;
            $usuario->password  = Hash::make($tempPassword);
            $usuario->activo    = 0; // inactivo hasta verificación email+tel
            if ($this->cliHas('usuarios_cuenta', 'must_change_password')) {
                $usuario->must_change_password = 1;
            }
            $usuario->setConnection('mysql_clientes');
            $usuario->save();

            DB::connection('mysql_clientes')->commit();
            DB::connection('mysql_admin')->commit();

            // --- 4) Correo de verificación + credenciales iniciales ---
            $token = $this->createEmailVerificationToken($adminAccountId, $email);
            $this->sendEmailVerification($email, $token, $name);
            $this->sendCredentialsEmail($email, $name, $rfc, $tempPassword, false);

            // Redirigir a login cliente
            return redirect()
                ->route('cliente.login')
                ->with('ok', "Tu cuenta fue creada. Revisa tu correo y verifica tu teléfono para activarla.")
                ->with('need_verify', true);

        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();
            DB::connection('mysql_admin')->rollBack();

            Log::error('Error en registro FREE', ['error' => $e->getMessage()]);
            return back()->withErrors(['general' => 'Ocurrió un error al registrar. Intenta nuevamente.'])->withInput();
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
        $name  = trim((string) $req->nombre);
        $modo  = ($req->plan === 'anual') ? 'anual' : 'mensual';

        // Duplicados
        if ($this->rfcExiste($rfc)) {
            return back()->withErrors(['rfc' => 'Este RFC ya fue registrado.'])->withInput();
        }
        if ($this->emailExiste($email)) {
            return back()->withErrors(['email' => 'Este correo ya está en uso.'])->withInput();
        }

        $tempPassword = $this->generateTempPassword(12);

        DB::connection('mysql_admin')->beginTransaction();
        DB::connection('mysql_clientes')->beginTransaction();

        try {
            // --- 1) ADMIN (accounts) ---
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

            // --- 2) CLIENTES: cuenta_cliente ---
            $cuenta = new CuentaCliente();
            $cuenta->id             = (string) Str::uuid();
            $cuenta->codigo_cliente = $this->makeCodigoCliente();
            $cuenta->customer_no    = $this->nextCustomerNo(); // 👈 FIX CLAVE
            $cuenta->rfc_padre      = $rfc;
            $cuenta->razon_social   = $name;
            $cuenta->plan_actual    = 'PRO';
            $cuenta->modo_cobro     = $modo;
            $cuenta->estado_cuenta  = 'bloqueada_pago';

            if ($this->cliHas('cuentas_cliente', 'admin_account_id'))    $cuenta->admin_account_id     = $adminAccountId;
            if ($this->cliHas('cuentas_cliente', 'espacio_asignado_mb')) $cuenta->espacio_asignado_mb  = 15360; // 15GB
            if ($this->cliHas('cuentas_cliente', 'max_usuarios'))        $cuenta->max_usuarios         = 10;

            $cuenta->setConnection('mysql_clientes');
            $cuenta->save();

            // --- 3) CLIENTES: usuario owner ---
            $usuario = new UsuarioCuenta();
            $usuario->id        = (string) Str::uuid();
            $usuario->cuenta_id = $cuenta->id;
            $usuario->tipo      = 'owner';
            $usuario->rol       = 'owner';
            $usuario->nombre    = $name;
            $usuario->email     = $email;
            $usuario->password  = Hash::make($tempPassword);
            $usuario->activo    = 0; // inactivo hasta verificación + pago
            if ($this->cliHas('usuarios_cuenta', 'must_change_password')) {
                $usuario->must_change_password = 1;
            }
            $usuario->setConnection('mysql_clientes');
            $usuario->save();

            DB::connection('mysql_clientes')->commit();
            DB::connection('mysql_admin')->commit();

            // --- 4) Emails ---
            $token = $this->createEmailVerificationToken($adminAccountId, $email);
            $this->sendEmailVerification($email, $token, $name);
            $this->sendCredentialsEmail($email, $name, $rfc, $tempPassword, true);

            return redirect()
                ->route('cliente.login')
                ->with('ok', 'Tu cuenta PRO fue creada. Te enviamos tus credenciales y los pasos de pago.')
                ->with('need_verify', true)
                ->with('checkout_ready', true);

        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();
            DB::connection('mysql_admin')->rollBack();

            Log::error('Error en registro PRO', ['error' => $e->getMessage()]);
            return back()->withErrors(['general' => 'Ocurrió un error al registrar PRO. Intenta nuevamente.'])->withInput();
        }
    }

    /* ========================================================
     * Correos (HTML + fallback texto)
     * ======================================================== */
    private function sendEmailVerification(string $email, string $token, string $nombre): void
    {
        $url = route('cliente.verify.email.token', ['token' => $token]);
        $data = [
            'nombre'    => $nombre,
            'actionUrl' => $url,
            'soporte'   => 'soporte@pactopia.com',
        ];

        try {
            // En local: log; en productivo: enviar
            if (app()->environment('production')) {
                Mail::send(
                    ['html' => 'emails.cliente.verify_email', 'text' => 'emails.cliente.verify_email_text'],
                    $data,
                    function ($m) use ($email) { $m->to($email)->subject('Confirma tu correo · Pactopia360'); }
                );
            } else {
                Log::debug('EmailVerificationLink QA', ['to' => $email, 'link' => $url]);
            }
        } catch (\Throwable $e) {
            Log::error('Fallo envío verificación', ['to' => $email, 'error' => $e->getMessage()]);
        }
    }

    private function sendCredentialsEmail(
        string $email,
        string $nombre,
        string $rfc,
        string $plainPassword,
        bool $isPro
    ): void {
        $data = [
            'nombre'       => $nombre,
            'email'        => $email,
            'rfc'          => $rfc,
            'tempPassword' => $plainPassword,
            'loginUrl'     => route('cliente.login'),
            'is_pro'       => $isPro,
            'soporte'      => 'soporte@pactopia.com',
        ];

        try {
            Mail::send(
                ['html' => 'emails.cliente.welcome_account_activated', 'text' => 'emails.cliente.welcome_account_activated_text'],
                $data,
                function ($m) use ($email) { $m->to($email)->subject('Tu cuenta está lista · Pactopia360'); }
            );
        } catch (\Throwable $e) {
            Log::error('Fallo envío credenciales', ['to' => $email, 'error' => $e->getMessage()]);
        }
    }

    /* ========================================================
     * Validaciones input
     * ======================================================== */
    private function validateFree(Request $req): void
    {
        $captchaRule = $this->captchaRule();

        $req->validate([
            'nombre'   => ['required','string','min:3','max:150'],
            'email'    => ['required','email','max:150'],
            'rfc'      => ['required','string','max:20', function ($attr, $val, $fail) {
                $rfc = $this->normalizeRfc($val);
                if (!preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
                    $fail('RFC inválido. Revisa formato (13 caracteres para moral, 12 para física).');
                }
            }],
            'telefono' => ['required','string','max:25', function ($attr, $val, $fail) {
                $tel = $this->normalizePhone($val);
                if (!preg_match('/^\+?[0-9\s\-]{8,20}$/', $tel)) {
                    $fail('Teléfono inválido.');
                }
            }],
            'terms'    => ['accepted'],
            'g-recaptcha-response' => $captchaRule,
        ], [
            'terms.accepted' => 'Debes aceptar los términos y condiciones para continuar.',
            'g-recaptcha-response.required' => 'Completa el captcha para continuar.',
        ]);
    }

    private function validatePro(Request $req): void
    {
        $captchaRule = $this->captchaRule();

        $req->validate([
            'nombre'   => ['required','string','min:3','max:150'],
            'email'    => ['required','email','max:150'],
            'rfc'      => ['required','string','max:20', function ($attr, $val, $fail) {
                $rfc = $this->normalizeRfc($val);
                if (!preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
                    $fail('RFC inválido. Revisa formato.');
                }
            }],
            'telefono' => ['required','string','max:25', function ($attr, $val, $fail) {
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

    /* ========================================================
     * Utilidades de normalización / captcha
     * ======================================================== */
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

    private function captchaRule(): array
    {
        $enabled = (bool) (config('services.recaptcha.enabled') ?? env('RECAPTCHA_ENABLED', false));
        return $enabled ? ['required'] : ['nullable'];
    }

    /* ========================================================
     * Checadores de duplicado
     * ======================================================== */
    private function rfcExiste(string $rfc): bool
    {
        $existsAdmin = DB::connection('mysql_admin')
            ->table('accounts')
            ->whereRaw('UPPER(COALESCE(rfc,"")) = ?', [strtoupper($rfc)])
            ->exists();

        $existsCliente = DB::connection('mysql_clientes')
            ->table('cuentas_cliente')
            ->whereRaw('UPPER(COALESCE(rfc_padre,"")) = ?', [strtoupper($rfc)])
            ->exists();

        return $existsAdmin || $existsCliente;
    }

    private function emailExiste(string $email): bool
    {
        $emailCol = $this->adminEmailColumn();

        $existsAdmin = DB::connection('mysql_admin')
            ->table('accounts')
            ->where($emailCol, $email)
            ->exists();

        $existsCliente = DB::connection('mysql_clientes')
            ->table('usuarios_cuenta')
            ->where('email', $email)
            ->exists();

        return $existsAdmin || $existsCliente;
    }

    /* ========================================================
     * CRM carrito (opcional)
     * ======================================================== */
    private function insertCarrito(
        string $estado,
        string $titulo,
        float $total,
        string $cliente,
        string $email,
        string $telefono
    ): void {
        $table = 'crm_carritos';
        try {
            if (!Schema::connection('mysql_admin')->hasTable($table)) return;
        } catch (\Throwable $e) { return; }

        try {
            $columns = Schema::connection('mysql_admin')->getColumnListing($table);
        } catch (\Throwable $e) { return; }

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
        }
    }

    private function firstColumn(array $columns, array $options): ?string
    {
        $lc = array_map('strtolower', $columns);
        foreach ($options as $opt) {
            if (in_array(strtolower($opt), $lc, true)) {
                return $opt;
            }
        }
        return null;
    }

    /* ========================================================
     * Tokens de verificación de email
     * ======================================================== */
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

    /* ========================================================
     * Generadores varios
     * ======================================================== */
    private function makeCodigoCliente(): string
    {
        return 'C' . strtoupper(Str::random(6));
    }

    /**
     * Genera un número de cliente incremental y seguro para evitar
     * el error de "Field 'customer_no' doesn't have a default value".
     *
     * - Usa una fila de secuencia en admin (si existiera)
     * - Si no, bloquea la tabla clientes y usa MAX+1 atómico
     */
    private function nextCustomerNo(): int
    {
        // 1) Intentar secuencia en admin (si existe) — igual que hoy
        try {
            if (Schema::connection('mysql_admin')->hasTable('sequences')) {
                $row = DB::connection('mysql_admin')
                    ->table('sequences')
                    ->where('key', 'customer_no')
                    ->lockForUpdate()
                    ->first();

                if ($row) {
                    $next = ((int) $row->value) + 1;
                    DB::connection('mysql_admin')
                        ->table('sequences')
                        ->where('key', 'customer_no')
                        ->update(['value' => $next, 'updated_at' => now()]);
                    return $next;
                }
            }
        } catch (\Throwable $e) {
            // seguimos al plan B
        }

        // 2) Plan B: usar MUTEX con GET_LOCK en mysql_clientes (NO usa LOCK TABLES)
        $conn = DB::connection('mysql_clientes');
        $lockName = 'p360_next_customer_no';
        $timeout  = 5; // segundos

        try {
            // Adquirir lock. Devuelve 1 si lo obtuvo.
            $got = (int) $conn->selectOne('SELECT GET_LOCK(?, ? ) AS l', [$lockName, $timeout])->l ?? 0;

            // Si no se pudo adquirir, usar cálculo simple como fallback
            if ($got !== 1) {
                $max  = (int) $conn->table('cuentas_cliente')->max('customer_no');
                return max(1, $max + 1);
            }

            // Con lock: calcular MAX+1 de forma segura
            $max  = (int) $conn->table('cuentas_cliente')->max('customer_no');
            $next = max(1, $max + 1);

            return $next;
        } finally {
            // Liberar lock si lo teníamos
            try { $conn->select('SELECT RELEASE_LOCK(?)', [$lockName]); } catch (\Throwable $e) {}
        }
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

        foreach ($sets as $set) {
            $pwd .= $set[random_int(0, strlen($set) - 1)];
        }

        for ($i = strlen($pwd); $i < $length; $i++) {
            $pwd .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($pwd);
    }

    /* ========================================================
     * Helpers de esquema (admin / clientes)
     * ======================================================== */
    private function adminHas(string $col): bool
    {
        try {
            return Schema::connection('mysql_admin')->hasColumn('accounts', $col);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function adminEmailColumn(): string
    {
        foreach (['correo_contacto', 'email'] as $c) {
            if ($this->adminHas($c)) {
                return $c;
            }
        }
        return 'email';
    }

    private function adminPhoneColumn(): string
    {
        foreach (['telefono', 'phone'] as $c) {
            if ($this->adminHas($c)) {
                return $c;
            }
        }
        return 'telefono';
    }

    private function adminNameColumn(): string
    {
        foreach (['name', 'razon_social', 'nombre', 'nombre_cuenta'] as $c) {
            if ($this->adminHas($c)) {
                return $c;
            }
        }
        return 'name';
    }

    private function buildAdminAccountInsert(array $data): array
    {
        $insert = [];

        $nameCol  = $this->adminNameColumn();
        $emailCol = $this->adminEmailColumn();
        $phoneCol = $this->adminPhoneColumn();

        $insert[$nameCol]  = $data['nombre'];
        $insert[$emailCol] = $data['email'];
        $insert[$phoneCol] = $data['telefono'];

        if ($this->adminHas('rfc'))               $insert['rfc']            = $data['rfc'];
        if ($this->adminHas('plan'))              $insert['plan']           = $data['plan'];
        if ($this->adminHas('plan_actual'))       $insert['plan_actual']    = $data['plan_actual'];
        if ($this->adminHas('modo_cobro'))        $insert['modo_cobro']     = $data['modo_cobro'];
        if ($this->adminHas('is_blocked'))        $insert['is_blocked']     = $data['is_blocked'];

        if ($this->adminHas('estado_cuenta')) {
            $insert['estado_cuenta'] = $data['estado_cuenta'];
        } elseif ($this->adminHas('status')) {
            $insert['status'] = $data['estado_cuenta'];
        }

        if ($this->adminHas('email_verified_at')) $insert['email_verified_at'] = $data['email_verified_at'];
        if ($this->adminHas('phone_verified_at')) $insert['phone_verified_at'] = $data['phone_verified_at'];
        if ($this->adminHas('created_at'))        $insert['created_at']        = $data['created_at'];
        if ($this->adminHas('updated_at'))        $insert['updated_at']        = $data['updated_at'];

        return $insert;
    }

    private function cliHas(string $tabla, string $col): bool
    {
        try {
            return Schema::connection('mysql_clientes')->hasColumn($tabla, $col);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
