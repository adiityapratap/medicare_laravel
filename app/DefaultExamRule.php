<?php

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;

class DefaultExamRule extends Model
{
    use SoftDeletes;
	use HasUserStamps;
    //
    protected $fillable =[
        'subject_id',
        'template',
        'combine_subject',
        'marks_distribution',
        'total_exam_marks',
        'over_all_marks'
    ];

    public function subject()
    {
        return $this->belongsTo('App\Subject', 'subject_id');
    }
    public function examruletemplate(){

        return $this->belongsTo('App\ExamRulesTemplate','id');
    }
}
