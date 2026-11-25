<?php

namespace App\Services\Qa;

use Illuminate\Support\Str;

class QaAiDemo
{
    public const EXAMPLES = [
        '¿Cómo pruebo el inicio de sesión?' => "Positivo: ingresa credenciales válidas y comprueba que se abre el área autorizada.\nNegativo: usa una contraseña incorrecta y comprueba que la sesión sigue cerrada.\nLímite: deja el correo vacío y verifica el mensaje de campo obligatorio.",
        '¿Cómo pruebo un correo inválido?' => "Positivo: usa qa.admin@example.test y verifica que el formato se acepta.\nNegativo: escribe usuario-sin-arroba y verifica que el formulario lo rechaza.\nLímite: deja el campo vacío y comprueba la validación de obligatoriedad.",
        '¿Cómo pruebo el stock insuficiente?' => "Positivo: con 3 unidades disponibles, solicita 2 y verifica que la operación se permite.\nNegativo: solicita 4 y comprueba que la operación se rechaza sin cambiar el stock.\nLímite: solicita exactamente 3 y verifica que el saldo resultante sea cero.",
    ];

    public function answer(string $prompt): ?string
    {
        $normalize = static fn (string $value): string => Str::of($value)
            ->ascii()->lower()->replaceMatches('/[¿?¡!]/u', '')->squish()->toString();

        $normalized = $normalize($prompt);

        foreach (self::EXAMPLES as $question => $answer) {
            if ($normalized === $normalize($question)) {
                return $answer;
            }
        }

        return null;
    }
}
