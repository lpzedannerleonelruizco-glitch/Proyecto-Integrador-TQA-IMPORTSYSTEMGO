<?php

return [
    'python_binary' => env('ML_PYTHON_BINARY', base_path('.venv-ml/Scripts/python.exe')),
    'python_base_argument' => env('ML_PYTHON_BASE_ARGUMENT', ''),
    'minimum_history_days' => (int) env('ML_MINIMUM_HISTORY_DAYS', 180),
    'minimum_training_rows' => (int) env('ML_MINIMUM_TRAINING_ROWS', 500),
    'forecast_horizon_days' => (int) env('ML_FORECAST_HORIZON_DAYS', 30),
    'timeout_seconds' => (int) env('ML_TRAINING_TIMEOUT', 300),
];
