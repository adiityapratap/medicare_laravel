<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class CircularNotification extends Model implements HasMedia
{
    //
    use SoftDeletes;
    use HasMediaTrait;

    protected $fillable = [
        'circular_type', 'circular_message','title','description'
    ];

    public function registration()
    {
        return $this->hasMany(CircularUserMapping::class(), 'student_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function mapping()
    {
        return $this->hasOne(CircularUserMapping::class, 'circular_id');
    }
}
