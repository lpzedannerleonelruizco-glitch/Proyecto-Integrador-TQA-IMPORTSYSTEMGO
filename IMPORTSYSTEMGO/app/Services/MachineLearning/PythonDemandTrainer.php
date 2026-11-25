<?php

namespace App\Services\MachineLearning;

use RuntimeException;
use Symfony\Component\Process\Process;

class PythonDemandTrainer
{
    /** @return array<string, mixed> */
    public function train(string $input, string $output, string $artifact): array
    {
        $binary = (string) config('machine-learning.python_binary');
        $baseArgument = trim((string) config('machine-learning.python_base_argument'));
        $command = [$binary];
        if ($baseArgument !== '') {
            $command[] = $baseArgument;
        }
        array_push($command,
            base_path('ml/train_demand.py'),
            '--input', $input,
            '--output', $output,
            '--artifact', $artifact,
            '--minimum-history-days', (string) config('machine-learning.minimum_history_days'),
            '--minimum-training-rows', (string) config('machine-learning.minimum_training_rows'),
            '--horizon-days', (string) config('machine-learning.forecast_horizon_days'),
        );

        $process = new Process($command, base_path(), null, null, (float) config('machine-learning.timeout_seconds'));
        $process->run();
        if (! is_file($output)) {
            throw new RuntimeException('El motor de aprendizaje automático no produjo un resultado verificable.');
        }

        $result = json_decode((string) file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
        if (! $process->isSuccessful() || ! ($result['ok'] ?? false)) {
            throw new RuntimeException((string) ($result['error'] ?? 'Falló el entrenamiento del modelo.'));
        }

        return $result;
    }
}
