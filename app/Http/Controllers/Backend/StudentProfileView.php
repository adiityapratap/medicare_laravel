<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Support\Facades\Input;
use \stdClass;
use App\FeeCol;
use App\AttendanceFileQueue;
use App\AcademicCalendar;
use App\Jobs\PushStudentAbsentJob;
use Symfony\Component\Process\Process;
use App\AcademicYear;
use App\CircularNotification;
use App\Employee;
use App\Homework;
use App\HomeworkSubmission;
use App\IClass;
use App\Models\PasswordResets;
use App\Permission;
use App\Registration;
use App\Role;
use App\Result;
use App\ExamRule;
use DateTime;
use App\Mark;
use App\Section;
use App\smsLog;
use App\Student;
use App\StudentAttendance;
use App\Subject;
use App\Template;
use App\User;
use App\Exam;
use App\UserRole;
use App\MessageNotification;
use App\MessageUserMapping;
use App\AppMeta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\AppHelper;
use App\Http\Helpers\SmsHelper;
use App\Traits\MessageTrait;
use App\Traits\InstituteUsersTrait;

class StudentProfileView extends Controller
{
    /**
     * attendance of student
     */
    public function attendance(Request $request){
    
        $id = auth()->user()->student->id;
        $classid = auth()->user()->student->register->class_id;
        // Authentication if user is passing different value
        $attendancs = StudentAttendance::where('registration_id',$id)
            ->select('attendance_date', 'present', 'registration_id')
            ->orderBy('attendance_date', 'asc')
            ->paginate(25);
        

        return view("backend.student.attendancestud",compact('attendancs'));
    }

    /**
     * showing marks of auth user as student
     */
    public function marksListing()
        {
                $class= DB::table('users')
                    ->join('students','students.user_id','=','users.id')
                    ->join('registrations','registrations.id','=','students.id')
                    // ->join('marks','marks.registration_id','=','registrations.id')
                    ->where('user_id',auth()->user()->student->user_id)
                    ->whereNotNull('students.user_id')
                    ->whereNull('registrations.deleted_at')
                    ->whereNull('students.deleted_at')
                    ->where('registrations.status', AppHelper::ACTIVE)->where('students.status', AppHelper::ACTIVE)
                    ->pluck('registrations.class_id');
                    /**
                     * student from same section
                     */
        $section= 'A';
        $sections = Section::where('status', AppHelper::ACTIVE)
                            ->whereIn('class_id', $class)
                            ->where('name', $section)
                            ->orderBy('name','asc')->pluck('name', 'id');

        // Use to fetch other things 
            $section = Section::where('status', AppHelper::ACTIVE)
                                ->whereIn('class_id', $class)
                                ->where('name', $section)->first();

        if(!$section) {
            abort(404);
        }

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');

        $section_id = $class;
        $class_id = $section->class_id;
        $subject_id = null;
        $exam_id = null;

        $teacherId = 0;
        $subjectType = 0;
        if(session('user_role_id',0) == AppHelper::USER_TEACHER){
            $teacherId = auth()->user()->teacher->id;
        }
        $subjects = Subject::select('id', 'name')
            ->where('class_id',$class_id)
            ->sType($subjectType)
            ->when($teacherId, function ($query) use($teacherId){
                $query->where('teacher_id', $teacherId);
            })
            ->where('status', AppHelper::ACTIVE)
            ->orderBy('name', 'asc');
            // ->get();

        /////////////////////////////////////////////////////////////
        // Old Code | Need To Remove after test //
        /////////////////////////////////////////////////////////////
        //     ->where('class_id', $class_id)
        //     ->when($section->teacher_id, function ($query) use($section){ // Get the particular teacher's subjects
        //         $query->where('teacher_id', $section->teacher_id);
        //     })
        //     ->pluck('name', 'id');

        // $subjects = Subject::where('class_id', $section->class_id)
        //     ->where('status', AppHelper::ACTIVE)
        //     ->get();
            // ->pluck('name', 'id');


        $exams = Exam::where('status', AppHelper::ACTIVE)
            ->where('class_id', $class_id)
            ->get();
            // ->pluck('name', 'id');

        // Combine Subject Rule
            $combine_sub = ExamRule::whereIn('exam_id', array_values($exams->pluck('id')->toArray()))
                                    ->where('class_id', $class_id)
                                    ->get();
            
            $com_data = [];
            foreach ($combine_sub as $sub) {
                if( $sub->combine_subject_id )
                    $com_data[] = $sub->combine_subject_id;
            }
            if( $com_data ) {
                $subjects->whereNotIn('id', explode(',', implode(',', $com_data)));
            }

        $subjects = $subjects->pluck('name', 'id');
        $exam_rules = ExamRule::where('class_id', $class_id)
                                ->whereIn('exam_id', $exams->pluck('id')->toArray())
                                ->whereIn('subject_id', array_keys($subjects->toArray()))
                                ->pluck('id')->toArray();

        ////////////////////////////////////////////////////////////////
        // Avoid Subject If Required //
        ////////////////////////////////////////////////////////////////
        //     ->where('status', AppHelper::ACTIVE)
        //     ->whereIn('id', array_keys($avoid_subs))
        //     ->pluck('name', 'id');
        
        $exams = $exams->pluck('name', 'id');
        return view('backend.student.marks', compact(
                    'sections',
                    'classes',
                    'section_id',
                    'class_id',
                    'subjects',
                    'subject_id',
                    'exams',
                    'exam_id'
        ));
    }

