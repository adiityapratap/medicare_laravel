<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sqits\UserStamps\Concerns\HasUserStamps;

class FeeCollectionMeta extends Model {
	 /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
     use SoftDeletes;
    use HasUserStamps;
	
	protected $table = 'fee_collection_meta';
	protected $fillable = ['type', 'reference' , 'date', 'bank', 'invoicedto'];
}
