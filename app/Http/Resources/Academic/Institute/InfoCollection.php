<?php

namespace App\Http\Resources\Academic\Institute;

use Illuminate\Http\Resources\Json\ResourceCollection;

class InfoCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
    
    public function with($request)
    {
        return [
            'success'   => true,
            'message'   => 'Institute users/sections retireved successfully!',
        ];
    }
}
