<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\AppHelper;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Illuminate\Support\Arr;

class Template extends Model
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
        'type',
        'module',
        'role_id',
        'content',
    ];



    public function user()
    {
        return $this->belongsTo('App\Role', 'role_id')->select('id','name');
    }

    public function getTypeAttribute($value)
    {
        return Arr::get(AppHelper::TEMPLATE_TYPE, $value);
    }


}
