<?php

namespace App\Http\Resources\Exam;

use App\Http\Helpers\AppHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class Rule extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $details =  [
            'class'         =>  [
                'id'        =>  $this->class->id,
                'name'      =>  $this->class->name,
            ],
            'subject'       =>  [
                'id'        =>  $this->subject->id,
                'name'      =>  $this->subject->name,
            ],
            'grade' =>  [
                'id'        =>  $this->grade->id,
                'name'      =>  $this->grade->name,
                'type'      =>  AppHelper::GRADE_TYPES[$this->grade->id],
            ],
            'total_exam_marks'  =>  $this->total_exam_marks,
            'over_all_pass'     =>  $this->over_all_pass,
        ];
        foreach (json_decode($this->marks_distribution,true) as $count) {
            $details['subject']['rule'][] = [
                'type' => AppHelper::MARKS_DISTRIBUTION_TYPES[$count['type']],
                'total_marks'   =>  $count['total_marks'],
                'pass_marks'   =>  $count['pass_marks'],
            ];
        }
        return $details;
    }
}
