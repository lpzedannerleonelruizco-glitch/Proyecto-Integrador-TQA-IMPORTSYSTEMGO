<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QaEmailLog extends Model
{
    protected $table = 'qa_email_logs';

    protected $fillable = ['execution_id', 'recipient_email', 'status', 'attempt', 'error_message', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
