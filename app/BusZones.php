<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusZones extends Model
{
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'bus_id',
        'zone'
    ];

    public function student()
    {
        return $this->hasMany('App\Student', ['zone'], ['transport_zone']);
    }
}
