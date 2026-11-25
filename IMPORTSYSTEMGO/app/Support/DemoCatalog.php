<?php

namespace App\Support;

final class DemoCatalog
{
    public const DOMAIN = 'importsystemgo.example';

    public static function branches(): array
    {
        return [
            'LP01' => ['name' => 'Centro', 'area' => 'LP'],
            'LP02' => ['name' => 'Sopocachi', 'area' => 'LP'],
            'LP03' => ['name' => 'Miraflores', 'area' => 'LP'],
            'LP04' => ['name' => 'Calacoto', 'area' => 'LP'],
            'EA01' => ['name' => 'Ceja', 'area' => 'EA'],
            'EA02' => ['name' => 'Satélite', 'area' => 'EA'],
            'EA03' => ['name' => 'Villa Adela', 'area' => 'EA'],
        ];
    }

    public static function identities(): array
    {
        $identities = [
            'admin.demo@'.self::DOMAIN => ['name' => 'Alex Rivera', 'role' => 'administrador-general', 'branch' => null, 'area' => null, 'employee' => false, 'employee_code' => ''],
            'operaciones.lp@'.self::DOMAIN => ['name' => 'Valeria Quispe', 'role' => 'jefe-operaciones', 'branch' => 'LP01', 'area' => 'LP', 'employee' => true, 'employee_code' => 'OP-LP'],
            'operaciones.ea@'.self::DOMAIN => ['name' => 'Diego Mamani', 'role' => 'jefe-operaciones', 'branch' => 'EA01', 'area' => 'EA', 'employee' => true, 'employee_code' => 'OP-EA'],
            'almacen.lp01@'.self::DOMAIN => ['name' => 'Noelia Flores', 'role' => 'almacenero', 'branch' => 'LP01', 'area' => null, 'employee' => false, 'employee_code' => ''],
            'creditos.lp01@'.self::DOMAIN => ['name' => 'Mateo Condori', 'role' => 'asesor-creditos', 'branch' => 'LP01', 'area' => null, 'employee' => false, 'employee_code' => ''],
            'compras.lp01@'.self::DOMAIN => ['name' => 'Sofía Rojas', 'role' => 'encargado-compras', 'branch' => 'LP01', 'area' => null, 'employee' => false, 'employee_code' => ''],
        ];

        foreach (self::branches() as $code => $branch) {
            $lower = strtolower($code);
            $identities["jefe.{$lower}@".self::DOMAIN] = ['name' => "Jefe {$code}", 'role' => 'jefe-tienda', 'branch' => $code, 'area' => null, 'employee' => true, 'employee_code' => "JE-{$code}"];
            for ($index = 1; $index <= 3; $index++) {
                $number = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                $identities["vendedor.{$lower}.{$number}@".self::DOMAIN] = ['name' => "Vendedor {$code} {$number}", 'role' => 'vendedor', 'branch' => $code, 'area' => null, 'employee' => true, 'employee_code' => "VE-{$code}-{$number}"];
            }
            $identities["cajero.{$lower}@".self::DOMAIN] = ['name' => "Cajero {$code}", 'role' => 'cajero', 'branch' => $code, 'area' => null, 'employee' => true, 'employee_code' => "CA-{$code}"];
        }

        return $identities;
    }
}
