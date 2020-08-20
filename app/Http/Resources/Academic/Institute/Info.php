<?php

namespace App\Http\Resources\Academic\Institute;

use App\Http\Helpers\AppHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class Info extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $data = [];
        if(isset($this['Teacher'])) {
            $data['teachers'] =  $this['Teacher'];
        }
        if(isset($this['Student'])) {
            $data['students'] =  $this['Student'];
        }
        if(isset($this['sections'])) {
            $data['sections'] =  $this['sections'];
        }

        return $data;
    }

}
