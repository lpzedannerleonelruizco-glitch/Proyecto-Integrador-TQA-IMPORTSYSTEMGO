<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QaAiSession extends Model
{
    protected $table = 'qa_ai_sessions';

    protected $fillable = ['public_id', 'owner_id', 'provider', 'model', 'prompt', 'response', 'status', 'error_message'];
}
