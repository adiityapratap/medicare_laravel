<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Permissions\HasPermissionsTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class ChapterTopic extends Model implements HasMedia
{
	use HasMediaTrait;
    use HasPermissionsTrait;
    use SoftDeletes;
    use HasUserStamps;

    protected $table='topics';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'chapter_id', 'title', 'description', 'status'
    ];


    public function chapter()
    {
        return $this->belongsTo('App\Chapters', 'chapter_id');
    }
}