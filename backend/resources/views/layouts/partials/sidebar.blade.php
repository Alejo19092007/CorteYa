@php
    // Menú según el rol. Cada ítem: [nombre de la ruta, ícono, texto].
    // El barbero SOLO ve "Mi agenda" (no ve Barberos, Servicios ni Clientes).
    $items = auth()->user()->esAdmin()
        ? [
            ['dashboard', 'bi-house', 'Dashboard'],
            ['turnos.index', 'bi-calendar3', 'Turnos'],
            ['barberos.index', 'bi-person', 'Barberos'],
            ['servicios.index', 'bi-scissors', 'Servicios'],
            ['clientes.index', 'bi-people', 'Clientes'],
        ]
        : [
            ['agenda', 'bi-calendar3', 'Mi agenda'],
        ];
@endphp

<aside class="cy-sidebar offcanvas-lg offcanvas-start d-flex flex-column" tabindex="-1" id="menuLateral"
       aria-labelledby="menuLateralTitulo">

    <div class="offcanvas-header">
        <span class="cy-marca" id="menuLateralTitulo"><i class="bi bi-scissors"></i> CORTEYA</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                data-bs-target="#menuLateral" aria-label="Cerrar menú"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-3 pt-lg-4">
        {{-- Logo (en escritorio; en celular ya está en la cabecera del menú) --}}
        <a href="{{ route('inicio') }}" class="cy-marca d-none d-lg-block text-center mb-4">
            <i class="bi bi-scissors fs-2"></i>
            <div>CORTEYA</div>
            <small>SISTEMA DE TURNOS</small>
        </a>

        <nav class="nav flex-column gap-1">
            @foreach ($items as [$ruta, $icono, $texto])
                {{-- Route::has: el ítem aparece solo cuando su pantalla ya existe --}}
                @if (Route::has($ruta))
                    <a href="{{ route($ruta) }}"
                       class="nav-link {{ request()->routeIs(\Illuminate\Support\Str::before($ruta, '.').'*') ? 'active' : '' }}">
                        <i class="bi {{ $icono }}"></i> {{ $texto }}
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- Cerrar sesión: formulario POST con token CSRF (no un enlace) --}}
        <form method="POST" action="{{ route('logout') }}" class="cy-usuario mt-auto pt-3">
            @csrf
            <button type="submit" class="nav-link w-100 border-0 bg-transparent text-start">
                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
            </button>
        </form>
    </div>
</aside>
