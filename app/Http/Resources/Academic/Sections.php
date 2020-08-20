<?php

namespace App\Http\Resources\Academic;

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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'class_id' => $this->class_id,
            'teacher_id' => $this->teacher_id,
            'note' => $this->note,
            'status' => $this->status,
            'iclass' => IClass::collection($this->whenLoaded('class')),
            'teacher' => Employee::collection($this->whenLoaded('teacher')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
