<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use App\Http\Helpers\AppHelper;
use Illuminate\Support\Arr;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;
use Spatie\Activitylog\Traits\LogsActivity;


class Student extends Model implements HasMedia
{
    use SoftDeletes;
   use HasUserStamps;
    use HasMediaTrait;
    use LogsActivity;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'dob',
        'pob',
        'gender',
        'religion',
        'caste',
        'castecategory',
        'blood_group',
        'nationality',
        'nationalid',
        'photo',
        'email',
        'phone_no',
        'extra_activity',
        'note',
        'father_name',
        'father_phone_no',
        'mother_name',
        'mother_phone_no',
        'guardian',
        'guardian_phone_no',
        'present_address',
        'permanent_address',
        'monther_tongue',
        'need_transport',
        'transport_zone',
        'status',
        'class_id',
        'interview_datetime',
    ];

    public function registration()
    {
        return $this->hasMany('App\Registration', 'student_id');
    }
    public function register()
    {
        return $this->hasOne('App\Registration', 'student_id');
    }
    public function getGenderAttribute($value)
    {
        return Arr::get(AppHelper::GENDER, $value);
    }
    public function getNeedTransportAttribute($value)
    {
        return Arr::get(AppHelper::NEED_TRANSPORT, $value);
    }

    public function getReligionAttribute($value)
    {
        return Arr::get(AppHelper::RELIGION, $value);
    }

    public function getBloodGroupAttribute($value)
    {
        return Arr::get(AppHelper::BLOOD_GROUP, $value);
    }
}
