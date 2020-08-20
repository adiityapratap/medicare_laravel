<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    //
    protected $fillable = [
        'question'
    ];
    public function feedback()
    {
        return $this->hasOne('App\Feedback', 'feedback',);
    }
    
}
