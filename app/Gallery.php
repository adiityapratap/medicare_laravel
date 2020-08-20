<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\Models\Media;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;
use Spatie\Image\Manipulations;

class Gallery extends Model implements HasMedia
{
    use HasMediaTrait;
    protected $table = 'gallary';
    protected $fillable = ['title','class_id'];

    public function registerMediaConversions(Media $media = null)
    {
        $this->addMediaConversion('thumb')
              ->width(187)
              ->height(127)
              ->sharpen(10)
              ->performOnCollections(config('app.name').'/gallary/','s3')
              ->nonQueued();
    }
}
