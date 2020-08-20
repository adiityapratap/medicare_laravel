<?php

namespace App\Jobs;

use App\AppMeta;
use App\Http\Helpers\AppHelper;
use App\Mail\HomeworkCreatedMail;
use App\Homework;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Jobs\ProcessSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HomeworkCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $homeworkID = '';
    private $studentIds = [];
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($homeworkID, $studentIds)
    {
        $this->homeworkID = $homeworkID;
        $this->studentIds = $studentIds;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //check if notification need to send?
        $sendNotification = AppHelper::getAppSettings('homework_notification');
        if($sendNotification != "0") {

            if($sendNotification == "1"){
                //then send sms notification
                //get sms gateway information
                $gateway = AppMeta::where('id', AppHelper::getAppSettings('homework_notification_gateway'))->first();
                if(!$gateway){
                    Log::channel('studenthomeworklog')->error("SMS Gateway not setup!");
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

        $homeworkData = Homework::select('title', 'description')->where('id', $this->homeworkID)->first();
        $smsMessage = $homeworkData->description;
        $emailMessage['subject'] = "Homework: ".$homeworkData->title;
        $emailMessage['body'] = $homeworkData->description;

        //pull students
        $students = $this->getStudents();

        foreach ($students as $student) {
            
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

                if($cellNumber) {
                    ProcessSms::dispatch($gateway, array($cellNumber), $smsMessage)->onQueue('sms');
                } else {
                    Log::channel('smsLog')->error("Invalid Cell No! ".$student->father_phone_no);
                }

            } else {
                // make email notification jobs here
                //check if have email for this student
                if(strlen($student->email)) {
                    $emailValidator = Validator::make(['email' => $student->email], [ 'email' => 'email']);
                    if($emailValidator->fails()) {
                        Log::channel('studenthomeworklog')->error("Student \" ".$student->name ."\" has invalid email address!");
                    } else {
                        //send to a job handler
                        $emailBody = (new HomeworkCreated($emailMessage))->onQueue('email');
                        Mail::to($student->email)->queue($emailBody);
                    }
                } else {
                    Log::channel('studenthomeworklog')->error("Student \" ".$student->name ."\" has no email address!");
                }
            }
        }
    }

    private function getStudents() {
        return DB::table('students')->select('students.*')->whereIn('user_id', $this->studentIds)->where('status', AppHelper::ACTIVE)->get();
    }

}
