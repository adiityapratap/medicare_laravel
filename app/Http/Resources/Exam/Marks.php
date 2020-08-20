<?php

namespace App\Http\Resources\Exam;

use App\ExamRule;
use App\Http\Helpers\AppHelper;
use App\Subject;
use Illuminate\Http\Resources\Json\JsonResource;

class Marks extends JsonResource
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
            'name'      =>  $this->student->student->name,
            'regi_no'   =>  $this->student->regi_no,
            'id'        =>  $this->student->student->id,
        ];
        // $details = [];
        $mark_entry = [];
        $subjects = Subject::where('status', AppHelper::ACTIVE)
            ->where('class_id', $this->class_id)
            ->pluck('name', 'id');
        $examRule = ExamRule::where('exam_id',$this->exam_id)->first();
        foreach ($subjects as $key => $subject) {
            $mark_db_data = $this->where('subject_id', $key)->where('registration_id', $this->student->student->id)->first();
            if( $mark_db_data ) {
                foreach (json_decode($examRule->marks_distribution,true) as $count) {
                    $final_mark = json_decode($mark_db_data->marks, true)[$count['type']];
                    $mark_entry[AppHelper::MARKS_DISTRIBUTION_TYPES[$count['type']]] = $final_mark == -1 ? 'AB' : ($final_mark == null ? 'N/A' : $final_mark);
                    $details['marks'][$subject]['is_absent'] = $final_mark == -1;
                    $details['marks'][$subject]['is_entery_exists'] = $final_mark != null;
                    $details['marks'][$subject]['marks'] = $mark_entry;
                }
            }
        }
        return $details;
    }
}
