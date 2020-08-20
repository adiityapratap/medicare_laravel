<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\AppHelper;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Illuminate\Support\Arr;

class BusAttendance extends Model
{
    use SoftDeletes;
   use HasUserStamps;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'academic_year_id',
        'bus_id',
        'registration_id',
        'attendance_date',
        'in_time',
        'status',
        'present'
    ];


    protected $dates = ['attendance_date','in_time'];


    public function student()
    {
        return $this->belongsTo('App\Registration', 'registration_id');
    }

    public function setAttendanceDateAttribute($value)
    {
        $this->attributes['attendance_date'] = Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
    }

    public function getAttendanceDateAttribute($value)
    {
        return Carbon::parse($value)->format('d/m/Y');

    }

    public function getPresentAttribute($value)
    {
        return Arr::get(AppHelper::ATTENDANCE_TYPE, $value);
    }
}
