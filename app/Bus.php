<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class Bus extends Model
{
    use SoftDeletes;
   use HasUserStamps;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'numeric_value',
        'order',
        'status',
        'note'
    ];

    public function zones()
    {
        return $this->hasMany('App\BusZones', 'bus_id');
    }

    public function attendance()
    {
        return $this->hasMany('App\BusAttendance', 'bus_id');
    }
}
