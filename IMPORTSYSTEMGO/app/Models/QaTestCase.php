<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaTestCase extends Model
{
    protected $table = 'qa_test_cases';

    protected $fillable = [
        'suite_id', 'code', 'name', 'category', 'case_type', 'executor',
        'objective', 'preconditions', 'test_data', 'validations',
        'expected_result', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'test_data' => 'array',
            'validations' => 'array',
            'expected_result' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(QaSuite::class, 'suite_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(QaExecutionResult::class, 'case_id');
    }
}
