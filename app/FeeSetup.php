<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeSetup extends Model {

	use SoftDeletes;
	use HasUserStamps;
    use LogsActivity;
	

	 /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public $timestamps = true;
    
	protected $table = 'fee_setup';
	protected $fillable = ['type','title','fee','Latefee', 'installments','description'];

	public function class()
    {
        return $this->hasMany('App\FeeClass', 'fee_item');
    }
	public function classSignle()
    {
        return $this->hasOne('App\FeeClass', 'fee_item');
    }

	public function feeInstallments()
    {
        return $this->hasMany('App\FeeInstallments', 'feeitem');
    }
	public function feeInstallment()
    {
        return $this->hasOne('App\FeeInstallments', 'feeitem');
    }

	public function excludedFees()
    {
        return $this->hasMany('App\ExcludedFees', 'feeitem');
    }
}
