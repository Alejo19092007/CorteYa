<!DOCTYPE html>
{{--
    Layout de las páginas de error. Es INDEPENDIENTE a propósito: no usa sesión, usuario ni
    base de datos. Si el error es que la base de datos se cayó, una página que la consultara
    volvería a fallar y el usuario vería una pantalla en blanco.
--}}
<html lang="es">
<head>
    @include('layouts.partials.head')
</head>
<body>
    <div class="cy-error-pagina">
        <div>
            <i class="bi bi-scissors cy-error-icono"></i>
            <div class="cy-error-codigo">@yield('codigo')</div>
            <h1 class="h2 mb-3">@yield('mensaje')</h1>
            <p class="text-secondary mb-4">@yield('detalle')</p>
            <a href="{{ url('/') }}" class="btn btn-primary btn-lg px-4">
                <i class="bi bi-house"></i> Volver al inicio
            </a>
        </div>
    </div>
</body>
</html>
