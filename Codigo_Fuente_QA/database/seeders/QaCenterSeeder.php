<?php

namespace Database\Seeders;

use App\Models\QaSuite;
use App\Models\QaTestCase;
use Illuminate\Database\Seeder;

class QaCenterSeeder extends Seeder
{
    public function run(): void
    {
        $suites = [
            ['SMOKE', 'Smoke', 'functional', 'smoke'],
            ['SANITY', 'Sanity', 'functional', 'sanity'],
            ['REGRESSION', 'Regression', 'functional', 'regression'],
            ['INTEGRATION', 'Integration', 'functional', 'integration'],
            ['END_TO_END', 'End to End', 'functional', 'end_to_end'],
            ['NON_FUNCTIONAL', 'No funcional', 'non_functional', 'performance'],
            ['ROBOT', 'Robot Framework', 'functional', 'robot'],
        ];

        foreach ($suites as [$code, $name, $type, $category]) {
            QaSuite::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'category' => $category,
                'is_active' => true,
            ]);
        }

        $cases = [
            ['TC-SMK-001', 'Inicio de sesión correcto', 'SMOKE', 'selenium', 'functional', 'El usuario activo inicia sesión y llega al QA Center con su sesión regenerada.', [['selector' => '#email y #password'], ['session' => 'authenticated']]],
            ['TC-SMK-002', 'Acceso al dashboard QA', 'SMOKE', 'selenium', 'functional', 'Un usuario autenticado con permiso carga el dashboard QA.', [['status' => 200], ['view' => 'IMPORTSYSTEMGO QA CENTER']]],
            ['TC-SMK-003', 'Consulta de productos', 'SMOKE', 'phpunit', 'functional', 'El catálogo muestra únicamente productos accesibles.', [['status' => 200], ['catalog' => 'visible']]],
            ['TC-SMK-004', 'Consulta de inventario', 'SMOKE', 'phpunit', 'functional', 'El inventario respeta sucursal y permisos.', [['status' => 200], ['scope' => 'active_branch']]],
            ['TC-SAN-001', 'Búsqueda de productos', 'SANITY', 'phpunit', 'functional', 'La búsqueda devuelve coincidencias válidas.', [['result' => 'matching'], ['other' => 'excluded']]],
            ['TC-SAN-002', 'Cálculo automático de totales', 'SANITY', 'phpunit', 'functional', 'Subtotal, descuento e impuesto producen el total esperado.', [['total' => 'calculated'], ['precision' => 'decimal']]],
            ['TC-SAN-003', 'FAIL CONTROLADO — Validación HTML5 de correo', 'SANITY', 'selenium', 'functional', 'El navegador rechaza un correo inválido; la demostración invierte deliberadamente la expectativa para evidenciar un FAIL controlado.', [['validation' => 'typeMismatch=true'], ['form' => 'invalid value is blocked']]],
            ['TC-REG-001', 'Registro de productos', 'REGRESSION', 'phpunit', 'functional', 'Producto válido se crea y datos sensibles no son aceptados.', [['created' => true], ['cost' => 'controlled']]],
            ['TC-REG-002', 'Entrada de inventario', 'REGRESSION', 'phpunit', 'functional', 'Una recepción actualiza saldo y kardex una sola vez.', [['balance' => 'updated'], ['idempotency' => 'preserved']]],
            ['TC-REG-003', 'Salida de inventario', 'REGRESSION', 'phpunit', 'functional', 'Una salida nunca deja saldo negativo.', [['sale' => 'confirmed'], ['stock' => 'non_negative']]],
            ['TC-REG-004', 'Registro de ventas', 'REGRESSION', 'phpunit', 'functional', 'Una venta queda creada dentro de sucursal y rol.', [['sale' => 'created'], ['scope' => 'branch']]],
            ['TC-REG-005', 'Permisos de usuarios', 'REGRESSION', 'phpunit', 'functional', 'Roles locales deniegan recursos ajenos.', [['allowed' => 'own_scope'], ['forbidden' => 'other_scope']]],
            ['TC-INT-001', 'Integración de ventas e inventario', 'INTEGRATION', 'phpunit', 'integration', 'Venta confirmada reserva o consume existencias consistentemente.', [['sale' => 'linked'], ['inventory' => 'consistent']]],
            ['TC-INT-002', 'Integración de productos y categorías', 'INTEGRATION', 'phpunit', 'integration', 'Producto conserva categoría y marca válidas.', [['category' => 'valid'], ['product' => 'persisted']]],
            ['TC-INT-003', 'Integración de ventas y reportes', 'INTEGRATION', 'phpunit', 'integration', 'La venta afecta reportes autorizados de su sucursal.', [['report' => 'updated'], ['scope' => 'branch']]],
            ['TC-E2E-001', 'Flujo completo de venta', 'END_TO_END', 'selenium', 'e2e', 'Cliente, producto, venta y cobro siguen el flujo disponible.', [['customer' => 'valid'], ['payment' => 'confirmed']]],
            ['TC-E2E-002', 'Flujo completo de inventario', 'END_TO_END', 'selenium', 'e2e', 'Recepción, stock y movimiento quedan trazables.', [['receipt' => 'confirmed'], ['kardex' => 'traced']]],
            ['TC-NF-001', 'Tiempo de respuesta', 'NON_FUNCTIONAL', 'locust', 'performance', 'La carga normal mantiene el umbral de tres segundos.', [['p95' => '<= 3000ms'], ['errors' => 'bounded']]],
            ['TC-NF-002', 'Carga concurrente', 'NON_FUNCTIONAL', 'locust', 'performance', 'Una carga breve soporta usuarios definidos.', [['users' => 'configured'], ['rps' => 'reported']]],
            ['TC-NF-003', 'Estrés controlado', 'NON_FUNCTIONAL', 'locust', 'performance', 'El estrés corto identifica saturación sin afectar ERP persistente.', [['duration' => 'limited'], ['target' => 'qa_local']]],
            ['TC-NF-004', 'Resistencia', 'NON_FUNCTIONAL', 'locust', 'performance', 'Una demostración breve verifica estabilidad temporal.', [['duration' => 'short_demo'], ['not' => 'certification']]],
            ['TC-NF-005', 'Acceso sin autenticación', 'NON_FUNCTIONAL', 'robot', 'security', 'Ruta protegida redirige o devuelve denegación.', [['anonymous' => 'denied'], ['login' => 'required']]],
            ['TC-NF-006', 'Restricciones por roles', 'NON_FUNCTIONAL', 'phpunit', 'security', 'Permisos reales deniegan acciones fuera de alcance.', [['own_role' => 'allowed'], ['cross_role' => 'denied']]],
        ];

        foreach ($cases as [$code, $name, $suiteCode, $executor, $caseType, $objective, $validations]) {
            $suite = QaSuite::query()->where('code', $suiteCode)->first();
            QaTestCase::query()->updateOrCreate(['code' => $code], [
                'suite_id' => $suite?->id,
                'name' => $name,
                'category' => strtolower($suiteCode),
                'case_type' => $caseType,
                'executor' => $executor,
                'objective' => $objective,
                'preconditions' => 'Entorno QA aislado, aplicación disponible y usuario autorizado.',
                'test_data' => ['environment' => 'importsystemgo_qa'],
                'validations' => $validations,
                'expected_result' => [['status' => 'PASS o FAIL controlado'], ['mutation' => 'conforme al alcance']],
                'is_active' => true,
            ]);
        }
    }
}
