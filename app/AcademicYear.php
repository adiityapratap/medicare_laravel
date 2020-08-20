<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

class AcademicYear extends Model
{
    use SoftDeletes;
   use HasUserStamps;

    protected  $dates = ['start_date', 'end_date'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['title','start_date', 'end_date', 'status'];


}
