<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head')
</head>
<body>

    {{-- Menú lateral: barra fija en escritorio y menú deslizable (offcanvas) en celular --}}
    @include('layouts.partials.sidebar')

    <div class="cy-main">
        {{-- Barra superior: botón del menú (solo celular) y datos del usuario --}}
        <header class="cy-topbar d-flex align-items-center justify-content-between px-3 py-2">
            <button class="btn btn-outline-secondary d-lg-none" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#menuLateral"
                    aria-controls="menuLateral" aria-label="Abrir menú">
                <i class="bi bi-list fs-5"></i>
            </button>

            <span class="cy-titulo fs-5 d-lg-none">CORTEYA</span>

            <div class="text-end ms-auto lh-sm">
                <div class="fw-semibold">{{ auth()->user()->name }}</div>
                <small class="text-secondary">{{ auth()->user()->esAdmin() ? 'Administrador' : 'Barbero' }}</small>
            </div>
        </header>

        <main class="container-fluid p-3 p-lg-4">
            {{-- Mensajes de éxito o error que dejan los controladores con ->with('exito', ...) --}}
            @if (session('exito'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('exito') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif

            @yield('contenido')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
