<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QaEvidence extends Model
{
    protected $table = 'qa_evidences';

    protected $fillable = ['execution_id', 'result_id', 'kind', 'description', 'relative_path', 'sha256'];
}
