<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CircularUserMapping extends Model
{
    // protected $table = 'message_user_mappings';
    protected $fillable = [
        'circular_id', 'section_id', 'student_id', 'staff_id', 'all','is_read',
    ];

    public function circular()
    {
        return $this->belongsTo('App\CircularNotification', 'circular_id');
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
