<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PreAdmissionForm extends Model
{
    
    protected $fillable = ['field_title', 'field_name', 'initial_fields', 'mandatory', 'status'];

}
