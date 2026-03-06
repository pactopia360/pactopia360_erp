<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminPasswordResetController extends Controller
{
    /**
     * Mostrar formulario para solicitar link de recuperación.
     */
    public function showRequestForm(Request $request)
    {
        Auth::shouldUse('admin');

        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.home');
        }

        return view('admin.auth.forgot-password');
    }

    /**
     * Enviar correo con link de recuperación.
     */
    public function sendResetLink(Request $request)
    {
        Auth::shouldUse('admin');

        $request->validate([
            'email' => ['required', 'string', 'email:rfc,dns', 'max:150'],
        ], [], [
            'email' => 'correo electrónico',
        ]);

        $email = Str::lower(trim((string) $request->input('email')));

        [$adminConn, $adminTable] = $this->adminConnAndTable();

        $admin = DB::connection($adminConn)
            ->table($adminTable)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        // Respuesta genérica para no enumerar usuarios
        $genericMessage = 'Si el correo existe, te enviamos un enlace para restablecer tu contraseña.';

        if (!$admin) {
            return back()->with('status', $genericMessage)->withInput();
        }

        $tokenTable = (string) (config('auth.passwords.admins.table') ?: 'password_reset_tokens');
        $tokenConn  = (string) (config('auth.passwords.admins.connection') ?: 'mysql_admin');
        $expires    = (int) (config('auth.passwords.admins.expire') ?: 60);

        if (!Schema::connection($tokenConn)->hasTable($tokenTable)) {
            return back()
                ->withErrors(['email' => 'La tabla de recuperación de contraseñas no existe en mysql_admin. Ejecuta la migración primero.'])
                ->withInput();
        }

        $token = Str::random(64);

        DB::connection($tokenConn)->table($tokenTable)->where('email', $email)->delete();

        DB::connection($tokenConn)->table($tokenTable)->insert([
            'email'      => $email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetUrl = route('admin.password.reset', [
            'token' => $token,
            'email' => $email,
        ]);

        try {
            Mail::send('emails.admin.reset-password', [
                'email'      => $email,
                'resetUrl'   => $resetUrl,
                'expiresMin' => $expires,
                'appName'    => config('app.name', 'Pactopia360'),
            ], function ($message) use ($email) {
                $message->to($email)
                    ->subject('Restablece tu contraseña de administrador');
            });
        } catch (\Throwable $e) {
            Log::error('admin-password-reset-mail-error', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['email' => 'No se pudo enviar el correo de recuperación. Revisa la configuración de correo.'])
                ->withInput();
        }

        return back()->with('status', $genericMessage);
    }

    /**
     * Mostrar formulario para restablecer contraseña.
     */
    public function showResetForm(Request $request, string $token)
    {
        Auth::shouldUse('admin');

        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.home');
        }

        $email = Str::lower(trim((string) $request->query('email', '')));

        if ($email === '') {
            return redirect()
                ->route('admin.password.request')
                ->withErrors(['email' => 'El enlace de recuperación es inválido o está incompleto.']);
        }

        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Restablecer contraseña.
     */
    public function reset(Request $request)
    {
        Auth::shouldUse('admin');

        $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'string', 'email:rfc,dns', 'max:150'],
            'password'              => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8', 'max:200'],
        ], [], [
            'email'                 => 'correo electrónico',
            'password'              => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
        ]);

        $email      = Str::lower(trim((string) $request->input('email')));
        $plainToken = (string) $request->input('token');
        $newPass    = (string) $request->input('password');

        $tokenTable = (string) (config('auth.passwords.admins.table') ?: 'password_reset_tokens');
        $tokenConn  = (string) (config('auth.passwords.admins.connection') ?: 'mysql_admin');
        $expires    = (int) (config('auth.passwords.admins.expire') ?: 60);

        if (!Schema::connection($tokenConn)->hasTable($tokenTable)) {
            return back()
                ->withErrors(['email' => 'La tabla de recuperación de contraseñas no existe en mysql_admin.'])
                ->withInput();
        }

        $tokenRow = DB::connection($tokenConn)
            ->table($tokenTable)
            ->where('email', $email)
            ->first();

        if (!$tokenRow) {
            return back()
                ->withErrors(['email' => 'El enlace de recuperación es inválido o ya expiró.'])
                ->withInput();
        }

        $createdAt = $tokenRow->created_at ? \Carbon\Carbon::parse($tokenRow->created_at) : now()->subMinutes($expires + 1);
        $isExpired = $createdAt->lt(now()->subMinutes($expires));

        if ($isExpired || !Hash::check($plainToken, (string) $tokenRow->token)) {
            DB::connection($tokenConn)->table($tokenTable)->where('email', $email)->delete();

            return back()
                ->withErrors(['email' => 'El enlace de recuperación es inválido o ya expiró.'])
                ->withInput();
        }

        [$adminConn, $adminTable] = $this->adminConnAndTable();

        $admin = DB::connection($adminConn)
            ->table($adminTable)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (!$admin) {
            DB::connection($tokenConn)->table($tokenTable)->where('email', $email)->delete();

            return back()
                ->withErrors(['email' => 'No se encontró la cuenta administrativa asociada a ese correo.'])
                ->withInput();
        }

        $update = [
            'password' => Hash::make($newPass),
        ];

        if ($this->colExists($adminConn, $adminTable, 'remember_token')) {
            $update['remember_token'] = Str::random(60);
        }

        if ($this->colExists($adminConn, $adminTable, 'force_password_change')) {
            $update['force_password_change'] = 0;
        }

        if ($this->colExists($adminConn, $adminTable, 'updated_at')) {
            $update['updated_at'] = now();
        }

        DB::connection($adminConn)
            ->table($adminTable)
            ->where('id', $admin->id)
            ->update($update);

        DB::connection($tokenConn)->table($tokenTable)->where('email', $email)->delete();

        if (Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
        }

        return redirect()
            ->route('admin.login')
            ->with('status', 'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.');
    }

    private function adminConnAndTable(): array
    {
        $provider = Auth::guard('admin')->getProvider();
        $modelCls = method_exists($provider, 'getModel') ? $provider->getModel() : null;

        if ($modelCls) {
            $m = new $modelCls;
            $conn  = $m->getConnectionName() ?: (config('database.default') ?: 'mysql');
            $table = $m->getTable() ?: 'usuario_administrativos';
            return [$conn, $table];
        }

        return [
            config('database.connections.mysql_admin') ? 'mysql_admin' : (config('database.default') ?: 'mysql'),
            'usuario_administrativos',
        ];
    }

    private function colExists(string $conn, string $table, string $column): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}