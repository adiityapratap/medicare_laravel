<?php

namespace App;

use Sqits\UserStamps\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;



class AboutContent extends Model
{
   use HasUserStamps;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'why_content', 'about_us',
        'key_point_1_title',
        'key_point_1_content',
        'key_point_2_title',
        'key_point_2_content',
        'key_point_3_title',
        'key_point_3_content',
        'key_point_4_title',
        'key_point_4_content',
        'key_point_5_title',
        'key_point_5_content',
        'who_we_are',
        'intro_video_embed_code',
        'video_site_link'
    ];
}
