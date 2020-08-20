<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class voiceLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sender_id', 'to', 'status','description','detail','message_id',
    ];


    public function message()
    {
        return $this->hasOne('App\MessageNotification', 'id', 'message_id');
    }
}
