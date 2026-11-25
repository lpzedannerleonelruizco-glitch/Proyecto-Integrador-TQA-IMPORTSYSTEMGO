<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureMachineLearningSchemaReady
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['ml_model_versions', 'demand_forecasts', 'replenishment_recommendations'] as $table) {
            if (! Schema::hasTable($table)) {
                $message = 'El módulo de aprendizaje automático está preparado, pero su migración todavía no fue autorizada en este entorno.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 503)
                    : response()->view('errors.machine-learning-schema', compact('message'), 503);
            }
        }

        return $next($request);
    }
}
