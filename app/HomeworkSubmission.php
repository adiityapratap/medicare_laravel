<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class HomeworkSubmission extends Model implements HasMedia
{
    
	use HasMediaTrait;

	protected $table = 'homework_submissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'homework_id', 'student_id', 'attachment', 'status', 'count'
    ];

}
