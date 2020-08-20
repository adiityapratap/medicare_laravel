<?php

namespace App\Http\Controllers\Backend;

use \DateTime;
use App\AcademicCalendar;
use App\AcademicYear;
use App\Employee;
use App\EmployeeAttendance;
use App\Exam;
use App\ExamRule;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Leave;
use App\Mark;
use App\Registration;
use App\Result;
use App\Section;
use App\StudentAttendance;
use App\Template;
use App\WorkOutside;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Subject;

class ReportController extends Controller
{
    /**
     *  Student ID card print.
     *
     * @return \Illuminate\Http\Response
     */
    public function studentIdcard(Request $request)
    {


        if($request->isMethod('post')){
            $templateId = $request->get('template_id', 0);
            $side = $request->get('side', 'back');
//            $howMany = intval($request->get('how_many', 0));

            $templateConfig = Template::where('id', $templateId)->where('type',3)->where('role_id', AppHelper::USER_STUDENT)->first();

            if(!$templateConfig){
                return redirect()->route('report.student_idcard')->with('error', 'Template not found!');
            }

            $templateConfig = json_decode($templateConfig->content);

            $format = "format_";
            if($templateConfig->format_id == 2){
                $format .="two";
            }
            else if($templateConfig->format_id == 3){
                $format .="three";
            }
            else {
                $format .="one";
            }

            //get institute information
            $instituteInfo = AppHelper::getAppSettings('institute_settings');


            //pull students
            //filters
            if(AppHelper::getInstituteCategory() != 'college') {
                // now check is academic year set or not
                $acYear = AppHelper::getAcademicYear();
                if (!$acYear || (int)($acYear) < 1) {

                    return redirect()->route('administrator.report.student_idcard')
                        ->with("error", 'Academic year not set yet! Please go to settings and set it.');
                }
            }
            else {
                $acYear = $request->get('academic_year',0);
            }

            $classId = $request->get('class_id');
            $sectionId = $request->get('section_id');

            $session = '';
            $validity = '';
            $totalStudent = 0;

            if($side == "front") {
                $students = Registration::where('academic_year_id', $acYear)
                    ->where('class_id', $classId)
                    ->where('section_id', $sectionId)
                    ->where('status', AppHelper::ACTIVE)
                    ->with(['student' => function ($query) {
                        $query->select('name', 'blood_group', 'id', 'photo');
                    }])
                    ->with(['class' => function ($query) {
                        $query->select('name', 'group', 'id');
                    }])
                    ->select('id', 'roll_no', 'regi_no', 'student_id','class_id', 'house')
                    ->orderBy('roll_no', 'asc')
                    ->get();


                $acYearInfo = AcademicYear::where('id', $acYear)->first();

                $session = $acYearInfo->title;
                $validity = $acYearInfo->end_date->format('Y');

                if($templateConfig->format_id == 3){
                    $validity = $acYearInfo->end_date->format('F Y');
                }
            }
            else{
                $students = Registration::where('academic_year_id', $acYear)
                    ->where('class_id', $classId)
                    ->where('section_id', $sectionId)
                    ->where('status', AppHelper::ACTIVE)
                    ->select('id', 'regi_no')
                    ->orderBy('regi_no', 'asc')
                    ->get();

                $totalStudent = count($students);
            }


            return view('backend.report.student.idcard.'.$format, compact(
                'templateConfig',
                'instituteInfo',
                'side',
                'students',
                'totalStudent',
                'session',
                'validity'
            ));

        }

        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        $academic_years = [];
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        //get templates for students
        // AppHelper::TEMPLATE_TYPE  1=SMS , 2=EMAIL, 3=Id card
        $templates = Template::whereIn('type',[3])->where('role_id', AppHelper::USER_STUDENT)->pluck('name','id');

        return view('backend.report.student.idcard.form', compact(
            'academic_years',
            'classes',
            'templates'
        ));

    }

    /**
     *  Employee ID card print.
     *
     * @return \Illuminate\Http\Response
     */
    public function employeeIdcard(Request $request)
    {



        if($request->isMethod('post')){
            $templateId = $request->get('template_id', 0);
            $side = $request->get('side', 'back');

            $templateConfig = Template::where('id', $templateId)->where('type',3)->where('role_id', AppHelper::USER_TEACHER)->first();

            if(!$templateConfig){
                return redirect()->route('report.employee_idcard')->with('error', 'Template not found!');
            }

            $templateConfig = json_decode($templateConfig->content);

            $format = "format_";
            if($templateConfig->format_id == 2){
                $format .="two";
            }
            else if($templateConfig->format_id == 3){
                $format .="three";
            }
            else {
                $format .="one";
            }

            //get institute information
            $instituteInfo = AppHelper::getAppSettings('institute_settings');


            //pull employee
            if($side == "front") {
                $employees = Employee::orderBy('id_card', 'asc')->get();

            }
            else{

                $employees = Employee::select('id_card')->orderBy('id_card', 'asc')->get();
            }


            return view('backend.report.hrm.employee.idcard.'.$format, compact(
                'templateConfig',
                'instituteInfo',
                'side',
                'employees',
                ''
            ));

        }




        //get templates for students
        // AppHelper::TEMPLATE_TYPE  1=SMS , 2=EMAIL, 3=Id card
        $templates = Template::whereIn('type',[3])->where('role_id', AppHelper::USER_TEACHER)->pluck('name','id');

        return view('backend.report.hrm.employee.idcard.form', compact(
            'templates'
        ));

    }