    public function getResultsDetails(Request $request)
    {
        if(!auth()->user()->student) {
            abort(401);
        }   
        if(AppHelper::getInstituteCategory() == 'college') {
            $acYear = $request->get('academic_year_id', 0);
        }
        else{
            $acYear = AppHelper::getAcademicYear();
        }                
        $academic_years = [];
        $editMode = 1;
        $student_id = auth()->user()->student->id;
        // $class_id = $request->get('class',0);
        $reg = Registration::select('id', 'class_id')->where('student_id','=', $student_id)->first();
        $reg_id = $reg->id;
        $class_id = $reg->class_id;
        $section_id = $request->get('section',0);
        $subject_id = $request->get('subject',0);
        $exam_id = $request->get('exam',0);

        $marks = Mark::with(['student' => function($query) use($reg_id){
            $query->with(['info' => function($query){
                $query->select('name','id');
            }])
            ->select('regi_no','student_id','roll_no','id')
            ->where('id', $reg_id)
            ->whereNull('deleted_at')
            ->where('status', AppHelper::ACTIVE);
            }])->where('academic_year_id', $acYear)
            ->where('registration_id', $reg_id)
            ->where('section_id', $section_id)
            ->where('exam_id', $exam_id);
            

        if( $subject_id != 0 ) {
            $marks->whereIn('subject_id', $subject_id);
        }

        $marks = $marks->get();
            

        $examRule = ExamRule::where('exam_id',$exam_id);

        if( $subject_id != 0 ) {
            $examRule->whereIn('subject_id', $subject_id);
        }
        $examRule = $examRule->first();

            // ->pluck('name', 'id');

        $subjects = Subject::where('status', AppHelper::ACTIVE)
            ->where('class_id', $class_id);
            

        if( $subject_id != 0  ) {
            $subjects->whereIn('id', $subject_id);
        }

        // Combine Subject Rule
            $combine_sub = ExamRule::where('exam_id', $exam_id)
                                    ->where('class_id', $class_id)
                                    ->get();
            
            $com_data = [];
            foreach ($combine_sub as $sub) {
                if( $sub->combine_subject_id )
                    $com_data[] = $sub->combine_subject_id;
            }
            if( $com_data ) {
                $subjects->whereNotIn('id', explode(',', implode(',', $com_data)));
            }

        $subjects = $subjects->pluck('name', 'id');

        $exams = Exam::where('status', AppHelper::ACTIVE)
            ->where('class_id', $class_id)
            ->pluck('name', 'id');  

        //check is result is published?
        $isPublish = DB::table('result_publish')
            ->where('academic_year_id', $acYear)
            ->where('class_id', $class_id)
            ->where('exam_id', $exam_id)
            ->count();

        if($isPublish){
            $editMode = 0;
        }

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }


