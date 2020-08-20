<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class Event extends Model
{
    use SoftDeletes;
   use HasUserStamps;

    protected $dates = [
        'event_time'
    ];


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'event_time',
        'title',
        'description',
        'cover_photo',
        'cover_video',
        'tags',
        'slider_1',
        'slider_2',
        'slider_3',
        'slug',
    ];
}