    /**
     * employee list print
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function employeeList(Request $request){
            $this->validate($request,[]);
            $employees = Employee::with(['role' => function($q){
                $q->select('id','name');
            }])->select(
                'id',
                'role_id',
                'name',
                'designation',
                'qualification',
                'dob',
                'gender',
                'religion',
                'email',
                'phone_no',
                'address',
                'joining_date',
                'shift',
                'duty_start',
                'duty_end',
                'status'
            )->get();
            $headerData = new \stdClass();
            $headerData->reportTitle = 'Employee List';
            $headerData->reportSubTitle = '';
        if($request->isMethod('post')) {
            return view('backend.report.hrm.employee.list_print', compact('headerData','employees'));
        }
        return view('backend.report.hrm.employee.list', compact('headerData','employees'));
    }

    public function employeeMonthlyAttendance(Request $request){
        $monthDates = NULL;
        $employees = array();
        $attendanceData = array();
        $calendarData = array();
        $employeesLeaves = array();
        $employeesWorkoutside = array();
        $headerData = new \stdClass();
        $monthstring = $request->get('month');
        if($request->isMethod('post')){
            $rules = [
                'month' => 'required|min:7|max:7',
            ];
            $this->validate($request, $rules);

        }
        if(!$monthstring){
            $monthstring = Carbon::now()->format('m/Y');
        }
        $month = Carbon::createFromFormat('m/Y', $monthstring)->timezone(env('APP_TIMEZONE','Asia/Kolkata'));
        $monthStart = $month->startOfMonth()->copy();
        $monthEnd = $month->endOfMonth()->copy();
        $employees = Employee::where('status', AppHelper::ACTIVE)
            ->select('id','name','id_card')
            ->orderBy('id_card','asc')
            ->get();
        $employeeIds = $employees->pluck('id');
        $attendanceData = EmployeeAttendance::select('employee_id','attendance_date','present','status')
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', '>=', $monthStart->format('Y-m-d'))
            ->whereDate('attendance_date', '<=', $monthEnd->format('Y-m-d'))
            ->get()
            ->reduce(function ($attendanceData, $attendance){
                $inLate = 0;
                if(strpos($attendance->status, '1') !== false){
                    $inLate = 1;
                }
                $attendanceData[$attendance->employee_id][$attendance->getOriginal('attendance_date')] = [
                    'present' => $attendance->getOriginal('present'),
                    'inLate'  => $inLate
                ];
                return $attendanceData;
            });
        //get all leaves of employees for requested month
        $employeesLeaves = Leave::where('status',2) //1= pending, 2= approved, 3= Rejected
            ->whereDate('leave_date','>=', $monthStart->format('Y-m-d'))
            ->whereDate('leave_date','<=', $monthEnd->format('Y-m-d'))
            ->get(['employee_id','leave_date'])
            ->reduce(function ($employeesLeaves, $leave) {
                $employeesLeaves[$leave->employee_id][$leave->getOriginal('leave_date')] = 1; //just true
                return $employeesLeaves;
            });
        //get all workoutside of employees for requested month
        $employeesWorkoutside = WorkOutside::whereDate('work_date','>=', $monthStart->format('Y-m-d'))
            ->whereDate('work_date','<=', $monthEnd->format('Y-m-d'))
            ->get(['employee_id','work_date'])
            ->reduce(function ($employeesWorkoutside, $work) {
                $employeesWorkoutside[$work->employee_id][$work->getOriginal('work_date')] = 1; //just true
                return $employeesWorkoutside;
            });
        $wekends = AppHelper::getAppSettings('weekends');
        if($wekends){
            $wekends = json_decode($wekends);
        }
        //pull holidays
        $calendarData = AcademicCalendar::where(function ($q){
            $q->where('is_holiday','1');
        })
            ->where(function ($q) use($monthStart, $monthEnd){
                $q->whereDate('date_from', '>=', $monthStart->format('Y-m-d'))
                    ->whereDate('date_from', '<=', $monthEnd->format('Y-m-d'))
                    ->orWhere(function ($q) use($monthStart, $monthEnd){
                        $q->whereDate('date_upto', '>=', $monthStart->format('Y-m-d'))
                            ->whereDate('date_upto', '<=', $monthEnd->format('Y-m-d'));
                    });
            })
            ->select('date_from','date_upto','is_holiday','is_exam','class_ids')
            ->get()
            ->reduce(function ($calendarData, $calendar) use($monthEnd, $monthStart, $wekends){
                $startDate = $calendar->date_from;
                $endDate = $calendar->date_upto;
                if($calendar->date_upto->gt($monthEnd)){
                    $endDate = $monthEnd;
                }
                if($calendar->date_from->lt($monthStart)){
                    $startDate = $monthStart;
                }
                $cladendarDateRange = AppHelper::generateDateRangeForReport($startDate, $endDate, true, $wekends, true);
                foreach ($cladendarDateRange as $date => $value){
                    $symbols = 'H';
                    $calendarData[$date] = $symbols;
                }
                return $calendarData;
            });
        $monthDates = AppHelper::generateDateRangeForReport($monthStart, $monthEnd, true, $wekends);
        $headerData->reportTitle = 'Monthly Attendance';
        $headerData->reportSubTitle = 'Month: '.$month->format('F,Y');
        $filters = [];
        
        if($request->isMethod('post')){
            return view('backend.report.employee.attendance.monthly_print',compact(
                'headerData',
                'monthDates',
                'employees',
                'attendanceData',
                'calendarData',
                'employeesLeaves',
                'employeesWorkoutside'
            ));
        }
        return view('backend.report.employee.attendance.monthly_view',compact(
            'headerData',
            'monthDates',
            'employees',
            'attendanceData',
            'calendarData',
            'employeesLeaves',
            'employeesWorkoutside',
            'monthstring'
        ));
    }
    /**
     *  Student attendance Monthly Section wise
     *  @return \Illuminate\Http\Response
     */
    public function studentMonthlyAttendance(Request $request){

        $monthstring = $request->get('month');
        $classId = $request->get('class_id', 0);
        $sectionId = $request->get('section_id', 0);
        if(!$monthstring){
            $monthstring = Carbon::now()->format('m/Y');
        }
        if($request->isMethod('post')){
            $rules = [
                'class_id' => 'required|integer',
                'section_id' => 'required|integer',
                'month' => 'required|min:7|max:7',
            ];

            if(AppHelper::getInstituteCategory() == 'college') {
                $rules['academic_year'] = 'required|integer';
            }

            $this->validate($request, $rules);
        }

        $month = Carbon::createFromFormat('m/Y', $monthstring)->timezone(env('APP_TIMEZONE','Asia/Kolkata'));
        if(AppHelper::getInstituteCategory() == 'college') {
            $academicYearId = $request->get('academic_year', 0);
        }
        else{
            $academicYearId = AppHelper::getAcademicYear();
        }
        $monthStart = $month->startOfMonth()->copy();
        $monthEnd = $month->endOfMonth()->copy();

        $students = Registration::where('status', AppHelper::ACTIVE)
            ->where('class_id', $classId)
            ->where('academic_year_id', $academicYearId)
            ->where('section_id', $sectionId)
            ->with(['info' => function($query){
                $query->select('name','id');
            }])
            ->select('id','student_id','roll_no','regi_no')
            ->orderBy('roll_no','asc')
            ->get();

        $studentIds = $students->pluck('id');
        $attendanceData = StudentAttendance::select('registration_id','attendance_date','present','status')
            ->whereIn('registration_id', $studentIds)
            ->whereDate('attendance_date', '>=', $monthStart->format('Y-m-d'))
            ->whereDate('attendance_date', '<=', $monthEnd->format('Y-m-d'))
            ->get()
            ->reduce(function ($attendanceData, $attendance){
                $inLate = 0;
                if(strpos($attendance->status, '1') !== false){
                    $inLate = 1;
                }
                $attendanceData[$attendance->registration_id][$attendance->getOriginal('attendance_date')] = [
                    'present' => $attendance->getOriginal('present'),
                    'inLate'  => $inLate
                ];

                return $attendanceData;
            });

        $wekends = AppHelper::getAppSettings('weekends');
        if($wekends){
            $wekends = json_decode($wekends);
        }
        //pull holidays
        $calendarData = AcademicCalendar::where(function ($q){
            $q->where('is_holiday','1')
                ->orWhere('is_exam','1');
        })
            ->where(function ($q) use($monthStart, $monthEnd){
                $q->whereDate('date_from', '>=', $monthStart->format('Y-m-d'))
                    ->whereDate('date_from', '<=', $monthEnd->format('Y-m-d'))
                    ->orWhere(function ($q) use($monthStart, $monthEnd){
                        $q->whereDate('date_upto', '>=', $monthStart->format('Y-m-d'))
                            ->whereDate('date_upto', '<=', $monthEnd->format('Y-m-d'));
                    });
            })

            ->select('date_from','date_upto','is_holiday','is_exam','class_ids')
            ->get()
            ->reduce(function ($calendarData, $calendar) use($monthEnd, $monthStart, $wekends){

                $startDate = $calendar->date_from;
                $endDate = $calendar->date_upto;
                if($calendar->date_upto->gt($monthEnd)){
                    $endDate = $monthEnd;
                }

                if($calendar->date_from->lt($monthStart)){
                    $startDate = $monthStart;
                }

                $cladendarDateRange = AppHelper::generateDateRangeForReport($startDate, $endDate, true, $wekends, true);
                foreach ($cladendarDateRange as $date => $value){
                    $symbols = 'H';
                    if($calendar->is_exam == 1){
                        $symbols = 'E';
                    }
                    $calendarData[$date] = $symbols;
                }
                return $calendarData;
            });


        $monthDates = AppHelper::generateDateRangeForReport($monthStart, $monthEnd, true, $wekends);

        $headerData = new \stdClass();
        $headerData->reportTitle = 'Monthly Attendance';
        $headerData->reportSubTitle = 'Month: '.$month->format('F,Y');

        
        if($request->isMethod('post')){
            $filters = [];
            if(AppHelper::getInstituteCategory() == 'college') {
                $academicYearInfo = AcademicYear::where('id', $academicYearId)->first();
                $filters[] = "Academic year: ".$academicYearInfo->title;
            }
            $section = Section::where('id', $sectionId)
                ->with(['class' => function($q){
                    $q->select('name','id');
                }])
                ->select('id','class_id','name')
                ->first();
    
            $filters[] = "Class: ".$section->class->name;
            $filters[] = "Section: ".$section->name;


            return view('backend.report.student.attendance.monthly_print',compact(
                'classId',
                'sectionId',
                'headerData',
                'monthDates',
                'students',
                'attendanceData',
                'calendarData',
                'filters'
            ));
        }



        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        $academic_years = [];
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        return view('backend.report.student.attendance.monthly', compact(
            'classId',
            'sectionId',
            'headerData',
            'monthDates',
            'students',
            'attendanceData',
            'calendarData',
            'academic_years',
            'classes',
            'monthstring'
        ));

    }

