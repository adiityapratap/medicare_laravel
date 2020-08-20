<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class UserRole extends Model
{
    use SoftDeletes;
   use HasUserStamps;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'role_id'
    ];


    public function user()
    {
        return $this->hasMany('App\User');
    }

    public function role()
    {
        return $this->hasMany('App\Role');
    }
}
