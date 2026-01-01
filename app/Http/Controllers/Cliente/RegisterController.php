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
            // Generar customer_no una sola vez y mantenerlo consistente
            $customerNo = $this->nextCustomerNo();

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
                    // NUEVO: si existe customer_no en accounts, guardarlo también
                    'customer_no'       => $customerNo,
                ]));

            // --- 2) CLIENTES: cuenta_cliente ---
            $cuenta = new CuentaCliente();
            $cuenta->id             = (string) Str::uuid();
            $cuenta->codigo_cliente = $this->makeCodigoCliente();
            $cuenta->customer_no    = $customerNo; // consistente
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
            $usuario->activo    = 0;

            if ($this->cliHas('usuarios_cuenta', 'must_change_password')) {
                $usuario->must_change_password = 1;
            }
            $usuario->setConnection('mysql_clientes');
            $usuario->save();

            DB::connection('mysql_clientes')->commit();
            DB::connection('mysql_admin')->commit();

            // --- 4) Correo verificación + credenciales ---
            $token = $this->createEmailVerificationToken($adminAccountId, $email);
            $this->sendEmailVerification($email, $token, $name);
            $this->sendCredentialsEmail($email, $name, $rfc, $tempPassword, false);

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
            // customer_no consistente
            $customerNo = $this->nextCustomerNo();

            [$priceKey, $cycle, $amountMxn, $stripePriceId] = $this->resolveProLicense($modo);

            $meta = [];
            if ($this->adminHas('meta')) {
                $meta = [
                    'billing' => [
                        'price_key'       => $priceKey,
                        'billing_cycle'   => $cycle,
                        'amount_mxn'      => (int) $amountMxn,
                        'stripe_price_id' => $stripePriceId ?: null,
                        'assigned_at'     => now()->toISOString(),
                        'assigned_by'     => 'system.register.pro',
                    ],
                ];
            } else {
                Log::warning('Register.PRO.no_meta_column_on_admin.accounts', [
                    'msg' => 'No existe columna meta en mysql_admin.accounts; no se pudo guardar billing base.',
                ]);
            }

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
                    'meta'              => $meta,
                    'billing_cycle'     => $cycle,
                    // NUEVO: si existe customer_no en accounts, guardarlo también
                    'customer_no'       => $customerNo,
                ]));

            Log::info('Register.PRO.license_assigned_on_create', [
                'admin_account_id' => (int) $adminAccountId,
                'price_key' => $priceKey,
                'cycle' => $cycle,
                'amount_mxn' => (int) $amountMxn,
                'stripe_price_id' => $stripePriceId,
                'modo' => $modo,
                'customer_no' => $customerNo,
            ]);

            // --- 2) CLIENTES: cuenta_cliente ---
            $cuenta = new CuentaCliente();
            $cuenta->id             = (string) Str::uuid();
            $cuenta->codigo_cliente = $this->makeCodigoCliente();
            $cuenta->customer_no    = $customerNo;
            $cuenta->rfc_padre      = $rfc;
            $cuenta->razon_social   = $name;
            $cuenta->plan_actual    = 'PRO';
            $cuenta->modo_cobro     = $modo;
            $cuenta->estado_cuenta  = 'bloqueada_pago';

            if ($this->cliHas('cuentas_cliente', 'admin_account_id'))    $cuenta->admin_account_id     = $adminAccountId;
            if ($this->cliHas('cuentas_cliente', 'espacio_asignado_mb')) $cuenta->espacio_asignado_mb  = 15360;
            if ($this->cliHas('cuentas_cliente', 'max_usuarios'))        $cuenta->max_usuarios         = 10;

            if ($this->cliHas('cuentas_cliente', 'plan'))          $cuenta->plan = 'PRO';
            if ($this->cliHas('cuentas_cliente', 'billing_cycle')) $cuenta->billing_cycle = $cycle;

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
            $usuario->activo    = 0;

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

            // --- 5) Checkout ---
            session([
                'checkout_plan'     => $modo,
                'verify.account_id' => (int) $adminAccountId,
                'verify.email'      => $email,
                'verify.rfc'        => $rfc,
            ]);

            return view('cliente.auth.redirect_checkout', [
                'plan'      => $modo,
                'accountId' => (string) $adminAccountId,
                'email'     => $email,
            ]);

        } catch (\Throwable $e) {
            DB::connection('mysql_clientes')->rollBack();
            DB::connection('mysql_admin')->rollBack();

            Log::error('Error en registro PRO', ['error' => $e->getMessage()]);
            return back()->withErrors(['general' => 'Ocurrió un error al registrar PRO. Intenta nuevamente.'])->withInput();
        }
    }

    private function resolveProLicense(string $modo): array
    {
        $priceKey = ($modo === 'anual') ? 'pro_anual' : 'pro_mensual';
        $cycle    = ($modo === 'anual') ? 'yearly' : 'monthly';

        $catalog = config('p360.billing.prices');
        if (!is_array($catalog)) $catalog = [];

        $amount = 0;
        $stripePriceId = null;

        if (isset($catalog[$priceKey]) && is_array($catalog[$priceKey])) {
            $p = $catalog[$priceKey];
            $amount = (int) ($p['amount_mxn'] ?? 0);
            $cycle  = (string) ($p['billing_cycle'] ?? $cycle);
            $stripePriceId = $p['stripe_price_id'] ?? null;
        } else {
            if ($modo === 'anual') {
                $amount = (int) round((float) config('services.stripe.display_price_annual', 9990.00), 0);
            } else {
                $amount = (int) round((float) config('services.stripe.display_price_monthly', 990.00), 0);
            }
        }

        if ($amount <= 0) {
            $amount = ($modo === 'anual') ? 8990 : 899;
        }

        return [$priceKey, $cycle, $amount, is_string($stripePriceId) ? $stripePriceId : null];
    }

    private function sendEmailVerification(string $email, string $token, string $nombre): void
    {
        $url = route('cliente.verify.email.token', ['token' => $token]);
        $data = [
            'nombre'    => $nombre,
            'actionUrl' => $url,
            'soporte'   => 'soporte@pactopia.com',
        ];

        try {
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

    private function sendCredentialsEmail(string $email, string $nombre, string $rfc, string $plainPassword, bool $isPro): void
    {
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

    private function rfcExiste(string $rfc): bool
    {
        $existsAdmin = DB::connection('mysql_admin')
            ->table('accounts')
            ->whereRaw('UPPER(COALESCE(rfc,"")) = ?', [strtoupper($rfc)])
            ->exists();

        $existsCliente = false;
        if (Schema::connection('mysql_clientes')->hasTable('cuentas_cliente')) {
            $existsCliente = DB::connection('mysql_clientes')
                ->table('cuentas_cliente')
                ->whereRaw('UPPER(COALESCE(rfc_padre,"")) = ?', [strtoupper($rfc)])
                ->exists();
        }

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

    private function makeCodigoCliente(): string
    {
        return 'C' . strtoupper(Str::random(6));
    }

    private function nextCustomerNo(): int
    {
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
        } catch (\Throwable $e) {}

        $conn = DB::connection('mysql_clientes');
        $lockName = 'p360_next_customer_no';
        $timeout  = 5;

        try {
            $got = (int) ($conn->selectOne('SELECT GET_LOCK(?, ? ) AS l', [$lockName, $timeout])->l ?? 0);

            if ($got !== 1) {
                $max  = (int) $conn->table('cuentas_cliente')->max('customer_no');
                return max(1, $max + 1);
            }

            $max  = (int) $conn->table('cuentas_cliente')->max('customer_no');
            $next = max(1, $max + 1);

            return $next;
        } finally {
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
            if ($this->adminHas($c)) return $c;
        }
        return 'email';
    }

    private function adminPhoneColumn(): string
    {
        foreach (['telefono', 'phone'] as $c) {
            if ($this->adminHas($c)) return $c;
        }
        return 'telefono';
    }

    private function adminNameColumn(): string
    {
        foreach (['name', 'razon_social', 'nombre', 'nombre_cuenta'] as $c) {
            if ($this->adminHas($c)) return $c;
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

        // NUEVO: customer_no si existe en accounts
        if ($this->adminHas('customer_no') && array_key_exists('customer_no', $data)) {
            $insert['customer_no'] = (int) $data['customer_no'];
        }

        if ($this->adminHas('meta') && array_key_exists('meta', $data) && is_array($data['meta'])) {
            $insert['meta'] = json_encode($data['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($this->adminHas('billing_cycle') && array_key_exists('billing_cycle', $data) && is_string($data['billing_cycle'])) {
            $insert['billing_cycle'] = $data['billing_cycle'];
        }

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
