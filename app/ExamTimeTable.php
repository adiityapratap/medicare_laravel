<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class ExamTimeTable extends Model
{
    use SoftDeletes;
	use HasUserStamps;
	
    public function subject()
    {
        return $this->belongsTo('App\Subject', 'subject_id');
    }

    public function exam()
    {
        return $this->belongsTo('App\Exam', 'exam_id');
    }
}
