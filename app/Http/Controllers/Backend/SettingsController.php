<?php

namespace App\Http\Controllers\Backend;

use App\AcademicCalendar;
use App\AcademicYear;
use App\Grade;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Template;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\AppMeta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\MessageNotification;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /**
     * institute setting section content manage
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function institute(Request $request)
    {


        //for save on POST request
        if ($request->isMethod('post')) {

            //validate form
            $messages = [
                'logo.max' => 'The :attribute size must be under 1MB.',
                'logo_small.max' => 'The :attribute size must be under 512kb.',
                'logo.dimensions' => 'The :attribute dimensions max be 230 X 50.',
                'logo_small.dimensions' => 'The :attribute dimensions max be 50 X 50.',
                'favicon.max' => 'The :attribute size must be under 512kb.',
                'favicon.dimensions' => 'The :attribute dimensions must be 32 X 32.',
            ];

            $rules = [
                'name' => 'required|min:5|max:255',
                'short_name' => 'required|min:3|max:255',
                'logo' => 'mimes:jpeg,jpg,png|max:1024|dimensions:max_width=230,max_height=50',
                'logo_small' => 'mimes:jpeg,jpg,png|max:512|dimensions:max_width=50,max_height=50',
                'favicon' => 'mimes:png|max:512|dimensions:min_width=32,min_height=32,max_width=32,max_height=32',
                'establish' => 'min:4|max:255',
                'website_link' => 'max:255',
                'email' => 'nullable|email|max:255',
                'phone_no' => 'required|min:8|max:15',
                'address' => 'required|max:500',
//                'language' => 'required|min:2',
                'weekends' => 'required|array',
                'morning_start' => 'required|max:8|min:7',
                'morning_end' => 'required|max:8|min:7',
                'day_start' => 'required|max:8|min:7',
                'day_end' => 'required|max:8|min:7',
                'evening_start' => 'required|max:8|min:7',
                'evening_end' => 'required|max:8|min:7',
                'student_attendance_notification' => 'required|integer',
                'employee_attendance_notification' => 'required|integer',
                'institute_type' => 'required|integer',
                'student_idcard_template' => 'required|integer',
                'employee_idcard_template' => 'required|integer',
                'result_default_grade_id' => 'required|integer',
                'homework_notification' => 'required|integer',
                'study_certificate_template' => 'required|integer',
                'study_certificate_template_BG' => 'required',
                'admit_card_template' => 'required|integer',
                'admit_card_template_BG' => 'required',
            ];

            // if(AppHelper::getInstituteCategory() != 'college') {
                $rules[ 'academic_year'] ='required|integer';
            // }
            $this->validate($request, $rules, $messages);

            if($request->hasFile('logo')) {
                $storagepath = $request->file('logo')->store('public/logo');
                $fileName = basename($storagepath);
                $data['logo'] = $fileName;

                //if file chnage then delete old one
                $oldFile = $request->get('oldLogo','');
                if( $oldFile != ''){
                    $file_path = "public/logo/".$oldFile;
                    Storage::delete($file_path);
                }
            }
            else{
                $data['logo'] = $request->get('oldLogo','');
            }

            if($request->hasFile('logo_small')) {
                $storagepath = $request->file('logo_small')->store('public/logo');
                $fileName = basename($storagepath);
                $data['logo_small'] = $fileName;

                //if file chnage then delete old one
                $oldFile = $request->get('oldLogoSmall','');
                if( $oldFile != ''){
                    $file_path = "public/logo/".$oldFile;
                    Storage::delete($file_path);
                }
            }
            else{
                $data['logo_small'] = $request->get('oldLogoSmall','');
            }

            if($request->hasFile('favicon')) {
                $storagepath = $request->file('favicon')->store('public/logo');
                $fileName = basename($storagepath);
                $data['favicon'] = $fileName;

                //if file chnage then delete old one
                $oldFile = $request->get('oldFavicon','');
                if( $oldFile != ''){
                    $file_path = "public/logo/".$oldFile;
                    Storage::delete($file_path);
                }
            }
            else{
                $data['favicon'] = $request->get('oldFavicon','');
            }

            $data['name'] = $request->get('name');
            $data['short_name'] = $request->get('short_name');
            $data['establish'] = $request->get('establish');
            $data['website_link'] = $request->get('website_link');
            $data['email'] = $request->get('email');
            $data['phone_no'] = $request->get('phone_no');
            $data['address'] = $request->get('address');

            //now crate
            AppMeta::updateOrCreate(
                ['meta_key' => 'institute_settings'],
                ['meta_value' => json_encode($data)]
            );

            // File upload

            AppMeta::updateOrCreate(
                ['meta_key' => 'allow_fileupload'],
                ['meta_value' => $request->has('allow_fileupload') ? 1 : 0]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'allow_s3upload'],
                ['meta_value' => $request->has('allow_s3upload') ? 1 : 0]
            );

            // if(AppHelper::getInstituteCategory() != 'college') {
                AppMeta::updateOrCreate(
                    ['meta_key' => 'academic_year'],
                    ['meta_value' => $request->get('academic_year', 0)]
                );
            // }

            AppMeta::updateOrCreate(
                ['meta_key' => 'frontend_website'],
                ['meta_value' => $request->has('frontend_website') ? 1 : 0]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'language'],
                ['meta_value' => $request->get('language', 'en')]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'disable_language'],
                ['meta_value' => $request->has('disable_language') ? 1 : 0]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'institute_type'],
                ['meta_value' => $request->get('institute_type', 1)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'trustname'],
                ['meta_value' => $request->get('trustname', "")]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'trustaddress'],
                ['meta_value' => $request->get('trustaddress', "")]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'otherorgname'],
                ['meta_value' => $request->get('orgname', "")]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'otherorgaddr'],
                ['meta_value' => $request->get('orgaddress', "")]
            );

            $shiftData = [
                'Morning' => [
                    'start' => Carbon::createFromFormat('h:i a', $request->get('morning_start','12:00 am'))->format('H:i:s'),
                    'end' => Carbon::createFromFormat('h:i a', $request->get('morning_end','12:00 am'))->format('H:i:s'),
                ],
                'Day' => [
                    'start' => Carbon::createFromFormat('h:i a', $request->get('day_start','12:00 am'))->format('H:i:s'),
                    'end' => Carbon::createFromFormat('h:i a', $request->get('day_end','12:00 am'))->format('H:i:s'),
                ],
                'Evening' => [
                    'start' => Carbon::createFromFormat('h:i a', $request->get('evening_start','12:00 am'))->format('H:i:s'),
                    'end' => Carbon::createFromFormat('h:i a', $request->get('evening_end','12:00 am'))->format('H:i:s'),
                ]
            ];

            AppMeta::updateOrCreate(
                ['meta_key' => 'shift_data'],
                ['meta_value' => json_encode($shiftData)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'weekends'],
                ['meta_value' => json_encode($request->get('weekends',[]))]
            );

            $transzones = array();
            $feezones = explode("\n", $request->get('fee_trans_zones', ""));
            foreach ($feezones as $zones) {
                $zone = trim($zones);
                if (!empty($zone)) {
                    list($key, $name) = explode('|', $zone);
                    $transzones[$key] = $name;
                }
            }

            AppMeta::updateOrCreate(
                ['meta_key' => 'fee_trans_zones'],
                ['meta_value' => json_encode($transzones)]
            );

            $feecats = array();
            $feecategories = explode("\n", $request->get('fee_reciept_prefix', ""));
            foreach ($feecategories as $cats) {
                $cat = trim($cats);
                if (!empty($cat)) {
                    list($key, $name) = explode('|', $cat);
                    $feecats[$key] = $name;
                }
            }

            AppMeta::updateOrCreate(
                ['meta_key' => 'fee_reciept_prefix'],
                ['meta_value' => json_encode($feecats)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'default_reciept_prefix'],
                ['meta_value' => $request->get('default_reciept_prefix', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'fee_payment_notification'],
                ['meta_value' => $request->get('fee_payment_notification', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'hide_outstanding_amount'],
                ['meta_value' => $request->has('hide_outstanding_amount') ? 1 : 0]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'show_school_logo'],
                ['meta_value' => $request->has('show_school_logo') ? 1 : 0]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'show_discount'],
                ['meta_value' => $request->has('show_discount') ? 1 : 0]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'use_academic_prefix'],
                ['meta_value' => $request->has('use_academic_prefix') ? 1 : 0]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'message_center_notification'],
                ['meta_value' => $request->get('message_center_notification', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'message_center_gateway'],
                ['meta_value' => $request->get('sms_gateway_MC', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'voice_call_center_gateway'],
                ['meta_value' => $request->get('voice_call_notification', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'student_attendance_notification'],
                ['meta_value' => $request->get('student_attendance_notification', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'employee_attendance_notification'],
                ['meta_value' => $request->get('employee_attendance_notification', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'homework_notification'],
                ['meta_value' => $request->get('homework_notification', 0)]
            );

            //if send notification then add settings
            AppMeta::updateOrCreate(
                ['meta_key' => 'fee_payment_gateway'],
                ['meta_value' => $request->get('sms_gateway_PM', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'fee_payment_template'],
                ['meta_value' => $request->get('notification_template_PM', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'circular_message_gateway'],
                ['meta_value' => $request->get('sms_gateway_CC', 0)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'student_attendance_gateway'],
                ['meta_value' => $request->get('sms_gateway_St', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'student_attendance_template'],
                ['meta_value' => $request->get('notification_template_St', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'employee_attendance_gateway'],
                ['meta_value' => $request->get('sms_gateway_Emp', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'employee_attendance_template'],
                ['meta_value' => $request->get('notification_template_Emp', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'student_idcard_template'],
                ['meta_value' => $request->get('student_idcard_template', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'employee_idcard_template'],
                ['meta_value' => $request->get('employee_idcard_template', 0)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'result_default_grade_id'],
                ['meta_value' => $request->get('result_default_grade_id', 0)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'homework_notification_gateway'],
                ['meta_value' => $request->get('sms_gateway_HW', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'circular_notification'],
                ['meta_value' => $request->get('circular_notification', 0)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'study_certificate_template'],
                ['meta_value' => $request->get('study_certificate_template', 0)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'admit_card_template'],
                ['meta_value' => $request->get('admit_card_template', 0)]
            );
            
            AppMeta::updateOrCreate(
                ['meta_key' => 'study_certificate_template_BG'],
                ['meta_value' => $request->get('study_certificate_template_BG', 0)]
            );
            
            AppMeta::updateOrCreate(
                ['meta_key' => 'admit_card_template_BG'],
                ['meta_value' => $request->get('admit_card_template_BG', 0)]
            );
            
            AppMeta::updateOrCreate(
                ['meta_key' => 'exam_result_sms_template'],
                ['meta_value' => $request->get('exam_result_sms_template', 0)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'pre_admission_interview_notification'],
                ['meta_value' => $request->get('pre_admission_interview_notification', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'pre_admission_interview_gateway'],
                ['meta_value' => $request->get('sms_gateway_PA', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'pre_admission_interview_template'],
                ['meta_value' => $request->get('notification_template_PA', 0)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'promote_students_notification'],
                ['meta_value' => $request->get('promote_students_notification', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'promote_students_gateway'],
                ['meta_value' => $request->get('sms_gateway_SP', 0)]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'promote_students_template'],
                ['meta_value' => $request->get('notification_template_SP', 0)]
            );
            //echo '<pre>';print_r($request->get('attendance_type', 0));die;
            AppMeta::updateOrCreate(
                ['meta_key' => 'attendance_type'],
                ['meta_value' => $request->get('attendance_type', 0)]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'number_of_session'],
                ['meta_value' => $request->get('number_of_session', 0)]
            );
            $attendance_sessions = array();
            $attendance_session_to = (!empty($request->get('attendance_session_to', "")) ? $request->get('attendance_session_to', "") :'');
            $attendance_session_from = (!empty($request->get('attendance_session_from', "")) ? $request->get('attendance_session_from', "") :'');
            if(!empty($attendance_session_from)) {
                foreach($attendance_session_from as $key=>$value) {
                    $to = (!empty($attendance_session_to[$key])) ? $attendance_session_to[$key] : '';
                    $session = array();
                    $session['session_no'] = $key+1;
                    $session['from'] = Carbon::createFromFormat('h:i a', $value)->format('H:i:s');
                    $session['to'] = Carbon::createFromFormat('h:i a', $to)->format('H:i:s');
                    $attendance_sessions[] = $session;
                }
            }
            AppMeta::updateOrCreate(
                ['meta_key' => 'attendance_sessions'],
                ['meta_value' => (!empty($attendance_sessions)) ? json_encode($attendance_sessions) : '']
            );

            Cache::forget('app_settings');

            //now notify the admins about this record
            $msg = "Institute settings updated by ".auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            return redirect()->route('settings.institute')->with('success', 'Setting updated!');
        }

        //for get request
        $settings = AppMeta::where('meta_key', 'institute_settings')->select('meta_key','meta_value')->first();
        $info = null;
        if($settings) {
            $info = json_decode($settings->meta_value);
        }


        $settings = AppMeta::select('meta_key','meta_value')->get();

        $metas = [];
        foreach ($settings as $setting){
            $metas[$setting->meta_key] = $setting->meta_value;
        }

        $catlist = [];
        if(isset($metas['fee_reciept_prefix'])){
            $feecats = json_decode($metas['fee_reciept_prefix']);
            foreach($feecats as $key => $name){
                array_push($catlist, "$key|$name");
            }
        }
        $metas['fee_reciept_prefix'] = implode("\n", $catlist);

        $zonelist = [];
        if(isset($metas['fee_trans_zones'])){
            $transzones = json_decode($metas['fee_trans_zones']);
            foreach($transzones as $key => $name){
                array_push($zonelist, "$key|$name");
            }
        }
        $metas['fee_trans_zones'] = implode("\n", $zonelist);


        //if its college then no need to setup up default academic year
        $academic_years = [];
        $academic_year = 0;
        if(AppHelper::getInstituteCategory() != 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
            $academic_year = isset($metas['academic_year']) ? $metas['academic_year'] : 0;
        }

        $allow_fileupload = isset($metas['allow_fileupload']) ? $metas['allow_fileupload'] : 0;
        $allow_s3upload = isset($metas['allow_s3upload']) ? $metas['allow_s3upload'] : 0;
        $frontend_website = isset($metas['frontend_website']) ? $metas['frontend_website'] : 0;
        $hide_outstanding_amount = isset($metas['hide_outstanding_amount']) ? $metas['hide_outstanding_amount'] : 0;
        $show_school_logo = isset($metas['show_school_logo']) ? $metas['show_school_logo'] : 0;
        $use_academic_prefix = isset($metas['use_academic_prefix']) ? $metas['use_academic_prefix'] : 0;
        $show_discount = isset($metas['show_discount']) ? $metas['show_discount'] : 0;
        $language = isset($metas['language']) ? $metas['language'] : 'en';
        $disable_language = isset($metas['disable_language']) ? $metas['disable_language'] : 1;
        $payment_notification = isset($metas['fee_payment_notification']) ? $metas['fee_payment_notification'] : 0;
        $message_center_message_notification = isset($metas['message_center_notification']) ? $metas['message_center_notification'] : 0;
        $voice_call_center_gateway = isset($metas['voice_call_center_gateway']) ? $metas['voice_call_center_gateway'] : 0;

        $circular_message_notification = isset($metas['circular_notification']) ? $metas['circular_notification'] : 0;
        $attendance_type = isset($metas['attendance_type']) ? $metas['attendance_type'] : 'daily_attendance';
        $number_of_session = isset($metas['number_of_session']) ? $metas['number_of_session'] : '0';
        $attendance_sessions = isset($metas['attendance_sessions']) ? json_decode($metas['attendance_sessions']) : '0';
        $student_attendance_notification = isset($metas['student_attendance_notification']) ? $metas['student_attendance_notification'] : 0;
        $employee_attendance_notification = isset($metas['employee_attendance_notification']) ? $metas['employee_attendance_notification'] : 0;
        $institute_type = isset($metas['institute_type']) ? $metas['institute_type'] : 1;
        $homework_notification = isset($metas['homework_notification']) ? $metas['homework_notification'] : 0;
        $pre_admission_interview_notification = isset($metas['pre_admission_interview_notification']) ? $metas['pre_admission_interview_notification'] : 0;
        $promote_students_notification = isset($metas['promote_students_notification']) ? $metas['promote_students_notification'] : 0;

        $weekends = isset($metas['weekends']) ? json_decode($metas['weekends'], true) : [-1];
        //format shifting data
        if(isset($metas['shift_data'])) {
            $shiftData = json_decode($metas['shift_data'], true);
            $formatedShiftData = [];
            foreach ($shiftData as $shift => $times){
                $formatedShiftData[$shift] = [
                    'start' => Carbon::parse($times['start'])->format('h:i a'),
                    'end' => Carbon::parse($times['end'])->format('h:i a')
                ];
            }

            $metas['shift_data'] = $formatedShiftData;
        }

        //get idcard templates
        // AppHelper::TEMPLATE_TYPE  1=SMS , 2=EMAIL, 3=Id card
        $studentIdcardTemplates = Template::whereIn('type',[3])->where('role_id', AppHelper::USER_STUDENT)
            ->pluck('name','id')->prepend('None', 0);
        $employeIdcardTemplates = Template::whereIn('type',[3])->where('role_id', AppHelper::USER_TEACHER)
            ->pluck('name','id')->prepend('None', 0);

        $studyCertificateTemplates = Template::whereIn('type',[4])->pluck('name','id')->prepend('None', 0);
        $admitCardTemplates = Template::whereIn('type',[6])->pluck('name','id')->prepend('None', 0);
        $examResultTemplates = Template::whereIn('module',[2])->pluck('name','id')->prepend('None', 0);

        $student_idcard_template = $metas['student_idcard_template'] ?? 0;
        $employee_idcard_template = $metas['employee_idcard_template'] ?? 0;

        $study_certificate_template = $metas['study_certificate_template'] ?? 0;
        $study_certificate_template_BG = $metas['study_certificate_template_BG'] ?? '';

        $exam_result_sms_template = $metas['exam_result_sms_template'] ?? 0;
        $admit_card_template = $metas['admit_card_template'] ?? 0;
        $admit_card_template_BG = $metas['admit_card_template_BG'] ?? '';

        //result settings
        $grades = Grade::pluck('name', 'id')->prepend('None',0);
        $grade_id = $metas['result_default_grade_id'] ?? 0;

        //get voice getway mail
        $voiceGateways = AppMeta::where('meta_key','voice_gateway')->get();
        $voiceGatewaysData = [];
        foreach ($voiceGateways as $gateway){
            $json_data = json_decode($gateway->meta_value);
            $voiceGatewaysData[$gateway->id] = $json_data->name.'['.AppHelper::VOICE_GATEWAY_LIST[$json_data->gateway].']';
        }
        return view(
            'backend.settings.institute', compact(
                'info',
                'academic_years',
                'academic_year',
                'weekends',
                'grades',
                'grade_id',
                'allow_s3upload',
                'allow_fileupload',
                'frontend_website',
                'hide_outstanding_amount',
                'show_school_logo',
                'use_academic_prefix',
                'show_discount',
                'disable_language',
                'payment_notification',
                'message_center_message_notification',
                'student_attendance_notification',
                'employee_attendance_notification',
                'institute_type',
                'language',
                'metas',
                'studentIdcardTemplates',
                'employeIdcardTemplates',
                'student_idcard_template',
                'employee_idcard_template',
                'homework_notification',
                'studyCertificateTemplates',
                'study_certificate_template',
                'study_certificate_template_BG',
                'circular_message_notification',
                'admit_card_template',
                'admit_card_template_BG',
                'admitCardTemplates',
                'examResultTemplates',
                'exam_result_sms_template',
                'pre_admission_interview_notification',
                'promote_students_notification',
                'attendance_type',
                'number_of_session',
                'attendance_sessions',
                'voiceGatewaysData',
                'voice_call_center_gateway'
            )
        );
    }



    /**
     * academic calendar settings  manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function academicCalendarIndex(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {//
            $this->validate($request, [
                'hiddenId' => 'required|integer',
            ]);

            $calendar = AcademicCalendar::findOrFail($request->get('hiddenId'));
            $calendar->delete();

            return redirect()->route('settings.academic_calendar.index')->with('success', 'Entry deleted!');
        }

        //for get request
        $year = $request->query->get('year','');
        $calendars = collect();
        if(strlen($year)) {
            $calendars = AcademicCalendar::whereYear('date_from', $year)
                ->whereYear('date_upto', $year)
                ->get();
        }

        return view('backend.settings.academic_calendar_list', compact('calendars','year'));
    }

    /**
     *  academic calendar settings   manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function academicCalendarCru(Request $request, $id=0)
    {
        //for save on POST request
        if ($request->isMethod('post')) {

            $rules =  [
                'title' => 'required|max:255',
                'date_from' => 'required|min:10|max:10',
                'date_upto' => 'nullable|min:10|max:10',
                'description' => 'nullable|min:5|max:500',
            ];

            if(AppHelper::getInstituteCategory() == 'college' && $request->has('is_exam')) {
                $rules['class_id'] = 'required|array';
            }
            $this->validate($request, $rules);


            $dateFrom = Carbon::createFromFormat('d/m/Y', $request->get('date_from'));
            $dateUpto = $dateFrom;
            if(strlen($request->get('date_upto'))){
                $dateUpto = Carbon::createFromFormat('d/m/Y', $request->get('date_upto'));
            }

            if($dateUpto->lessThan($dateFrom)){
                return redirect()->back()->with('error', 'Date up-to can not be less than date from!');
            }

            $data =  [
                'title' => $request->get('title'),
                'date_from' => $dateFrom,
                'date_upto' => $dateUpto,
                'description' => $request->get('description',''),
            ];
            if($request->has('is_holiday')){
                $data['is_holiday'] = '1';
            }

            if($request->has('is_exam')){
                $data['is_exam'] = '1';

                if(AppHelper::getInstituteCategory() == 'college') {
                    $data['class_ids'] = json_encode($request->get('class_id', []));
                }

            }
            else{
                $data['is_exam'] = '0';
                $data['class_ids']  = null;
            }

            AcademicCalendar::updateOrCreate(['id' => $id], $data);

            $msg = "Calendar entry";
            $msg .= $id ? 'updated.' : 'added.';
            if($id){
                return redirect()->route('settings.academic_calendar.index')->with('success', $msg);
            }
            return redirect()->route('settings.academic_calendar.create')->with('success', $msg);
        }

        //for get request
        $classes = collect();
        $calendar = AcademicCalendar::where('id', $id)->first();
        $is_holiday = 0;
        $is_exam = 0;
        $class_id = [];
        if($calendar) {
           $is_holiday = $calendar->is_holiday;
           $is_exam = $calendar->is_exam;
           $class_id = $calendar->class_ids ? json_decode($calendar->class_ids) : [];
        }

        if(AppHelper::getInstituteCategory() == 'college') {
            $classes = IClass::where('status', AppHelper::ACTIVE)
                ->orderBy('order','asc')
                ->pluck('name', 'id');
        }

         $weekends = AppHelper::getAppSettings('weekends');

        return view('backend.settings.academic_calendar_add', compact('calendar', 'is_holiday',
            'is_exam','classes','class_id','weekends'));
    }


    /**
     * SMS Gateway settings  manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function smsGatewayIndex(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {//
            $this->validate($request, [
                'hiddenId' => 'required|integer',
            ]);

            $gateway = AppMeta::findOrFail($request->get('hiddenId'));

            // now check is gateway currently used ??
            $stGateway = AppHelper::getAppSettings('student_attendance_gateway');
            $empGateway = AppHelper::getAppSettings('employee_attendance_gateway');
            $pmGateway = AppHelper::getAppSettings('fee_payment_gateway');
            $hwGateway = AppHelper::getAppSettings('homework_notification_gateway');
            $paGateway = AppHelper::getAppSettings('pre_admission_interview_gateway');
            $spGateway = AppHelper::getAppSettings('promote_students_gateway');
            if($gateway->id == $stGateway || $gateway->id == $empGateway || $gateway->id == $pmGateway || $gateway->id == $hwGateway || $gateway->id == $paGateway || $gateway->id == $spGateway){
                return redirect()->route('settings.sms_gateway.index')->with('error', 'Can not delete it because this gateway is being used.');
            }
            if($gateway->meta_key == "sms_gateway"){
                $gateway->delete();
            }

            return redirect()->route('settings.sms_gateway.index')->with('success', 'Gateway deleted!');
        }

        //for get request
        $smsGateways = AppMeta::where('meta_key','sms_gateway')->get();

        //if it is ajax request then send json response with formated data
        if($request->ajax()){
            $data = [];
            foreach ($smsGateways as $gateway){
                $json_data = json_decode($gateway->meta_value);
                $data[] = [
                    'id' => $gateway->id,
                    'text' => $json_data->name.'['.AppHelper::SMS_GATEWAY_LIST[$json_data->gateway].']',
                ];
            }

            return response()->json($data);
        }



        return view('backend.settings.smsgateway_list', compact('smsGateways'));
    }

    /**
     *  SMS Gateway settings   manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function smsGatewayCru(Request $request, $id=0)
    {
        //for save on POST request
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'gateway' => 'required|integer',
                'name' => 'required|min:4|max:255',
                'sender_id' => 'nullable',
                'user' => 'required|max:255',
                'password' => 'nullable|max:255',
                'api_url' => 'required',
            ]);


            $data = [
                'gateway' => $request->get('gateway',''),
                'name' => $request->get('name',''),
                'sender_id' => $request->get('sender_id',''),
                'user' => $request->get('user',''),
                'password' => $request->get('password',''),
                'api_url' => $request->get('api_url',''),
            ];


            AppMeta::updateOrCreate(
                ['id' => $id],
                [
                    'meta_key' => 'sms_gateway',
                    'meta_value' => json_encode($data)
                ]
            );
            $msg = "SMS Gateway ";
            $msg .= $id ? 'updated.' : 'added.';

            if($id){
                return redirect()->route('settings.sms_gateway.index')->with('success', $msg);
            }
            return redirect()->route('settings.sms_gateway.create')->with('success', $msg);
        }

        //for get request
        $gateways = AppHelper::SMS_GATEWAY_LIST;
        $gateway_id = null;
        $gateway = AppMeta::find($id);
        if($gateway) {
            $gateway_id = (json_decode($gateway->meta_value))->gateway;
        }

        return view('backend.settings.smsgateway_add', compact('gateways', 'gateway', 'gateway_id'));
    }

    /**
     * VOICE Gateway settings  manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function voiceGatewayIndex(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {//
            $this->validate($request, [
                'hiddenId' => 'required|integer',
            ]);

            $gateway = AppMeta::findOrFail($request->get('hiddenId'));

            // now check is gateway currently used ??
            $stGateway = AppHelper::getAppSettings('student_attendance_gateway');
            $empGateway = AppHelper::getAppSettings('employee_attendance_gateway');
            $pmGateway = AppHelper::getAppSettings('fee_payment_gateway');
            $hwGateway = AppHelper::getAppSettings('homework_notification_gateway');
            $paGateway = AppHelper::getAppSettings('pre_admission_interview_gateway');
            $spGateway = AppHelper::getAppSettings('promote_students_gateway');
            if($gateway->id == $stGateway || $gateway->id == $empGateway || $gateway->id == $pmGateway || $gateway->id == $hwGateway || $gateway->id == $paGateway || $gateway->id == $spGateway){
                return redirect()->route('settings.voice_gateway.index')->with('error', 'Can not delete it because this gateway is being used.');
            }
            if($gateway->meta_key == "voice_gateway"){
                $gateway->delete();
            }

            return redirect()->route('settings.voice_gateway.index')->with('success', 'Gateway deleted!');
        }

        //for get request
        $voiceGateways = AppMeta::where('meta_key','voice_gateway')->get();

        //if it is ajax request then send json response with formated data
        if($request->ajax()){
            $data = [];
            foreach ($voiceGateways as $gateway){
                $json_data = json_decode($gateway->meta_value);
                $data[] = [
                    'id' => $gateway->id,
                    'text' => $json_data->name.'['.AppHelper::VOICE_GATEWAY_LIST[$json_data->gateway].']',
                ];
            }

            return response()->json($data);
        }



        return view('backend.settings.voicegateway_list', compact('voiceGateways'));
    }

    /**
     *  VOICE Gateway settings   manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function voiceGatewayCru(Request $request, $id=0)
    {
        //for save on POST request
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'gateway' => 'required|integer',
                'name' => 'required|min:4|max:255',
                'sender_id' => 'nullable',
                'user' => 'required|max:255',
                'password' => 'nullable|max:255',
                'api_url' => 'required',
            ]);


            $data = [
                'gateway' => $request->get('gateway',''),
                'name' => $request->get('name',''),
                'sender_id' => $request->get('sender_id',''),
                'user' => $request->get('user',''),
                'password' => $request->get('password',''),
                'api_url' => $request->get('api_url',''),
            ];


            AppMeta::updateOrCreate(
                ['id' => $id],
                [
                    'meta_key' => 'voice_gateway',
                    'meta_value' => json_encode($data)
                ]
            );
            $msg = "VOICE Gateway ";
            $msg .= $id ? 'updated.' : 'added.';

            if($id){
                return redirect()->route('settings.voice_gateway.index')->with('success', $msg);
            }
            return redirect()->route('settings.voice_gateway.create')->with('success', $msg);
        }

        //for get request
        $gateways = AppHelper::VOICE_GATEWAY_LIST;
        $gateway_id = null;
        $gateway = AppMeta::find($id);
        if($gateway) {
            $gateway_id = (json_decode($gateway->meta_value))->gateway;
        }

        return view('backend.settings.voicegateway_add', compact('gateways', 'gateway', 'gateway_id'));
    }
    /**
     * report settings  manage
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function report(Request $request)
    {

        //for save on POST request
        if ($request->isMethod('post')) {

            //validate form
            $messages = [
            ];
            $rules = [
                'background_color' => 'nullable|max:255',
                'text_color' => 'nullable|max:255',

            ];
            $this->validate($request, $rules, $messages);




            //now crate
            if($request->has('show_logo')){
                AppMeta::updateOrCreate(
                    ['meta_key' => 'report_show_logo'],
                    ['meta_value' => 1]
                );
            }
            else{
                AppMeta::updateOrCreate(
                    ['meta_key' => 'report_show_logo'],
                    ['meta_value' => 0]
                );
            }

            AppMeta::updateOrCreate(
                ['meta_key' => 'report_background_color'],
                ['meta_value' => $request->get('background_color', '')]
            );

            AppMeta::updateOrCreate(
                ['meta_key' => 'report_text_color'],
                ['meta_value' => $request->get('text_color', '')]
            );

            Cache::forget('app_settings');

            //now notify the admins about this record
            $msg = "Report settings updated by ".auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            return redirect()->route('settings.report')->with('success', 'Report setting updated!');
        }

        //for get request
        $settings = AppMeta::where('meta_key', 'like', '%report_%')->select('meta_key','meta_value')->get();
        $metas = [];
        foreach ($settings as $setting){
            $metas[$setting->meta_key] = $setting->meta_value;
        }

        $show_logo  = $metas['report_show_logo'] ?? 0;

        return view('backend.settings.report', compact('metas','show_logo'));
    }
}
