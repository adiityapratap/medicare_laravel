<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'group',
    ];


    public function setUpdatedAt($value)
    {
      return NULL;
    }

    public function roles() {
        return $this->belongsToMany(Role::class,'roles_permissions')->whereNull('roles_permissions.deleted_at')->withTimestamps();
    }
}
