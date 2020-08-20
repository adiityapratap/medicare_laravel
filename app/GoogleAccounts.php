<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoogleAccounts extends Model
{
    public $timestamps = false;
    /*
    * The attributes that are mass assignable.
    *
    * @var array
    */
   protected $fillable = [
       'email', 'refresh_token'
   ];
}
