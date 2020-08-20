<?php

namespace App\Traits;

use \Exception;
use App\Registration;
use App\Role;
use App\UserRole;
use App\User;
use App\Student;
use App\Section;
use App\Http\Helpers\AppHelper;

trait InstituteUsersTrait
{
    public function getUsers($type='all') {
        $data = [];

        try{
            if($type == 'section') {
                $data['sections'] = $this->getSectionList();
                return $data;
            }

            $roles = Role::where('id', '<>', AppHelper::USER_ADMIN)->get();
            foreach ($roles as $value) {
                if ($value->name == 'Student' && ($type == 'all' || $type == 'student')) {
                    $sections = $this->getSectionList();
                    $sectionsIds = $sections->pluck('id', 'id')->toArray();

                    $data['sections'] = $sections;

                    $sectionUserIds = Registration::with(['class' => function($query){
                            $query->select('id','name');
                        }, 'section' => function($query){
                            $query->select('id','name');
                        }, 'info' => function($query){
                            $query->select('id','name');
                        }])
                        ->where('status', '1')
                        ->whereIn('section_id', $sectionsIds)
                        ->whereNull('deleted_at')
                        ->select('id', 'regi_no', 'student_id', 'class_id', 'section_id')
                        ->orderBy('section_id', 'asc')->get();
                    $data[$value->name] = $sectionUserIds;
                } else if(($value->name != 'Admin' && $value->name != 'Student' && $value->name != 'Parents') && ($type == 'all' || $type == 'staff')) {
                    $systemUsers = UserRole::where('role_id', $value->id)->get();
                    $ids = $systemUsers->map(function ($ur) use ($systemUsers) {
                        return $ur->user_id;
                    });
                    $users = User::where('status', '1')->whereIn('id', $ids)->whereNull('deleted_at')->select('id', 'name')->get()->toArray();
                    if(isset($data['Teacher'])) {
                        $data['Teacher'] = array_merge($data['Teacher'], $users);
                    }else{
                        $data['Teacher'] = $users;
                    }
                }

            }
            
            return $data;
        } catch (Exception $e) {
            throw new Exception($e);
        }
    }

    public function getSectionList() {
        return Section::with(['class' => function ($query) {
                $query->select('name', 'id');
            }])
            ->select('id', 'name', 'class_id')
            ->whereNull('deleted_at')
            ->orderBy('class_id', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }
}