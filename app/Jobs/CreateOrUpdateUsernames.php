<?php

namespace App\Jobs;

use Log;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Imtigger\LaravelJobStatus\Trackable;
use App\User;
use App\AcademicYear;
use App\AppMeta;
use App\Http\Helpers\AppHelper;
use App\Http\Helpers\SmsHelper;

class CreateOrUpdateUsernames implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, Trackable;

    private $students = [];
    private $staff = [];
    private $prefix = null;
    private $infix = null;
    private $suffix = null;
    private $message = null;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($students, $staff, $prefix, $infix, $suffix, $message)
    {
        $this->prepareStatus();
        $this->students = $students;
        $this->staff = $staff;
        $this->prefix = $prefix;
        $this->infix = $infix;
        $this->suffix = $suffix;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        ini_set('max_execution_time', 300); //5 minutes
        $i = 0;
        $max = count($this->students) + count($this->staff);
        $this->setProgressMax($max);

        $this->setOutput(['total' => $max, 'other' => 'parameter']);
        $gateway = null;
        if($this->message) {
            $gateway = AppMeta::where('id', AppHelper::getAppSettings('message_center_gateway'))->first();
            $gateway = json_decode($gateway->meta_value);
        }
        if($this->prefix == 'shortname'){
            $institute = AppHelper::getAppSettings('institute_settings');
            $this->prefix = $institute['short_name'];
        }

        if($this->suffix == 'number'){
            $acYearId = '';
            $acYears = [];
            if(AppHelper::getInstituteCategory() != 'college') {
                $settings = AppHelper::getAppSettings();
                $acYearId = $settings['academic_year'];
            }
            $acYears = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
            $year = explode("-", $acYears[$acYearId]);
        }
        if(!empty($this->students)){
            foreach($this->students as $student) {
                $i++;
                if($this->suffix == 'number'){
                    $suffix = $year[0].''.$student->user_id;
                    $uname = $this->generateUserName(NULL, $this->prefix, $this->infix, $suffix, $student->user_id);
                }elseif($this->suffix == 'names') {
                    $uname = $this->generateUserName($student->name, $this->prefix, $this->infix, $suffix, $student->user_id);
                }
                User::where('id', $student->user_id)->update(
                    [
                    'username'=> $uname,
                    'password' => bcrypt($uname),
                    ]
                );
                if($this->message){
                    $keywords['name'] = $student->name;
                    $keywords['username'] = $uname;
                    $keywords['password'] = $uname;
                    $message = $this->message;
                    foreach ($keywords as $key => $value) {
                        if(is_string($value)){
                            $message = str_replace('{{' . $key . '}}', $value, $message);
                        }
                    }
                    $cellNumber = AppHelper::validateIndianCellNo($student->father_phone_no);
                    if(!$cellNumber) {
                        $cellNumber = AppHelper::validateIndianCellNo($student->mother_phone_no);
                        if(!$cellNumber) {
                            $cellNumber = AppHelper::validateIndianCellNo($student->guardian_phone_no);
                            if(!$cellNumber) {
                                $cellNumber = AppHelper::validateIndianCellNo($student->phone_no);
                            }
                        }
                    }
                    if($cellNumber){
                        //send sms via helper
                        $smsHelper = new SmsHelper($gateway);
                        $res = $smsHelper->sendSms(array($cellNumber), $message);            
                    }
                    else{
                        Log::channel('smsLog')->error("Invalid Cell No! ".$cellNumber);
                    }
                }
                $this->setProgressNow($i);
            }
        }
        if(!empty($this->staff)){
            foreach($this->staff as $emp) {
                $i++;
                if($this->suffix == 'number'){
                    $suffix = $year[0].''.$emp->user_id;
                    $uname = $this->generateUserName(NULL, $this->prefix, $this->infix, $suffix, $emp->user_id);
                }elseif($this->suffix == 'names') {
                    $uname = $this->generateUserName($emp->name, $this->prefix, $this->infix, $suffix, $emp->user_id);
                }
                User::where('id', $emp->user_id)->update(
                    [
                    'username'=> $uname,
                    'password' => bcrypt($uname)
                    ]
                );

                if($this->message){
                    $keywords['name'] = $emp->name;
                    $keywords['username'] = $uname;
                    $keywords['password'] = $uname;
                    $message = $this->message;
                    foreach ($keywords as $key => $value) {
                        if(is_string($value)){
                            $message = str_replace('{{' . $key . '}}', $value, $message);
                        }
                    }
                    $cellNumber = AppHelper::validateIndianCellNo($emp->phone_no);
                    if($cellNumber){
                        //send sms via helper
                        $smsHelper = new SmsHelper($gateway);
                        $res = $smsHelper->sendSms(array($cellNumber), $message);            
                    }
                    else{
                        Log::channel('smsLog')->error("Invalid Cell No! ".$cellNumber);
                    }
                }
                $this->setProgressNow($i);
            }
        }
        $this->setOutput(['total' => $max]);
    }

    private function generateUserName($fullname=NULL, $prefix, $infix, $suffix, $uid){
        $i = 0;
        do {
            if($fullname) {
                $splitname = explode(" ", $fullname);
                $name = $splitname[0];
                $surname = count($splitname) > 1 ? $splitname[1] : '';
                $ex = ($i == 0) ? '' : $i;
                //to produce username mtutumlu for Murat Tutumlu
                $uname = $prefix .''. $infix .''.strtolower(substr($name , 0, 3) . str_replace(array(' ', '.'), '', $surname)) . $ex;
            }else{
                $uname = $prefix .''. $infix .''. ($suffix + $i);
            }
            $i++;
            $ucount = User::withTrashed()->where('username', $uname)->where('id', '<>', $uid)->get();
            $ucount = $ucount->count();
        }while ($ucount > 0);

        return $uname;
    }
}
