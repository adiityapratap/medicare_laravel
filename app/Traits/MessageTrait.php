<?php

namespace App\Traits;

use Log;
use \Exception;
use App\Http\Helpers\AppHelper;
use App\Http\Helpers\SmsHelper;
use App\Http\Helpers\VoiceCallHelper;
use App\Employee;
use App\Student;
use App\Registration;
use App\MessageNotification;
use App\MessageUserMapping;
use App\AppMeta;
use Illuminate\Support\Facades\DB;

trait MessageTrait
{
    public function processmessage($data) {
        $phoneNumbers = array();
        $selectedStudents = array();
        $selectedEmployees = array();

        $sectionUserIds = array();
        if (array_key_exists('section', $data) && !empty($data['section'])) {
            $sectionUserIds = Registration::where('status', '1')->whereIn('section_id', $data['section'])->pluck('student_id', 'student_id')->toArray();
            $selectedStudents = Student::select('phone_no', 'father_phone_no', 'mother_phone_no', 'guardian_phone_no')
                ->where('status', '1')->whereIn('id', $sectionUserIds)->get()->all();;
        }

        if (array_key_exists('users', $data) && !empty($data['users'])) {
            $selectedEmployees = Employee::where('status', '1')->whereIn('user_id', $data['users'])->pluck('phone_no', 'id');
        }

        if (array_key_exists('students', $data) && !empty($data['students'])) {
            $selectedStudents = Student::select('phone_no', 'father_phone_no', 'mother_phone_no', 'guardian_phone_no')
                ->where('status', '1')->whereIn('id', $data['students'])->get()->all();
        }
        if(isset($data['message_category']) && $data['message_category'] == 'voice') {
            foreach ($selectedStudents as $st) {
                $cellNumber = AppHelper::validateCellNo($st->father_phone_no);
                if (!$cellNumber) {
                    $cellNumber = AppHelper::validateCellNo($st->mother_phone_no);
                    if (!$cellNumber) {
                        $cellNumber = AppHelper::validateCellNo($st->guardian_phone_no);
                        if (!$cellNumber) {
                            $cellNumber = AppHelper::validateCellNo($st->phone_no);
                        }
                    }
                }
                if ($cellNumber) {
                    array_push($phoneNumbers, $cellNumber);
                }
            }
        } else  {
            foreach ($selectedStudents as $st) {
                $cellNumber = AppHelper::validateIndianCellNo($st->father_phone_no);
                if (!$cellNumber) {
                    $cellNumber = AppHelper::validateIndianCellNo($st->mother_phone_no);
                    if (!$cellNumber) {
                        $cellNumber = AppHelper::validateIndianCellNo($st->guardian_phone_no);
                        if (!$cellNumber) {
                            $cellNumber = AppHelper::validateIndianCellNo($st->phone_no);
                        }
                    }
                }
                if ($cellNumber) {
                    array_push($phoneNumbers, $cellNumber);
                }
            }
        }


        foreach ($selectedEmployees as $phone_no) {
            array_push($phoneNumbers, $phone_no);
        }

        if (empty($phoneNumbers)) {
            throw new Exception('No Phone numbers found');
        }

        $phoneNumbers = array_filter($phoneNumbers);
        $phoneNumbers = array_unique($phoneNumbers);
        $phoneNumbers = array_values($phoneNumbers);

        // store sms data
        
        $messageId = $this->savemessage($data);
        if(!empty($messageId)) {
            if(isset($data['message_category']) && $data['message_category'] == 'voice') {
                $gateway = AppMeta::where('id', AppHelper::getAppSettings('voice_call_center_gateway'))->first();
                $gateway = (!empty($gateway->meta_value)) ? json_decode($gateway->meta_value) : "";
                $gateway->massage_id = (!empty($data['massage_id'])) ? (int)$data['massage_id'] : '';
                // make call via helper
                $voiceHelper = new VoiceCallHelper($gateway);
                $voiceHelper->makeCall($phoneNumbers, $data['message']);
            } else {
                $gateway = AppMeta::where('id', AppHelper::getAppSettings('message_center_gateway'))->first();
                $gateway = (!empty($gateway->meta_value)) ? json_decode($gateway->meta_value) : "";
                $gateway->massage_id = (!empty($messageId)) ? (int)$messageId : '';
                // send sms via helper
                $smsHelper = new SmsHelper($gateway);
                $smsHelper->sendSms($phoneNumbers, $data['message']);
            }
        }
    }

    protected function savemessage($data) {
        DB::beginTransaction();
        try {
            $messageId = '';
            if(isset($data['message_category']) && $data['message_category'] == 'voice') {
                $messageId = (!empty($data['massage_id'])) ? $data['massage_id'] : '';
            } else {
                $messageNotifications = MessageNotification::create(
                    [
                        'message' => $data['message'],
                        'message_type' => $data['message_type']
                    ]
                );
                $messageId = $messageNotifications->id;
            } 
            if (!empty($data) && !empty($messageId)) {
                $mapping_data = [];
                if (isset($data['all_user'])) {
                    array_push($mapping_data, array('message_id' => $messageId, 'all' => 1));
                } else {
                    if (isset($data['section'])) {
                        foreach ($data['section'] as $value) {
                            array_push($mapping_data, array('message_id' => $messageId, 'section_id' => $value));
                        }
                    }

                    if (isset($data['students'])) {
                        foreach ($data['students'] as $value) {
                            array_push($mapping_data, array('message_id' => $messageId, 'student_id' => $value));
                        }
                    }

                    if (isset($data['users'])) {
                        foreach ($data['users'] as $value) {
                            array_push($mapping_data, array('message_id' => $messageId, 'staff_id' => $value));
                        }
                    }
                }

                if (!empty($mapping_data)) {
                    foreach ($mapping_data as $data) {
                        MessageUserMapping::create($data);
                    }
                }
            }
            DB::commit();
            return $messageId;
        } catch (\Exception $e) {
            Log::error($e);
            DB::rollback();
            throw new Exception($e);
        }
    }
}