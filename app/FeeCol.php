<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Sqits\UserStamps\Concerns\HasUserStamps;

class FeeCol extends Model {

	use HasUserStamps;
	

	 /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

	protected $table = 'fee_collection';
	protected $fillable = ['billNo','class_id','payment','student_id', 'fee_item', 'type', 'discount', 'latefee', 'payableAmount','paidAmount','dueAmount','payDate'];

    public function feeItem(){
        return $this->belongsTo('App\FeeSetup', 'fee_item');
    }

    public function Payment(){
        return $this->belongsTo('App\FeeCollectionMeta', 'payment');
    }

    public function Student(){
        return $this->belongsTo('App\Student', 'student_id');
    }

    public function Class(){
        return $this->belongsTo('App\IClass', 'class_id');
    }
}
