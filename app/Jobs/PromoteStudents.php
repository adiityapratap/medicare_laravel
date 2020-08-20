<?php

namespace App\Jobs;

use Log;
use App\Template;
use App\AppMeta;
use App\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Http\Helpers\AppHelper;
use App\Jobs\ProcessSms;
use App\Mail\PromoteStudentsMail;

class PromoteStudents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    private $studentIDs = [];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($studentIDs)
    {
        $this->studentIDs = explode(',', $studentIDs);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $sendNotification = AppHelper::getAppSettings('promote_students_notification');
        if($sendNotification != "0") {

            if($sendNotification == "1"){
                //then send sms notification
                //get sms gateway information
                $gateway = AppMeta::where('id', AppHelper::getAppSettings('promote_students_gateway'))->first();
                if(!$gateway){
                    Log::channel('studentadmissionlog')->error("SMS Gateway not setup!");
                    return;
                }

                $this->makeNotificationJob($gateway);

            }

            if($sendNotification == "2"){
                //then send email notification
                $this->makeNotificationJob();
            }
        }
    }

    private function makeNotificationJob($gateway=null) {
        //decode if have $gateway
        if($gateway){
            $gateway = json_decode($gateway->meta_value);
        }

        //pull students
        $students = $this->getStudents();

        $template = Template::where('id', AppHelper::getAppSettings('promote_students_template'))->first();
        if(!$template){
            Log::channel('studentadmissionlog')->error("Template not setup!");
            return;
        }

        foreach ($students as $student) {
            $keywords = [];
            $keywords['regi_no'] = $student->regi_no;
            $keywords['roll_no'] = $student->roll_no;
            $keywords['class'] = $student->class->name;
            $keywords['section'] = $student->section->name;
            $studentData = $student->toArray();
            $keywords = array_merge($keywords ,$studentData['student']);

            Log::info($keywords);
            $message = $template->content;
            foreach ($keywords as $key => $value) {
                if(!is_array($value) && !is_array($key)){
                    $message = str_replace('{{' . $key . '}}', $value, $message);
                }
            }

            if($gateway) {
				$cellNumber = AppHelper::validateIndianCellNo($studentData['student']['father_phone_no']);
				if(!$cellNumber) {
					$cellNumber = AppHelper::validateIndianCellNo($studentData['student']['mother_phone_no']);
					if(!$cellNumber) {
					   $cellNumber = AppHelper::validateIndianCellNo($studentData['student']['guardian_phone_no']);
					   if(!$cellNumber) {
						  $cellNumber = AppHelper::validateIndianCellNo($studentData['student']['phone_no']);
					   }
				   }
				}
                // Log::info([$cellNumber, $message]);
                if($cellNumber) {
                    ProcessSms::dispatch($gateway, array($cellNumber), $message)->onQueue('sms');
                } else {
                    Log::channel('smsLog')->error("Invalid Cell No! ".$cellNumber);
                }

            } else {
                // make email notification jobs here
                //check if have email for this student
                if(strlen($student->email)) {
                    $emailValidator = Validator::make(['email' => $student->email], [ 'email' => 'email']);
                    if($emailValidator->fails()) {
                        Log::channel('studentadmissionlog')->error("Student \" ".$student->name ."\" has invalid email address!");
                    } else {
                        //send to a job handler
                        $emailBody = (new PromoteStudentsMail($message))->onQueue('email');
                        Mail::to($student->email)->queue($emailBody);
                    }
                } else {
                    Log::channel('studentadmissionlog')->error("Student \" ".$student->name ."\" has no email address!");
                }
            }
        }
    }

    private function getStudents() {
        return Registration::whereIn('id', $this->studentIDs)
            ->where('status', AppHelper::ACTIVE)
            ->with(['class' => function($query) {
                $query->select('name','id');
            }])
            ->with(['section' => function($query) {
                $query->select('name','id');
            }])
            ->with('student')
            ->select('id','regi_no','roll_no','student_id','class_id','section_id')
            ->get();
    }

}
