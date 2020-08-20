<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Permissions\HasPermissionsTrait;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class Homework extends Model implements HasMedia
{
	use HasMediaTrait;
    use HasPermissionsTrait;

    protected $table='homeworks';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'class_id', 'section_id', 'subject_id', 'title', 'description', 'attachment', 'submission_date', 'status'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function submission()
    {
        return $this->hasOne(HomeworkSubmission::class, 'homework_id');
    }
}
