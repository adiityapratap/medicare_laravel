<?php

namespace App\Jobs;

use Log;
use App\Template;
use App\AppMeta;
use App\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Http\Helpers\AppHelper;
use App\Jobs\ProcessSms;
use App\Mail\PreAdmissionMail;

class PreAdmissionInterview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    private $studentIDs = [];
    private $interviewDate = '';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($studentIDs, $interviewDate)
    {
        $this->studentIDs = explode(',', $studentIDs);
        $this->interviewDate = $interviewDate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $sendNotification = AppHelper::getAppSettings('pre_admission_interview_notification');
        if($sendNotification != "0") {

            if($sendNotification == "1"){
                //then send sms notification
                //get sms gateway information
                $gateway = AppMeta::where('id', AppHelper::getAppSettings('pre_admission_interview_gateway'))->first();
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

        $template = Template::where('id', AppHelper::getAppSettings('pre_admission_interview_template'))->first();
        if(!$template){
            Log::channel('studentadmissionlog')->error("Template not setup!");
            return;
        }

        foreach ($students as $student) {
            $keywords = [];
            // $keywords['regi_no'] = $student->regi_no;
            // $keywords['class'] = $student->class->name;
            $studentData = $student->toArray();
            $keywords = array_merge($keywords ,$studentData);
            $keywords['date'] = $this->interviewDate->toDateTimeString();

            $message = $template->content;
            foreach ($keywords as $key => $value) {
                if(!is_array($value) && !is_array($key)){
                    $message = str_replace('{{' . $key . '}}', $value, $message);
                }
            }
            
            if($gateway) {
                
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
                        $emailBody = (new PreAdmissionMail($message))->onQueue('email');
                        Mail::to($student->email)->queue($emailBody);
                    }
                } else {
                    Log::channel('studentadmissionlog')->error("Student \" ".$student->name ."\" has no email address!");
                }
            }
        }
    }

    private function getStudents() {
        return Student::whereIn('id', $this->studentIDs)->get();
    }

}
