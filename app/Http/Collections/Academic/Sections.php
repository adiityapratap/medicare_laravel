<?php

namespace App\Http\Collections\Academic;

use Illuminate\Http\Resources\Json\ResourceCollection;
Use App\Http\Resources\Academic\IClass;
use App\Http\Resources\User\Employee;

class Sections extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($section){
                return [
                'id' => $section->id,
                'name' => $section->name,
                'capacity' => $section->capacity,
                'class_id' => $section->class_id,
                'teacher_id' => $section->teacher_id,
                'note' => $section->note,
                'status' => $section->status,
                'iclass' => new IClass($section->class),
                'teacher' => new Employee($section->teacher),
                'created_at' => $section->created_at,
                'updated_at' => $section->updated_at,
            ];
        });
    }
}