        $student_marks = [];
        foreach ($marks as $mark) {
            if($mark->student->student_id == $student_id) {
                $student_marks[$mark->subject_id] = $mark;
            }
        }
        return json_encode( [   
            'data'      => view('backend.student.marksView', 
                            compact('student_marks',
                                        'acYear',
                                        'class_id',
                                        'section_id',
                                        'subject_id',
                                        'exam_id',
                                        'classes',
                                        'subjects',
                                        'academic_years',
                                        'exams',
                                        'examRule',
                                        'editMode',
                                    ))->render(),
        ]);
    }

    public function message(){
        $sectionId = Auth::user()->student->register->section_id;
        if ($sectionId) {
            $notifications = MessageNotification::whereHas('mapping', function($query) use($sectionId) {
                $query->where('student_id', Auth::user()->student->id)->orWhere('all', 1)->orWhere('section_id', $sectionId);
            })->orderBy('id', 'DESC')->limit(10)->paginate(25);
            return view('backend.student.message', compact('notifications'));
        }
    }

    public function circular(){
        $sectionId = Auth::user()->student->register->section_id;
        $circulars = CircularNotification::whereHas('mapping', function($query) use($sectionId) {
            $query->where('student_id', Auth::user()->student->id)->orWhere('all', 1)->orWhere('section_id', $sectionId);
        })->where('circular_type', 'circular')->orderBy('id', 'DESC')->paginate(25);
       
        return view('backend.student.circular', compact('circulars'));
    }

    public function announcement(){
        $sectionId = Auth::user()->student->register->section_id;
        $announcements = CircularNotification::whereHas('mapping', function($query) use($sectionId) {
            $query->where('student_id', Auth::user()->student->id)->orWhere('all', 1)->orWhere('section_id', $sectionId);
        })->where('circular_type', 'announcement')->orderBy('id', 'DESC')->paginate(25);
        return view('backend.student.announcement', compact('announcements'));
    }

    public function feeCollectionList(Request $request) {
		if($request->isMethod('get')) {
            $classes = IClass::select('id','name')
            ->where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')->get();
            $student = new stdClass();
			// $student->class=Input::get('class');
			// $student->section=Input::get('section');
            $student->student=Input::get('student');
			$fees=DB::Table('fee_collection')
			->select(DB::RAW("billNo,payableAmount,paidAmount,dueAmount,DATE_FORMAT(payDate,'%D %M,%Y') AS date"))
			->where('class_id',auth()->user()->student->register->class_id)
			->where('student_id',auth()->user()->student->id)
			->whereNull('deleted_at')
            ->get();
			$totals = FeeCol::select(DB::RAW('IFNULL(sum(payableAmount),0) as payTotal,IFNULL(sum(paidAmount),0) as paiTotal,(IFNULL(sum(payableAmount),0)- IFNULL(sum(paidAmount),0)) as dueAmount'))
			->where('class_id',auth()->user()->student->register->class_id)
			->where('student_id',auth()->user()->student->id)
			->whereNull('deleted_at')
            ->first();
			//  echo "<pre>";print_R($fees);die;
			return view('backend.student.payment',compact('classes','student','fees','totals'));
		}
		$classes = IClass::select('id','name')->where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->get();
		$fees = array();
		return view('backend.student.payment',compact('classes','fees'));
    }
    
    public function academicCalendar(){
        $year = date('Y');
        $calendars = collect();
        if(strlen($year)) {
            $calendars = AcademicCalendar::whereYear('date_from', $year)
                ->whereYear('date_upto', $year)
                ->get();
        }
        return view('backend.student.academic_calendar',compact('calendars','year'));
    }
}
