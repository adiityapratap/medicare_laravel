<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeeClass extends Model {
	 /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

	protected $table = 'fee_setup_class_map';
	protected $fillable = ['class_id','fee_item'];

	public $timestamps = false;

	public function class()
    {
        return $this->belongsTo('App\IClass', 'class_id');
    }
	public function setup()
    {
        return $this->belongsTo('App\FeeSetup', 'fee_item');
    }
}
