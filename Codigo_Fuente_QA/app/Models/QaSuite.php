<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaSuite extends Model
{
    protected $table = 'qa_suites';

    protected $fillable = ['code', 'name', 'type', 'category', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cases(): HasMany
    {
        return $this->hasMany(QaTestCase::class, 'suite_id');
    }
}
