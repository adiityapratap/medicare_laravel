<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class ClassProfile extends Model
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
        'slug',
        'image_sm',
        'image_lg',
        'teacher',
        'room_no',
        'capacity',
        'shift',
        'short_description',
        'description',
        'outline',
    ];
}
