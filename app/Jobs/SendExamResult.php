<?php

namespace App\Jobs;

use App\AppMeta;
use App\Template;
use App\Http\Helpers\AppHelper;
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

class SendExamResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $studentData = [];
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($studentData)
    {
        $this->studentData = $studentData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //then send sms notification
        //get sms gateway information
        $gateway = AppMeta::where('id', AppHelper::getAppSettings('student_attendance_gateway'))->first();
        if(!$gateway){
            Log::channel('studenthomeworklog')->error("SMS Gateway not setup!");
            return;
        }

        $this->makeNotificationJob($gateway);
    }

    private function makeNotificationJob($gateway=null) {
        //decode if have $gateway
        if($gateway){
            $gateway = json_decode($gateway->meta_value);
        }

        //get sms template information
        $template = Template::where('id', AppHelper::getAppSettings('exam_result_sms_template'))->first();
        if(!$template){
            Log::channel('studentabsentlog')->error("Template not setup!");
            return;
        }
        
        foreach ($this->studentData as $student) {
            $message = $template->content;
            $marks = [];
            foreach ($student->marks as $mark) {
                $total_mark = $mark->total_marks == -1 ? 'Absent': $mark->total_marks;
                array_push($marks, "{$mark->subject->name}: $total_mark");
                // array_push($marks, "{$mark->subject->name}: $total_mark Grade: {$mark->grade}");
            }

            $keywords['regi_no'] = $student->regi_no;
            $keywords['roll_no'] = $student->roll_no;
            $keywords['class'] = $student->class->name;
            $keywords['section'] = $student->section->name;
            $keywords['exam_name'] = $student->result[0]->exam->name;
            $keywords['mark_obtained'] = $student->result[0]->total_marks;
            $keywords['grade'] = $student->result[0]->grade;
            $keywords['subjects'] = implode(', ', $marks);
            $keywords['result'] = $student->result[0]->grade == 'F' ? 'Fail': 'Pass';
            $studentData = $student->info->toArray();
            $keywords = array_merge($keywords ,$studentData);

            foreach ($keywords as $key => $value) {
                if(!is_array($value) && is_string($key)){
					$message = str_replace('{{' . $key . '}}', $value, $message);
				}
            }
            
            if($gateway) {
                
                $cellNumber = AppHelper::validateIndianCellNo($student->info->father_phone_no);
                if(!$cellNumber) {
                    $cellNumber = AppHelper::validateIndianCellNo($student->info->mother_phone_no);
                    if(!$cellNumber) {
                        $cellNumber = AppHelper::validateIndianCellNo($student->info->guardian_phone_no);
                        if(!$cellNumber) {
                            $cellNumber = AppHelper::validateIndianCellNo($student->info->phone_no);
                        }
                    }
                }

                if($cellNumber) {
                    ProcessSms::dispatch($gateway, array($cellNumber), $message)->onQueue('sms');
                } else {
                    Log::channel('smsLog')->error("Invalid Cell No! ".$student->father_phone_no);
                }
            }
        }
    }

}
