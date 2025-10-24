<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Concerns\CreatesClienteFixtures;

class ClienteFlowTest extends TestCase
{
    use CreatesClienteFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // Todo en SQLite :memory: y con el esquema mínimo necesario
        $this->useInMemorySqliteForAllConnections();

        // No mandamos correos reales
        Mail::fake();

        // Evita que el middleware se “salte” cosas por estar en testing,
        // excepto en los casos donde lo activamos explícitamente.
        putenv('P360_ENFORCE_ACCOUNT_ACTIVE=false');
    }

    /**
     * Flujo FREE: Verify Email → Send OTP → Check OTP → Redirect a login.
     */
    public function test_verify_email_then_phone_free_ok(): void
    {
        // Arrange: FREE
        [$adminId] = $this->seedFreeAccount(email: 'free@example.com', rfc: 'ABC0102039A1');
        $this->seedEmailToken($adminId, 'free@example.com', 'TOK');

        // Act 1: Verificar correo por token
        $this->get(route('cliente.verify.email.token', ['token' => 'TOK']))
            ->assertOk()
            ->assertSee('¡Correo verificado!'); // coincide con la vista

        // Act 2: Enviar OTP
        $this->post(route('cliente.verify.phone.send'), ['channel' => 'sms'])
            ->assertSessionHas('ok');

        $otp = $this->getLastOtp($adminId);
        $this->assertNotNull($otp, 'Se esperaba un OTP generado');

        // Act 3: Validar OTP correcto
        $this->post(route('cliente.verify.phone.check'), ['code' => $otp->code])
            ->assertRedirect(route('cliente.login'));
    }

    /**
     * Verificación por token EXPIRED: debe mostrar estado "Enlace expirado".
     */
    public function test_verify_email_expired_token(): void
    {
        // Arrange: cuenta y token vencido
        [$adminId] = $this->seedFreeAccount(email: 'late@example.com', rfc: 'LAA0102039A1');

        // Token ya expirado
        $this->seedEmailToken($adminId, 'late@example.com', 'TOKEXPIRED');
        // Fuerza expiración
        app('db')->connection('mysql_admin')
            ->table('email_verifications')
            ->where('token', 'TOKEXPIRED')
            ->update(['expires_at' => now()->subMinute()]);

        // Act
        $this->get(route('cliente.verify.email.token', ['token' => 'TOKEXPIRED']))
            ->assertOk()
            ->assertSee('Enlace expirado');
    }

    /**
     * OTP incorrecto varias veces → alcanza OTP_MAX_ATTEMPTS (5) → invalida OTP.
     */
    public function test_otp_incorrect_until_lock_then_fail(): void
    {
        // Arrange FREE + token para llegar a verify_phone
        [$adminId] = $this->seedFreeAccount(email: 'otp@wrong.com', rfc: 'OTW0102039A1');
        $this->seedEmailToken($adminId, 'otp@wrong.com', 'TOKOTP');

        // Paso 1: verify email (puebla sesión verify.account_id)
        $this->get(route('cliente.verify.email.token', ['token' => 'TOKOTP']))->assertOk();

        // Paso 2: enviar OTP
        $this->post(route('cliente.verify.phone.send'), ['channel' => 'sms'])
            ->assertSessionHas('ok');

        // Paso 3: intentar 5 veces con código incorrecto
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('cliente.verify.phone.check'), ['code' => '000000'])
                ->assertSessionHasErrors(['code']); // “Código incorrecto…” y en el 5to “Se excedió…”
        }

        // Luego aunque intentes con el correcto, ya debe fallar por expiración/invalidación
        $otp = $this->getLastOtp($adminId);
        $this->assertNotNull($otp);
        $this->post(route('cliente.verify.phone.check'), ['code' => $otp->code])
            ->assertSessionHasErrors(['code']); // “El código expiró o no existe…”
    }

    /**
     * Cuenta PRO bloqueada/past_due → redirige a Billing por middleware.
     */
    public function test_blocked_account_redirects_to_billing(): void
    {
        // Activamos el candado del middleware en testing únicamente para este caso.
        putenv('P360_ENFORCE_ACCOUNT_ACTIVE=true');

        // Arrange: PRO bloqueada (con suscripción past_due)
        [$adminId, $cuentaId, $userId] = $this->seedProBlocked(email: 'pro@example.com', rfc: 'XYZ010203AA1');

        // Simular sesión del cliente (guard web)
        $user = new \App\Models\Cliente\UsuarioCuenta();
        $user->forceFill([
            'id' => $userId,
            'email' => 'pro@example.com',
            'cuenta_id' => $cuentaId,
            'activo' => 1,
        ]);
        $this->be($user, 'web');

        // Debe redirigir a billing por EnsureAccountIsActive
        $this->get(route('cliente.home'))
            ->assertRedirect(route('cliente.billing.statement'));

        // Volvemos a apagar el candado para no afectar otros tests
        putenv('P360_ENFORCE_ACCOUNT_ACTIVE=false');
    }

    /**
     * Link firmado de verificación de correo (alternativo).
     */
    public function test_signed_email_verification_link(): void
    {
        // Arrange
        [$adminId] = $this->seedFreeAccount(email: 'signed@example.com', rfc: 'SIG010203AA1');

        // URL firmada (usa el middleware 'signed' en tu ruta)
        $url = url()->signedRoute('cliente.verify.email.signed', [
            'account_id' => $adminId,
            'email'      => 'signed@example.com',
        ]);

        $this->get($url)
            ->assertRedirect(route('cliente.verify.phone'))
            ->assertSessionHas('ok');
    }

    /**
     * (Extra) Reenvío de verificación de correo: si ya estaba verificado, muestra OK.
     */
    public function test_resend_email_if_already_verified_shows_ok(): void
    {
        // Cuenta con email ya verificado
        [$adminId] = $this->seedFreeAccount(email: 'already@ok.com', rfc: 'OKA010203AA1');
        app('db')->connection('mysql_admin')
            ->table('accounts')->where('id', $adminId)
            ->update(['email_verified_at' => now()]);

        $this->post(route('cliente.verify.email.resend.do'), ['email' => 'already@ok.com'])
            ->assertSessionHas('ok');
    }
}
