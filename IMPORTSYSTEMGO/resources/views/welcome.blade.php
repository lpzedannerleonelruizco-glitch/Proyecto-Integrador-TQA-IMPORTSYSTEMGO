<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="d-flex align-items-center">
        <main class="container py-5">
            <section class="card border-0 shadow-sm mx-auto" style="max-width: 48rem">
                <div class="card-body p-4 p-md-5">
                    <span class="badge text-bg-primary mb-3">Laravel {{ app()->version() }}</span>
                    <h1 class="display-6 fw-semibold">IMPORTSYSTEMGO</h1>
                    <p class="lead text-secondary mb-4">
                        Base inicial del ERP preparada con Laravel, Blade, Bootstrap y MySQL.
                    </p>

                    <a href="{{ route('login') }}" class="btn btn-primary mb-4">Acceso corporativo</a>
                    <div class="alert alert-success mb-0" role="status">
                        La aplicación local está funcionando correctamente.
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
