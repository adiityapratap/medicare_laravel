<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class FeeInstallments extends Model
{
    use SoftDeletes;
	use HasUserStamps;
	

	 /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public $timestamps = true;
    
	protected $table = 'fee_installments';
	protected $fillable = ['feeitem','due_date','latefee','lftype', 'inst_type','inst_fee'];

	public function fee()
    {
        return $this->hasMany('App\FeeSetup', 'feeitem');
    }
	public function feeSignle()
    {
        return $this->hasOne('App\FeeSetup', 'feeitem');
    }
}
