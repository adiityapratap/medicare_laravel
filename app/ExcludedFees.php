<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class ExcludedFees extends Model
{
	use SoftDeletes;
   use HasUserStamps;
    
    public $timestamps = true;
    
	protected $table = 'fee_excluded';
	protected $fillable = ['feeitem','student_id'];
}
