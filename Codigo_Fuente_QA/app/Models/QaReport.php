<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QaReport extends Model
{
    protected $table = 'qa_reports';

    protected $fillable = ['execution_id', 'format', 'relative_path', 'sha256', 'bytes'];
}
