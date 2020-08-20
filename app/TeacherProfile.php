<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class TeacherProfile extends Model
{

    use SoftDeletes;
   use HasUserStamps;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'image',
        'description',
        'designation',
        'qualification',
        'facebook',
        'google',
        'twitter',
    ];
}
