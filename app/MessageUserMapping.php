<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MessageUserMapping extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $table = 'message_user_mappings';
    protected $fillable = [
        'message_id', 'section_id', 'student_id', 'staff_id', 'all'
    ];

    public function message()
    {
        return $this->belongsTo('App\MessageNotification', 'message_id');
    }

    public function section()
    {
        return $this->belongsTo('App\Section', 'section_id');
    }

    public function student()
    {
        return $this->belongsTo('App\Studnet', 'student_id');
    }

    public function staff()
    {
        return $this->belongsTo('App\User', 'staff_id');
    }
}
