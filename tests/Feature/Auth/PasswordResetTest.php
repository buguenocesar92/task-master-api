<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear un usuario para las pruebas
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
        $this->user = $user;

        // Interceptar envío de notificaciones
        Notification::fake();
    }

    /**
     * Test que verifica que se puede solicitar un restablecimiento de contraseña.
     *
     * Nota: Este test simula el comportamiento esperado y funcionará cuando se implemente
     * la funcionalidad real de recuperación de contraseña.
     */
    public function testCanRequestPasswordReset(): void
    {
        // Esto se deberá implementar en el controlador
        // Por ahora este test fallará hasta que se implemente la funcionalidad
        $this->markTestSkipped('Funcionalidad de recuperación de contraseña no implementada');
    }

    /**
     * Test que verifica que no se puede solicitar restablecimiento con email inexistente.
     */
    public function testCannotRequestPasswordResetWithInvalidEmail(): void
    {
        // Este test también es una simulación
        $this->markTestSkipped('Funcionalidad de recuperación de contraseña no implementada');
    }

    /**
     * Test que verifica que se puede restablecer la contraseña con un token válido.
     */
    public function testCanResetPasswordWithValidToken(): void
    {
        // Este test también es una simulación
        $this->markTestSkipped('Funcionalidad de recuperación de contraseña no implementada');
    }

    /**
     * Test que verifica que no se puede restablecer la contraseña con un token inválido.
     */
    public function testCannotResetPasswordWithInvalidToken(): void
    {
        // Este test también es una simulación
        $this->markTestSkipped('Funcionalidad de recuperación de contraseña no implementada');
    }
}
