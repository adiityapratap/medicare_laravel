<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class Role extends Model
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
        'deletable',
    ];


    public function permissions() {
        return $this->belongsToMany(Permission::class,'roles_permissions')->withTimestamps();
    }

}
