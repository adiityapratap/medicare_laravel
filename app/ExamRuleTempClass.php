<?php

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;

class ExamRuleTempClass extends Model
{
    //
    use SoftDeletes;
    use HasUserStamps;
    
    protected $fillable =[
        'class_id',
        'template'
    ];

    public function examruletemplate(){

        return $this->belongsTo('App\ExamRulesTemplate','id', 'template');
    }

    public function class(){

        return $this->hasOne('App\IClass','id', 'class_id');
    }
    
}
