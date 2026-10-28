<?php

namespace Tests\Feature;

use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BarberoSeeder;
use Database\Seeders\ProduccionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Pruebas de lo que hace falta para publicar el sistema: datos base que se cargan solos al
 * arrancar el servidor, contraseñas obligatorias en producción y HTTPS detrás del proxy de Render.
 */
class DespliegueTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- datos base (arranque)

    public function test_el_seeder_de_produccion_crea_el_admin_y_los_servicios(): void
    {
        config(['corteya.seed_admin_password' => 'clave-del-servidor-1']);

        $this->seed(ProduccionSeeder::class);

        $admin = User::where('email', 'admin@corteya.com')->firstOrFail();
        $this->assertSame('admin', $admin->rol);
        $this->assertTrue(Hash::check('clave-del-servidor-1', $admin->password));
        $this->assertSame(6, Servicio::count());

        // Solo datos base: nada de demo
        $this->assertSame(0, Barbero::count());
        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Turno::count());
    }

    public function test_repetirlo_en_cada_arranque_no_duplica_nada(): void
    {
        config(['corteya.seed_admin_password' => 'clave-del-servidor-1']);

        $this->seed(ProduccionSeeder::class);
        $this->seed(ProduccionSeeder::class);
        $this->seed(ProduccionSeeder::class);

        $this->assertSame(1, User::where('rol', 'admin')->count());
        $this->assertSame(6, Servicio::count());
    }

    public function test_un_nuevo_arranque_no_pisa_los_precios_que_el_dueno_edito(): void
    {
        config(['corteya.seed_admin_password' => 'clave-del-servidor-1']);
        $this->seed(ProduccionSeeder::class);

        // El dueño sube un precio y elimina un servicio que nunca se usó
        Servicio::where('nombre', 'Corte clásico')->update(['precio' => 40000]);
        Servicio::where('nombre', 'Barba')->delete();

        $this->seed(ProduccionSeeder::class); // el servidor se reinicia

        $this->assertEquals(40000, Servicio::where('nombre', 'Corte clásico')->value('precio'));
        $this->assertSame(0, Servicio::where('nombre', 'Barba')->count()); // no reaparece
        $this->assertSame(5, Servicio::count());
    }

    public function test_si_cambia_la_variable_la_contrasena_del_admin_se_actualiza(): void
    {
        config(['corteya.seed_admin_password' => 'primera-clave-123']);
        $this->seed(AdminSeeder::class);

        config(['corteya.seed_admin_password' => 'segunda-clave-456']);
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@corteya.com')->firstOrFail();
        $this->assertTrue(Hash::check('segunda-clave-456', $admin->password));
        $this->assertSame(1, User::where('rol', 'admin')->count());
    }

    // ------------------------------------------------ contraseñas en producción

    public function test_en_produccion_no_se_crea_el_admin_con_una_clave_por_defecto(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['corteya.seed_admin_password' => null]);

        try {
            // Se ejecuta el seeder directo: "db:seed" en producción pide una confirmación interactiva (el arranque real usa --force)
            app(AdminSeeder::class)->run();
            $this->fail('Debió rechazar la ejecución sin SEED_ADMIN_PASSWORD.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SEED_ADMIN_PASSWORD', $e->getMessage());
        }

        $this->assertSame(0, User::where('rol', 'admin')->count());
    }

    public function test_en_produccion_tampoco_se_crean_barberos_con_la_clave_por_defecto(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['corteya.seed_barbero_password' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_BARBERO_PASSWORD');

        app(BarberoSeeder::class)->run();
    }

    public function test_en_desarrollo_si_se_usan_las_claves_documentadas_del_readme(): void
    {
        config(['corteya.seed_admin_password' => null]);

        $this->seed(AdminSeeder::class);

        $this->assertTrue(Hash::check('admin12345', User::where('email', 'admin@corteya.com')->value('password')));
    }

    // --------------------------------------------------- barberos de demo (repetible)

    public function test_los_barberos_de_demo_son_repetibles_y_no_pisan_cambios(): void
    {
        $this->seed(BarberoSeeder::class);

        $this->assertSame(3, Barbero::count());
        $this->assertSame(21, Horario::count());   // 3 barberos × 7 días

        // El administrador le cambia la contraseña a un barbero; volver a sembrar no debe deshacerlo
        $andres = User::where('email', 'andres@corteya.com')->firstOrFail();
        $andres->update(['password' => 'cambiada-por-el-admin']);

        $this->seed(BarberoSeeder::class);

        $this->assertSame(3, Barbero::count());
        $this->assertSame(21, Horario::count());
        $this->assertTrue(Hash::check('cambiada-por-el-admin', $andres->fresh()->password));
    }

    // ------------------------------------------------------------ demo:cargar

    public function test_el_comando_demo_carga_los_datos_en_una_base_local_vacia(): void
    {
        $this->artisan('demo:cargar')->assertSuccessful();

        $this->assertSame(1, User::where('rol', 'admin')->count());
        $this->assertSame(6, Servicio::count());
        $this->assertSame(3, Barbero::count());
        $this->assertSame(25, Cliente::count());
        $this->assertGreaterThan(300, Turno::count());

        // La demo respeta R1: ningún barbero tiene dos turnos solapados
        $cruces = Turno::from('turnos as a')
            ->join('turnos as b', fn ($j) => $j->on('a.barbero_id', 'b.barbero_id')->on('a.fecha', 'b.fecha')->whereColumn('a.id', '<', 'b.id')
                ->whereColumn('a.hora_inicio', '<', 'b.hora_fin')->whereColumn('b.hora_inicio', '<', 'a.hora_fin'))
            ->count();
        $this->assertSame(0, $cruces);
    }

    public function test_el_comando_demo_no_mezcla_datos_de_demo_con_datos_reales(): void
    {
        Cliente::factory()->create(['nombre' => 'Cliente Real']);

        $this->artisan('demo:cargar')
            ->expectsOutputToContain('Ya hay clientes o turnos')
            ->assertFailed();

        $this->assertSame(1, Cliente::count());
        $this->assertSame(0, Barbero::count());
    }

    public function test_el_comando_demo_exige_contrasenas_propias_en_una_base_remota(): void
    {
        // Se simula que la base de datos está en un servidor (host remoto) y no hay variables definidas
        $conexion = config('database.default');
        config(["database.connections.{$conexion}.host" => 'mysql.ejemplo-remoto.com']);
        config(['corteya.seed_barbero_password' => null, 'corteya.seed_admin_password' => null]);

        $this->artisan('demo:cargar')
            ->expectsOutputToContain('SEED_BARBERO_PASSWORD')
            ->assertFailed();

        $this->assertSame(0, User::count());   // no se creó ninguna cuenta con claves conocidas
    }

    public function test_el_comando_demo_en_base_remota_funciona_si_hay_contrasenas_propias(): void
    {
        $conexion = config('database.default');
        config(["database.connections.{$conexion}.host" => 'mysql.ejemplo-remoto.com']);
        config(['corteya.seed_barbero_password' => 'barberos-claves-propias', 'corteya.seed_admin_password' => 'admin-clave-propia-1']);

        $this->artisan('demo:cargar')->assertSuccessful();

        $this->assertTrue(Hash::check('barberos-claves-propias', User::where('email', 'camilo@corteya.com')->value('password')));
        $this->assertTrue(Hash::check('admin-clave-propia-1', User::where('email', 'admin@corteya.com')->value('password')));
    }

    // ------------------------------------------------------------- HTTPS y salud

    public function test_detras_del_proxy_de_render_los_recursos_salen_con_https(): void
    {
        // Render termina el HTTPS y reenvía la petición por HTTP con la cabecera X-Forwarded-Proto
        // (Se busca el patrón y no la URL exacta: el host y el puerto dependen del APP_URL de cada equipo)
        $respuesta = $this->get('/login', ['X-Forwarded-Proto' => 'https'])->assertOk();

        $this->assertMatchesRegularExpression('#"https://[^"/]+/css/corteya\.css"#', $respuesta->getContent());
    }

    public function test_sin_el_proxy_los_recursos_salen_con_http(): void
    {
        // Contraste de la prueba anterior: sin la cabecera X-Forwarded-Proto, Laravel no inventa https.
        // Es una prueba aparte porque la aplicación recuerda el esquema de la primera petición.
        $respuesta = $this->get('/login')->assertOk();

        $this->assertMatchesRegularExpression('#"http://[^"/]+/css/corteya\.css"#', $respuesta->getContent());
    }

    public function test_la_ruta_de_salud_responde_para_el_chequeo_de_render(): void
    {
        $this->get('/up')->assertOk();
    }
}
