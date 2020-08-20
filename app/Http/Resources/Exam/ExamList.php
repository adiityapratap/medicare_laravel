<?php

namespace App\Http\Resources\Exam;

use App\Http\Helpers\AppHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $distribution_types = [];
        foreach (json_decode($this->marks_distribution_types) as $type) {
            $distribution_types[] = AppHelper::MARKS_DISTRIBUTION_TYPES[$type];
        }
        $data = [
            'id'                        =>  $this->id,
            'name'                      =>  $this->name,
            'distribution_types'        =>  $distribution_types,
        ];

        return $data;
    }

}
