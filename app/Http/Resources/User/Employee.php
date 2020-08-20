<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class Employee extends JsonResource
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
            'user_id' => $this->user_id,
            'id_card' => $this->id_card,
            'designation' => $this->designation,
            'qualification' => $this->qualification,
            'dob' => $this->dob,
            'gender' => $this->gender,
            'religion' => $this->religion,
            'email' => $this->email,
            'phone_no' => $this->phone_no,
            'address' => $this->address,
            'joining_date' => $this->joining_date,
            'photo' => $this->photo,
            'signature' => $this->signature,
            'shift' => $this->shift,
            'duty_start' => $this->duty_start,
            'duty_end' => $this->duty_end,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
