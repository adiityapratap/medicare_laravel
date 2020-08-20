<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class TimeTable extends Model
{
    use SoftDeletes;
	use HasUserStamps;
	
    public function subject()
    {
        return $this->belongsTo('App\Subject', 'subject_id');
    }
}
