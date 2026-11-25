<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureInventorySchemaReady;
use App\Http\Middleware\EnsureMachineLearningSchemaReady;
use App\Http\Middleware\ProtectSensitiveResponses;
use App\Http\Middleware\ResolveBranchContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ProtectSensitiveResponses::class]);
        $middleware->alias([
            'active.user' => EnsureActiveUser::class,
            'branch.context' => ResolveBranchContext::class,
            'inventory.schema' => EnsureInventorySchemaReady::class,
            'ml.schema' => EnsureMachineLearningSchemaReady::class,
        ]);
        $middleware->redirectUsersTo('/sucursales/seleccionar');

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveBranchContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'current_password', 'password', 'password_confirmation']);
    })->create();