        /**
     *  Student attendance Monthly Section wise
     *  @return \Illuminate\Http\Response
     */
    public function studentMonthlyAttendanceSubSess(Request $request){

        $monthstring = $request->get('month');
        $classId = $request->get('class_id', 0);
        $sectionId = $request->get('section_id', 0);
        if(!$monthstring){
            $monthstring = Carbon::now()->format('m/Y');
        }
        if($request->isMethod('post')){
            $rules = [
                'class_id' => 'required|integer',
                'section_id' => 'required|integer',
                'month' => 'required|min:7|max:7',
            ];

            if(AppHelper::getInstituteCategory() == 'college') {
                $rules['academic_year'] = 'required|integer';
            }

            $this->validate($request, $rules);
        }

        $month = Carbon::createFromFormat('m/Y', $monthstring)->timezone(env('APP_TIMEZONE','Asia/Kolkata'));
        if(AppHelper::getInstituteCategory() == 'college') {
            $academicYearId = $request->get('academic_year', 0);
        }
        else{
            $academicYearId = AppHelper::getAcademicYear();
        }
        $monthStart = $month->startOfMonth()->copy();
        $monthEnd = $month->endOfMonth()->copy();

        $students = Registration::where('status', AppHelper::ACTIVE)
            ->where('class_id', $classId)
            ->where('academic_year_id', $academicYearId)
            ->where('section_id', $sectionId)
            ->with(['info' => function($query){
                $query->select('name','id');
            }])
            ->select('id','student_id','roll_no','regi_no')
            ->orderBy('roll_no','asc')
            ->get();

        $studentIds = $students->pluck('id');

        $totalSessionSubject = '';
        $get_attendance_type = AppHelper::getAppSettings('attendance_type');
        if($get_attendance_type == 'session_attendance') {
            $column_name = 'session';
            $total_session = AppHelper::getAppSettings('attendance_sessions');
            $totalSessionSubject = !empty($total_session) ? count($total_session) : 0;
        } else if($get_attendance_type == 'subject_attendance') {
            $column_name = 'subject';
            $total_subject = AppHelper::getSubjectByClass($classId);
            $totalSessionSubject = !empty($total_subject) ? count($total_subject) : 0;
        }

        $attendanceData = StudentAttendance::select('registration_id','attendance_date','present','status','subject','session')
            ->whereIn('registration_id', $studentIds)
            ->whereDate('attendance_date', '>=', $monthStart->format('Y-m-d'))
            ->whereDate('attendance_date', '<=', $monthEnd->format('Y-m-d'))
            ->get()
            ->reduce(function ($attendanceData, $attendance) use ($column_name) {
                $inLate = 0;
                if(strpos($attendance->status, '1') !== false){
                    $inLate = 1;
                }
                if(!empty($attendance->getOriginal($column_name))) {
                    $attendanceData[$attendance->registration_id][$attendance->getOriginal('attendance_date')][$attendance->getOriginal($column_name)] = [
                        'present' => $attendance->getOriginal('present'),
                        'inLate'  => $inLate
                    ];
                }

                return $attendanceData;
            });
        //echo '<pre>';print_r($attendanceData);die;
        $wekends = AppHelper::getAppSettings('weekends');
        if($wekends){
            $wekends = json_decode($wekends);
        }
        //pull holidays
        $calendarData = AcademicCalendar::where(function ($q){
            $q->where('is_holiday','1')
                ->orWhere('is_exam','1');
        })
            ->where(function ($q) use($monthStart, $monthEnd){
                $q->whereDate('date_from', '>=', $monthStart->format('Y-m-d'))
                    ->whereDate('date_from', '<=', $monthEnd->format('Y-m-d'))
                    ->orWhere(function ($q) use($monthStart, $monthEnd){
                        $q->whereDate('date_upto', '>=', $monthStart->format('Y-m-d'))
                            ->whereDate('date_upto', '<=', $monthEnd->format('Y-m-d'));
                    });
            })

            ->select('date_from','date_upto','is_holiday','is_exam','class_ids')
            ->get()
            ->reduce(function ($calendarData, $calendar) use($monthEnd, $monthStart, $wekends){

                $startDate = $calendar->date_from;
                $endDate = $calendar->date_upto;
                if($calendar->date_upto->gt($monthEnd)){
                    $endDate = $monthEnd;
                }

                if($calendar->date_from->lt($monthStart)){
                    $startDate = $monthStart;
                }

                $cladendarDateRange = AppHelper::generateDateRangeForReport($startDate, $endDate, true, $wekends, true);
                foreach ($cladendarDateRange as $date => $value){
                    $symbols = 'H';
                    if($calendar->is_exam == 1){
                        $symbols = 'E';
                    }
                    $calendarData[$date] = $symbols;
                }
                return $calendarData;
            });


        $monthDates = AppHelper::generateDateRangeForReport($monthStart, $monthEnd, true, $wekends);

        $headerData = new \stdClass();
        $headerData->reportTitle = 'Monthly Attendance';
        $headerData->reportSubTitle = 'Month: '.$month->format('F,Y');


        if($request->isMethod('post')){
            $filters = [];
            if(AppHelper::getInstituteCategory() == 'college') {
                $academicYearInfo = AcademicYear::where('id', $academicYearId)->first();
                $filters[] = "Academic year: ".$academicYearInfo->title;
            }
            $section = Section::where('id', $sectionId)
                ->with(['class' => function($q){
                    $q->select('name','id');
                }])
                ->select('id','class_id','name')
                ->first();
    
            $filters[] = "Class: ".$section->class->name;
            $filters[] = "Section: ".$section->name;


            return view('backend.report.student.attendance.monthly_subsess_print',compact(
                'classId',
                'sectionId',
                'headerData',
                'monthDates',
                'students',
                'attendanceData',
                'calendarData',
                'filters',
                'totalSessionSubject'
            ));
        }



        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        $academic_years = [];
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        return view('backend.report.student.attendance.monthly_subsess', compact(
            'classId',
            'sectionId',
            'headerData',
            'monthDates',
            'students',
            'attendanceData',
            'calendarData',
            'academic_years',
            'classes',
            'monthstring',
            'totalSessionSubject'
        ));

    }

