<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head')
</head>
<body class="cy-login-fondo d-flex align-items-center justify-content-center p-3">

    @yield('contenido')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
