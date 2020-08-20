<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Permissions\HasPermissionsTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;


class Chapters extends Model
{   
    use HasPermissionsTrait;
    use SoftDeletes;
    use HasUserStamps;


    protected $table='chapters';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'class_id', 'subject_id', 'title', 'description', 'status'
    ];

    public function class()
    {
        return $this->belongsTo('App\IClass', 'class_id');
    }
    public function subject()
    {
        return $this->belongsTo('App\Subject', 'subject_id');
    }

    public function topics()
    {
        return $this->hasMany('App\ChapterTopic', 'chapter_id');
    }

}