    public function studentDailyAttendance(Request $request){

        if($request->isMethod('post')){
            $rules = [
                'class_id' => 'required|integer',
                'section_id' => 'required|integer',
                'date' => 'required',
            ];

            if(AppHelper::getInstituteCategory() == 'college') {
                $rules['academic_year'] = 'required|integer';
            }

            $this->validate($request, $rules);

            $month = Carbon::createFromFormat('d/m/Y', $request->get('date'))->timezone(env('APP_TIMEZONE','Asia/Kolkata'));
            $classId = $request->get('class_id', 0);
            $sectionId = $request->get('section_id', 0);
            if(AppHelper::getInstituteCategory() == 'college') {
                $academicYearId = $request->get('academic_year', 0);
            }
            else{
                $academicYearId = AppHelper::getAcademicYear();
            }
            $monthStart = $month->startOfMonth()->copy();
            $monthEnd = $month->endOfMonth()->copy();

            $students = Registration::where('status', AppHelper::ACTIVE)
                ->where('class_id', $classId)
                ->where('academic_year_id', $academicYearId)
                ->where('section_id', $sectionId)
                ->with(['info' => function($query){
                    $query->select('name','id');
                }])
                ->select('id','student_id','roll_no','regi_no')
                ->orderBy('roll_no','asc')
                ->get();

            $studentIds = $students->pluck('id');
            $attendanceData = StudentAttendance::select('registration_id','attendance_date','present','status')
                ->whereIn('registration_id', $studentIds)
                ->whereDate('attendance_date', '>=', $monthStart->format('Y-m-d'))
                ->whereDate('attendance_date', '<=', $monthEnd->format('Y-m-d'))
                ->get()
                ->reduce(function ($attendanceData, $attendance){
                    $inLate = 0;
                    if(strpos($attendance->status, '1') !== false){
                        $inLate = 1;
                    }
                    $attendanceData[$attendance->registration_id][$attendance->getOriginal('attendance_date')] = [
                        'present' => $attendance->getOriginal('present'),
                        'inLate'  => $inLate
                    ];

                    return $attendanceData;
                });

            $wekends = AppHelper::getAppSettings('weekends');
            if($wekends){
                $wekends = json_decode($wekends);
            }
            //pull holidays
            $calendarData = AcademicCalendar::where(function ($q){
                $q->where('is_holiday','1')
                    ->orWhere('is_exam','1');
            })
                ->where(function ($q) use($monthStart, $monthEnd){
                    $q->whereDate('date_from', '>=', $monthStart->format('Y-m-d'))
                        ->whereDate('date_from', '<=', $monthEnd->format('Y-m-d'))
                        ->orWhere(function ($q) use($monthStart, $monthEnd){
                            $q->whereDate('date_upto', '>=', $monthStart->format('Y-m-d'))
                                ->whereDate('date_upto', '<=', $monthEnd->format('Y-m-d'));
                        });
                })

                ->select('date_from','date_upto','is_holiday','is_exam','class_ids')
                ->get()
                ->reduce(function ($calendarData, $calendar) use($monthEnd, $monthStart, $wekends){

                    $startDate = $calendar->date_from;
                    $endDate = $calendar->date_upto;
                    if($calendar->date_upto->gt($monthEnd)){
                        $endDate = $monthEnd;
                    }

                    if($calendar->date_from->lt($monthStart)){
                        $startDate = $monthStart;
                    }

                    $cladendarDateRange = AppHelper::generateDateRangeForReport($startDate, $endDate, true, $wekends, true);
                    foreach ($cladendarDateRange as $date => $value){
                        $symbols = 'H';
                        if($calendar->is_exam == 1){
                            $symbols = 'E';
                        }
                        $calendarData[$date] = $symbols;
                    }
                    return $calendarData;
                });


            $monthDates = AppHelper::generateDateRangeForReport($monthStart, $monthEnd, true, $wekends);

            $headerData = new \stdClass();
            $headerData->reportTitle = 'Monthly Attendance';
            $headerData->reportSubTitle = 'Month: '.$month->format('F,Y');

            $filters = [];
            if(AppHelper::getInstituteCategory() == 'college') {
                $academicYearInfo = AcademicYear::where('id', $academicYearId)->first();
                $filters[] = "Academic year: ".$academicYearInfo->title;
            }
            $section = Section::where('id', $sectionId)
                ->with(['class' => function($q){
                    $q->select('name','id');
                }])
                ->select('id','class_id','name')
                ->first();

            $filters[] = "Class: ".$section->class->name;
            $filters[] = "Section: ".$section->name;


            return view('backend.report.student.attendance.monthly_print',compact(
                'headerData',
                'monthDates',
                'students',
                'attendanceData',
                'calendarData',
                'filters'
            ));
        }



        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        $academic_years = [];
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        return view('backend.report.student.attendance.monthly', compact(
            'academic_years',
            'classes'
        ));

    }
    /**
     * Student list print
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function studentList(Request $request){

        $activeTab = 1;

        if($request->isMethod('post')) {

            if($request->get('form_name') == 'class') {
                $rules = [
                    'class_id' => 'required|integer',
                    'section_id' => 'nullable|integer',
                ];
            }
            else {

                $rules = [
                    'gender' => 'required|integer',
                    'religion' => 'required|integer',
                    'blood_group' => 'required|integer',
                ];
            }

            if (AppHelper::getInstituteCategory() == 'college') {
                $rules['academic_year'] = 'required|integer';
            }

            $this->validate($request, $rules);

            if(AppHelper::getInstituteCategory() == 'college') {
                $academicYearId = $request->get('academic_year', 0);
            }
            else{
                $academicYearId = AppHelper::getAcademicYear();
            }


            $filters = [];
            if(AppHelper::getInstituteCategory() == 'college') {
                $academicYearInfo = AcademicYear::where('id', $academicYearId)->first();
                $filters[] = "Academic year: ".$academicYearInfo->title;
            }

            $students = collect();
            $showSection = false;
            $showClass = false;
            if($request->get('form_name') == 'class') {

                //filter input
                $classId = $request->get('class_id',0);
                $sectionId = intval($request->get('section_id',0));

                $students =  Registration::where('status', AppHelper::ACTIVE)
                    ->where('academic_year_id', $academicYearId)
                    ->where('class_id', $classId)
                    ->if($sectionId, 'section_id', '=', $sectionId)
                    ->with(['info' => function($query){
                        $query->select('name','id', 'father_name', 'father_phone_no', 'mother_name', 'mother_phone_no',
                            'guardian', 'guardian_phone_no', 'present_address', 'permanent_address');
                    }])
                    ->when($sectionId, function ($q){
                        $q->with(['section' => function($qq){
                            $qq->select('id','name');
                        }]);
                    })
                    ->select('id','student_id','roll_no','regi_no','section_id')
                    ->orderBy('regi_no','asc')
                    ->orderBy('roll_no','asc')
                    ->get();

                $classInfo = IClass::where('id', $classId)->first();
                $filters[] = "Class: ".$classInfo->name;

                if($sectionId){
                    $sectionInfo = Section::where('id', $sectionId)
                        ->select('id','name')
                        ->first();
                    $filters[] = "Section: ".$sectionInfo->name;
                }
                else{
                    $showSection = true;
                }

            }
            else{

                //filter input
                $gender = intval($request->get('gender',0));
                $religion = intval($request->get('religion',0));
                $bloodGroup = intval($request->get('blood_group',0));

                $students =  Registration::where('status', AppHelper::ACTIVE)
                    ->where('academic_year_id', $academicYearId)
                    ->with(['info' => function($query){
                        $query->select('name','id', 'father_name', 'father_phone_no', 'mother_name', 'mother_phone_no',
                            'guardian', 'guardian_phone_no', 'present_address', 'permanent_address','gender','religion','blood_group');
                    }])
                    ->with(['class' => function($query){
                        $query->select('name','id');
                    }])
                    ->with(['section' => function($query){
                        $query->select('name','id');
                    }])
                    ->whereHas('student', function ($q) use($gender, $religion, $bloodGroup){
                        $q->if($religion,'religion','=',$religion)
                            ->if($gender, 'gender', '=', $gender)
                            ->if($bloodGroup, 'blood_group', '=', $bloodGroup);
                    })
                    ->select('id','student_id','roll_no','regi_no','class_id','section_id')
                    ->orderBy('class_id','asc')
                    ->orderBy('section_id','asc')
                    ->orderBy('regi_no','asc')
                    ->orderBy('roll_no','asc')
                    ->get();


                $filters[] = "Gender: ".($gender ? AppHelper::GENDER[$gender] : 'All' );
                $filters[] = "Religion: ".($religion ? AppHelper::RELIGION[$religion] : 'All');
                $filters[] = "Blood Group: ".($bloodGroup ? AppHelper::BLOOD_GROUP[$bloodGroup] : 'All');

                $showClass = true;
                $showSection = true;
            }


            $headerData = new \stdClass();
            $headerData->reportTitle = 'Student List';
            $headerData->reportSubTitle = '';

            return view('backend.report.student.list_print', compact('headerData', 'students','filters','showClass','showSection'));
        }

        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        $academic_years = [];
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        return view('backend.report.student.list', compact(
            'academic_years',
            'classes',
            'activeTab'
        ));
    }

    /**
     *  Marksheet public print
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function marksheetPublic(Request $request){
        $classId = $request->get('class_id', 0);
        $regiNo = $request->get('regi_no', '');
        $exams = [];

        if($classId) {
            $exams = Exam::where('status', AppHelper::ACTIVE)
                    ->where('class_id',  $classId)
                    ->select('name as text', 'id')
                    ->orderBy('name', 'asc')
                    ->pluck('text', 'id');
        }
        if($request->isMethod('post')) {
            
            $rules = [
                'class_id' => 'required|integer',
                'exam_id' => 'required|integer',
                'regi_no' => 'required',
            ];

            if(!$request->exists('authorized_form',0)){
                $rules['captcha'] = 'required|captcha';
            }

            $v = Validator::make($request->all(), $rules);
            if(!$v->passes()){
                return redirect()->route('report.marksheet_pub')->withErrors($v);
            }

            $examId = $request->get('exam_id', 0);


            $exam = Exam::where('id', $examId)
                ->where('class_id', $classId)
                ->select('id','name','marks_distribution_types')
                ->first();

            if(!$exam){
                return redirect()->back()->with('error', 'Exam not found!');
            }

            $student = Registration::where('status', AppHelper::ACTIVE)
                ->where('class_id', $classId)
                ->where('regi_no', $regiNo)
                ->with(['info' => function($query){
                    $query->select('name','dob','father_name','mother_name','id');
                }])
                ->with(['class' => function($query){
                    $query->select('name','id');
                }])
                ->with(['section' => function($query){
                    $query->select('name','id');
                }])
                ->with(['acYear' => function($q){
                    $q->select('title','id','start_date','end_date');
                }])
                ->select('id','student_id','class_id','section_id','shift','regi_no','roll_no','academic_year_id','fourth_subject','alt_fourth_subject')
                ->first();

            if(!$student){
                return redirect()->back()->with('error', 'Student not found!');
            }

            $publishedResult = DB::table('result_publish')
                ->where('academic_year_id', $student->acYear->id)
                ->where('academic_year_id', $student->acYear->id)
                ->where('class_id', $classId)
                ->where('exam_id', $exam->id)
                ->whereDate('publish_date','<=', Carbon::now(env('APP_TIMEZONE','Asia/Kolkata'))->format('Y-m-d'))
                ->select('publish_date')
                ->first();

            if(!$publishedResult){
                return redirect()->back()->with('error', 'Result not published for this class and exam!');
            }

            //result
            $result =  Result::where('class_id', $request->get('class_id'))
                ->where('registration_id', $student->id)
                ->where('exam_id', $exam->id)
                ->select('registration_id','grade', 'point', 'total_marks')
                ->first();
            $p_date = new DateTime($publishedResult->publish_date);
            $p_date = $p_date->format('Y-m-d');
            $result->published_at = Carbon::createFromFormat('Y-m-d', $p_date)
                ->format('d/m/Y');



            // now pull marks
            //subject wise highest marks
            $subjectWiseHighestMarks =  Mark::where('marks.academic_year_id', $student->acYear->id)
                ->where('marks.class_id', $request->get('class_id'))
                ->where('marks.exam_id', $exam->id)
                ->selectRaw('max(total_marks) as total, subject_id')
                ->groupBy('subject_id')
                ->get()
                ->reduce(function ($subjectWiseHighestMarks, $mark){
                    $subjectWiseHighestMarks[$mark->subject_id] = $mark->total;
                    return $subjectWiseHighestMarks;
                });

            //student
            $examMakrs = Mark::join('subjects', 'marks.subject_id', 'subjects.id')
                ->where('marks.registration_id', $student->id)
                ->where('marks.academic_year_id', $student->acYear->id)
                ->where('marks.class_id', $classId)
                ->where('marks.exam_id', $exam->id)
                ->select('subject_id','marks','total_marks','grade','point','present','subjects.name as subject_name',
                    'subjects.type as subject_type','subjects.code as subject_code')
                ->orderBy('subject_code','asc')
                ->get();

            $coreSubjectsMakrs = [];
            foreach ($examMakrs as $marks){
                $m = json_decode($marks->marks, true);
                foreach($m as $d => $_m) {
                    $m[$d] = $_m == -1 ? 'AB' : $_m;
                    $m[$d] = !$_m ? '-' : $_m;
                }
                if($marks->subject_type == '1'){
                    //AppHelper::SUBJECT_TYPE
                    $coreSubjectsMakrs[] = [
                        'id' => $marks->subject_id,
                        'code' => $marks->subject_code,
                        'name' => $marks->subject_name,
                        'marks' => $m,
                        'highest_marks' => $subjectWiseHighestMarks[$marks->subject_id],
                        'total_marks' => $marks->total_marks,
                        'grade' => $marks->grade,
                        'point' => $marks->point
                    ];
                }
                else{
                    if($student->fourth_subject == $marks->subject_id){
                        $coreSubjectsMakrs[] = [
                            'id' => $marks->subject_id,
                            'code' => $marks->subject_code,
                            'name' => $marks->subject_name,
                            'marks' => $m,
                            'highest_marks' => $subjectWiseHighestMarks[$marks->subject_id],
                            'total_marks' => $marks->total_marks,
                            'grade' => $marks->grade,
                            'point' => $marks->point
                        ];
                    }
                }
            }


            //marks distribution types
            $marksDistributionTypes = json_decode($exam->marks_distribution_types, true);

            // report settings
            $headerData = new \stdClass();
            $headerData->reportTitle = 'Marksheet';
            $headerData->reportSubTitle = $exam->name.'-'.$student->acYear->title;

            $message = AppHelper::getAppSettings('report_pms_message');
            $expireDate = AppHelper::getAppSettings('report_pms_message_exp_date');
            $showMessage = false;
            if($message && strlen($message) && $expireDate && strlen($expireDate)){
                $expireDate = Carbon::createFromFormat('d/m/Y', $expireDate);
                $nowDate = Carbon::now(env('APP_TIMEZONE','Asia/Kolkata'));
                if($nowDate->lte($expireDate)){
                    $showMessage = true;
                }
            }


            return view('backend.report.exam.marksheet_pub_print', compact(
                'headerData',
                'exam',
                'marksDistributionTypes',
                'student',
                'coreSubjectsMakrs',
                'result',
                'message',
                'showMessage',
                'classId',
                'regiNo',
                'exams'
            ));
        }

        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');


        return view('backend.report.exam.marksheet_pub', compact(
            'exams',
            'classes',
            'classId',
            'regiNo'
        ));
    }

    public function studentDailyttendance(){
       $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

        return view('backend.report.student.attendance.daily', compact(
            'classes'
        ));
    }

    public function postStudentDailyttendance(Request $request){

        $request_data =$request->all();

        //validate form
        $messages = [
            'start_date.required' => 'Start date can not be empty!',
            'end_date.required' => 'End date can not be empty!',
//            'class_id.required' => 'Class can not empty!',
        ];
        $rules = [
            'start_date' => 'required',
            'end_date' => 'required',
            'class_id'  => 'required|array|min:1',
        ];

        $this->validate($request, $rules, $messages);

        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

         $start_date = Carbon::createFromFormat('d/m/Y', $request->get('start_date'))->format('Y-m-d');

        $end_date = Carbon::createFromFormat('d/m/Y', $request->get('end_date'))->format('Y-m-d');

        $class_ids = $request_data['class_id'];

        $attendance_data_query =  StudentAttendance::whereIn('class_id',$class_ids)->with('student');

        if($request_data['filter'] != 2){
            $attendance_data_query->Where('present',$request_data['filter']);
        }
        $attendance_data_query->whereBetween('attendance_date', [$start_date, $end_date]);

        $attendance_data =  $attendance_data_query->groupBy('attendance_date','registration_id')->get();

        $total_session_subject = array();
        $get_attendance_type = AppHelper::getAppSettings('attendance_type');
        $attendance_type = 'daily';
        if(!empty($class_ids)){
            foreach($class_ids as $class_id) {
                if($get_attendance_type == 'session_attendance') {
                    $attendance_type = 'session';
                    $total_session = AppHelper::getAppSettings('attendance_sessions');
                    $total_session_subject[$class_id] = !empty($total_session) ? count($total_session) : 0;
                } else if($get_attendance_type == 'subject_attendance') {
                    $attendance_type = 'subject';
                    $total_subject = AppHelper::getSubjectByClass($class_id);
                    $total_session_subject[$class_id] = !empty($total_subject) ? count($total_subject) : 0;
                    
                }
            }
        }
        //        dd($attendance_data);

        return view('backend.report.student.attendance.daily', compact(
            'classes','attendance_data','class_ids','start_date','end_date','total_session_subject','attendance_type'
        ))->with($request->all());
    }
    public function completeAttendanceLog(Request $request){
        $request_data =$request->all();
        $class_id = $request_data['class_id'];
        $attendance_date = Carbon::createFromFormat('d/m/Y', $request_data['attendance_date'])->format('Y-m-d');
        
        $registration_id = $request_data['registration_id'];
        
        $get_attendance_type = AppHelper::getAppSettings('attendance_type');
        if($get_attendance_type == 'subject_attendance') {
            $attendance_data =  Subject::leftJoin('student_attendances', function($join)  use ($attendance_date, $class_id, $registration_id) {
                $join->on('subjects.id', '=', 'student_attendances.subject')->where(array('attendance_date'=>$attendance_date,'registration_id'=>$registration_id));
            })->where('subjects.class_id', $class_id)->get();
        } else if($get_attendance_type == 'session_attendance') {
            $getAllSessions = AppHelper::getAppSettings('attendance_sessions');
            $attendance_data = [];
            foreach($getAllSessions as $key => $session) {
                if (!isset($attendance_data[$key])) {
                    $attendance_data[$key] = new \stdClass();
                }
                $session_id = (!empty($session['session_no'])) ? $session['session_no'] :'' ;
                $from = (!empty($session['from'])) ? Carbon::parse($session['from'])->format('h:i a') : '';
                $to = (!empty($session['to'])) ? Carbon::parse($session['to'])->format('h:i a') : '';
                $attendance_data[$key]->name = $from.':'.$to;

                $attendances = StudentAttendance::where(array('attendance_date'=>$attendance_date,'registration_id'=>$registration_id,'session'=>$session_id))
                ->select('present')
                ->first();
                if(!empty($attendances->present)) {
                    if($attendances->present == 'Present') {
                        $attendance_data[$key]->present = 1;
                    } else if($attendances->present == 'Absent') {
                        $attendance_data[$key]->present = 0;
                    }
                }
            }
        }
        $view = view('backend.report.student.attendance.attendance_log', compact('attendance_data'))->render();
         return response()->json(['html' => $view]);
    }
}
