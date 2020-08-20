<?php

namespace App\Http\Controllers\Backend;

use App\AppMeta;
use App\CircularNotification;
use App\CircularUserMapping;
use App\Mail\CircularNotificationMail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Employee;
use App\Registration;
use App\Role;
use App\Section;
use App\Student;
use App\User;
use App\UserRole;
use App\Http\Helpers\AppHelper;
use App\Http\Helpers\SmsHelper;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    //
    public function getAnnouncementCreate(){
        $roles = Role::where('id', '<>', AppHelper::USER_ADMIN)->get();
        $data = [];
        $sections = Section::with(['class' => function($query){
            $query->select('name','id');
        }])
            ->select('id','name','class_id')
            ->orderBy('class_id','asc')
            ->orderBy('name','asc')
            ->get();
        foreach ($roles as $value) {
            if($value->name == 'Student'){
                $sectionsIds = Section::with(['class' => function($query){
                    $query->select('name','id');
                }])
                    ->select('id','name','class_id')
                    ->orderBy('class_id','asc')
                    ->orderBy('name','asc')
                    ->pluck('id', 'id')->toArray();

                $sectionUserIds = Registration::with(['class','section','info'])
                    ->where('status', '1')->whereIn('section_id', $sectionsIds)->orderBy('section_id','asc')->get();
                $data[$value->name] = $sectionUserIds;
            } else {
                $systemUsers = UserRole::where('role_id', $value->id)->get();
                $ids = $systemUsers->map(function ($ur) use ($systemUsers) {
                    return $ur->user_id;
                });
                $users = User::where('status', '1')->whereIn('id', $ids)->get();
                $data[$value->name] = $users;
            }

        }

        $user = null;
        $role = null;
        return view('backend.announcement.create', compact('roles','user', 'role','data','sections'));
    }


    public function storeSendAnnouncement(Request $request){
        ini_set('max_execution_time', 1800);
        $data = $request->all();
        $phoneNumbers = array();
        $emails = array();
        $selectedStudents = array();
        $selectedEmployees = array();

        if (!isset($data['section']) && !isset($data['students']) && !isset($data['users'])) {
            return redirect()->back()
                ->with("error", 'Please select any one of the class or student or staff');

        }

        $imgStorePath = "public/Circular/circular_".date('YmdHis');

        $sectionUserIds =   array();
        if (array_key_exists('section', $data) ){
            $sectionUserIds = Registration::where('status', '1')->whereIn('section_id', $data['section'])->pluck('student_id', 'student_id')->toArray();
            $selectedStudents = Student::select('phone_no', 'father_phone_no', 'mother_phone_no', 'guardian_phone_no','email')
                ->where('status', '1')->whereIn('id', $sectionUserIds)->get()->all();
        }

        if (array_key_exists('users', $data) ){
            $selectedEmployees = Employee::select('phone_no','id', 'email')->where('status', '1')->whereIn('user_id', $data['users'])->get()->all();
        }

        if (array_key_exists('students', $data) ){
            $selectedStudents = Student::select('id','phone_no', 'father_phone_no', 'mother_phone_no', 'guardian_phone_no','email')
                ->where('status', '1')->whereIn('id', $data['students'])->get()->all();
        }


        $settings = AppMeta::select('meta_value')->where('meta_key','circular_notification')->first();

//        dd($settings->meta_value);

        if($settings->meta_value == 1){
            foreach($selectedStudents as $st) {
                $cellNumber = AppHelper::validateIndianCellNo($st->father_phone_no);
                if(!$cellNumber) {
                    $cellNumber = AppHelper::validateIndianCellNo($st->mother_phone_no);
                    if(!$cellNumber) {
                        $cellNumber = AppHelper::validateIndianCellNo($st->guardian_phone_no);
                        if(!$cellNumber) {
                            $cellNumber = AppHelper::validateIndianCellNo($st->phone_no);
                        }
                    }
                }
                if($cellNumber){
                    array_push($phoneNumbers, $cellNumber);
                }
            }

            foreach($selectedEmployees as $value) {

                array_push($phoneNumbers, $value->phone_no);
            }
//            return dd($phoneNumbers);
            if (! empty($phoneNumbers)) {
                $phoneNumbers = array_filter($phoneNumbers);
                $phoneNumbers = array_unique($phoneNumbers);
                $phoneNumbers = array_values($phoneNumbers);

                $gateway = AppMeta::where('id', AppHelper::getAppSettings('message_center_gateway'))->first();

                $gateway = json_decode($gateway->meta_value);

                // send sms via helper
                $smsHelper = new SmsHelper($gateway);
                $smsHelper->sendSms($phoneNumbers, $data['circular_message']);

            } else {
                return redirect()->back()
                    ->with("error", 'No Phone numbers found');
            }
        }else{

            foreach($selectedStudents as $st) {
                if(strlen($st->email)){
                    array_push($emails, $st->email);
                }
            }

            foreach($selectedEmployees as $value) {
                array_push($emails, $value->email);
            }
            if (! empty($emails)) {
                $subject = $data['title'];
                $message =$data['circular_message'];
                foreach ($emails as $single_email){
                    try{
                        Mail::to($single_email)->send(new CircularNotificationMail($subject, $message));

                    }catch(Exception $e){
                        Log::error($e);
                    }
                }
            } else {
                return redirect()->back()
                    ->with("error", 'No email found for students.');
            }
        }


        DB::beginTransaction();
        try {
            $messageNotifications = CircularNotification::create(
                [
                    'title' => $data['title'],
//                    'description' => $data['description'],
                    'circular_type' => $data['circular_type'],
                    'circular_message' => $data['circular_message'],
                ]
            );

            if($request->hasFile('file_path')) {
//            $storagepath = $request->file('file_path')->store($imgStorePath);
//            $fileName = basename($storagepath);
//            $data['file_path'] = $storagepath;
                $messageNotifications->addMedia($request->file('file_path'))->toMediaCollection(config('app.name').'/Announcement/','s3');
            }


            $circularId = $messageNotifications->id;

            if (!empty($data)) {
                $mapping_data = [];
                if (isset($data['all_user'])) {
                    array_push($mapping_data, array('circular_id' => $circularId, 'all' => 1));
                } else {
                    if (isset($data['section'])) {
                        foreach ($data['section'] as $value) {
                            array_push($mapping_data, array('circular_id' => $circularId, 'section_id' => $value));
                        }
                    }

                    if (isset($data['students'])) {
                        foreach ($data['students'] as $value) {
                            array_push($mapping_data, array('circular_id' => $circularId, 'student_id' => $value));
                        }
                    }

                    if (isset($data['users'])) {
                        foreach ($data['users'] as $value) {
                            array_push($mapping_data, array('circular_id' => $circularId, 'staff_id' => $value));
                        }
                    }
                }

                if (!empty($mapping_data)) {
                    foreach ($mapping_data as $data) {
                        CircularUserMapping::create($data);
                    }
                }
            }
            DB::commit();
            return redirect()->route('all-sent-announcement')->with("success","Send successfully");
        }
        catch(\Exception $e){
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
            return $message;
            return redirect()->route('send-announcement')->with("error",$message);
        }
    }

    public function listAllAannouncement(){

        $all_circular_notifications = CircularNotification::select('id', 'title', 'circular_message', 'created_at')
            ->where(['circular_type'=>'announcement'])->orderBy('id','desc')->get()->toArray();

        return view('backend.announcement.announcement_list', compact('all_circular_notifications'));
    }
}
