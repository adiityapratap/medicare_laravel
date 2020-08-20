<?php


namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeHistory extends Model {

	use SoftDeletes;
	use HasUserStamps;
    use LogsActivity;
	

	 /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    //only the `deleted` event will get logged automatically
    protected static $recordEvents = ['deleted', 'created'];
	 
	protected $table = 'fee_collection_history';
	protected $fillable = ['billNo','class_id','payment','student_id', 'fee_item', 'type', 'discount', 'latefee', 'payableAmount','paidAmount','dueAmount','payDate'];

    public function feeItem(){
        return $this->belongsTo('App\FeeSetup', 'fee_item');
    }

    public function payment(){
        return $this->belongsTo('App\FeeCollectionMeta', 'payment');
    }
}
