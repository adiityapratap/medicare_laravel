<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    //
    protected $fillable = [
        'feedback',
        'teacher_id',
        'class_id',
        'student_id',
        'parent_response'
    ];

    public function teacher()
    {
        return $this->hasMany('App\Employee','id','teacher_id');
    }

    public function question()
    {
        return $this->belongsTo('App\Question', 'id','feedback');
    }    
    
    public function class(){
        return $this->belongsTo('App\IClass', 'id','class_id'); 
    }

    public function registration()
    {
        return $this->hasOne('App\Registration','student_id','student_id');
    }


}
