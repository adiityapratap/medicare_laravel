<?php

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;

class ExamRulesTemplate extends Model
{
    //
    use SoftDeletes;
    use HasUserStamps;
    
    protected $fillable =[
        'name', 'grade_id', 'passing_rule'
    ];
    
    public function grade()
    {
        return $this->belongsTo('App\Grade', 'grade_id');
    }
    
    public function templateclass(){

        return $this->hasMany('App\ExamRuleTempClass','template');
    }

    public function template(){

        return $this->hasMany('App\DefaultExamRule','template');
    }
    
}
