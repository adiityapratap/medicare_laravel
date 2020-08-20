<?php

namespace App\Http\Controllers\Backend;

use Log;
use App\AcademicYear;
use App\Exam;
use App\ExamRule;
use App\Grade;
use App\Http\Controllers\Controller;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Mark;
use App\Registration;
use App\Result;
use App\Section;
use App\Subject;
use App\Acknowledgement;
use App\Jobs\SendExamResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use \stdClass;

class MarkController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            abort(404);
        }

        $teacherSubjects = '';
        $submission_date = date('d/m/Y');
        $classlist = [];

        if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER  ) {
            $teacherID = DB::table('employees')->select('id')->where('user_id', auth()->user()->id)->first();
            $teacherClasses = AppHelper::getTeacherClasses($teacherID->id);
            $teacherSubjects = AppHelper::getTeacherSubjects($teacherID->id);
            $classes = IClass::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherClasses)->orderBy('order','asc')->pluck('name', 'id');
        } else {
            $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order','asc')->pluck('name', 'id');
        }

        foreach($classes as $class_id => $class) {
            $sections = Section::where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->orderBy('name','asc')->pluck('name', 'id');
            foreach($sections as $section_id => $section) {
                if(!isset($classlist[$class_id])) {
                    $classlist[$class_id] = new stdClass();
                }
                $classlist[$class_id]->{$section_id} = new stdClass();
                $classlist[$class_id]->{$section_id}->class = $class;
                $classlist[$class_id]->{$section_id}->section = $section;
                $classlist[$class_id]->{$section_id}->htmlclass = filter_var($class, FILTER_SANITIZE_NUMBER_INT);
            }
        }
        
        // dd($classlist);
        return view('backend.exam.marks.list', compact('classlist'));
    }


        public function marksListing($class, $section)
        {
            $sections = Section::where('status', AppHelper::ACTIVE)
                                ->where('class_id', $class)
                                ->where('name', $section)
                                ->orderBy('name','asc')->pluck('name', 'id');

            // Use to fetch other things 
                $section = Section::where('status', AppHelper::ACTIVE)
                                    ->where('class_id', $class)
                                    ->where('name', $section)->first();

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
            return view('backend.exam.marks.marks-entry', compact(
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
                if(AppHelper::getInstituteCategory() == 'college') {
                    $acYear = $request->get('academic_year_id', 0);
                }
                else{
                    $acYear = AppHelper::getAcademicYear();
                }                
                $academic_years = [];
                $editMode = 1;
                $class_id = $request->get('class',0);
                $section_id = $request->get('section',0);
                $subject_id = $request->get('subject',0);
                $exam_id = $request->get('exam',0);

                $teacherId = 0;
                if(session('user_role_id',0) == AppHelper::USER_TEACHER){
                    $teacherId = auth()->user()->teacher->id;
                }


                $marks = Mark::with(['student' => function($query){
                    $query->with(['info' => function($query){
                        $query->select('name','id');
                    }])->select('regi_no','student_id','roll_no','id');
                    }])->where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('section_id', $section_id)
                    // ->where('subject_id', $subject_id)
                    ->where('exam_id', $exam_id);
                    

                if( $subject_id != 0 ) {
                    $marks->whereIn('subject_id', $subject_id);
                }

                $marks = $marks/*->select( 'registration_id', 'marks', 'total_marks', 'grade', 'point', 'present','id')
                    */->get();
                   

                $examRule = ExamRule::where('exam_id',$exam_id);

                if( $subject_id != 0 ) {
                    $examRule->whereIn('subject_id', $subject_id);
                }
                $examRule = $examRule->first();


                $sections = Section::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $class_id)
                    ->where('id', $section_id)->first();
                    // ->pluck('name', 'id');

                $subjects = Subject::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $class_id)
                    ->when($teacherId, function ($query) use($teacherId){
                        $query->where('teacher_id', $teacherId);
                    });
                    

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
                foreach ($sections->student as $student){
                    foreach ($marks as $mark) {
                        if(isset($student_marks[$student->student_id]) && $mark->student->student_id == $student->student_id){
                            $student_marks[$student->student_id][$mark->subject_id] = $mark;
                        } elseif($mark->student->student_id == $student->student_id) {
                            $student_marks[$student->student_id] = [];
                            $student_marks[$student->student_id][$mark->subject_id] = $mark;
                        }
                    }
                }
                return json_encode( [   
                    'data'      => view('backend.exam.marks.includes.marks-layout', 
                                    compact('student_marks',
                                                'acYear',
                                                'class_id',
                                                'section_id',
                                                'subject_id',
                                                'exam_id',
                                                'classes',
                                                'sections',
                                                'subjects',
                                                'academic_years',
                                                'exams',
                                                'examRule',
                                                'editMode',
                                            ))->render(),
                ]);
            }


            public function editExamDetails(Request $request, $class_id, $section_id, $exam_id, $subject_id)
            {
                $main_selected = explode(',', $request->subjects);

                if( empty($request->subjects) ||  count($main_selected) <= 0 ) {
                    return redirect()->route('marks.index')->with('error', 'Oops! Something went wrong.');
                }

                $academic_years = [];
                if(AppHelper::getInstituteCategory() == 'college') {
                    $acYear = $request->get('academic_year_id', 0);
                }
                else{
                    $acYear = AppHelper::getAcademicYear();
                }

                $teacherId = 0;
                if(session('user_role_id',0) == AppHelper::USER_TEACHER){
                    $teacherId = auth()->user()->teacher->id;
                }


                $marks = Mark::with(['student' => function($query){
                    $query->with(['info' => function($query){
                        $query->select('name','id');
                    }])->select('regi_no','student_id','roll_no','id');
                    }])->where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('section_id', $section_id)
                    ->where('exam_id', $exam_id);
                    

                // if ($subject_id != 0) {
                if (!empty($request->subjects)) {
                    // $marks->where('subject_id', $subject_id);
                    $marks->whereIn('subject_id', $main_selected);
                }
                $marks = $marks->select('subject_id', 'registration_id', 'marks', 'total_marks', 'grade', 'point', 'present','id')
                    ->get();

                $examRule = ExamRule::where('exam_id',$exam_id);
                if( !empty($request->subjects) ) {
                    // $examRule->where('subject_id', $subject_id);
                    $examRule->whereIn('subject_id', $main_selected);
                }
                    
                $examRule = $examRule->first();
                $sections = Section::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $class_id)
                    ->where('id', $section_id)
                    ->first();
                    // ->pluck('name', 'id');

                $subjects = Subject::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $class_id)
                    ->when($teacherId, function ($query) use($teacherId){
                        $query->where('teacher_id', $teacherId);
                    });

                if( !empty($request->subjects) )  {
                    // $subjects->where('id', $subject_id);
                    $subjects->whereIn('id', $main_selected);
                }
                    
                $subjects = $subjects->pluck('name', 'id');

                // dd($exam_id);
                $exams = Exam::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $class_id)
                    ->where('id', $exam_id)
                    ->first();
                    // ->pluck('name', 'id');  

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
                foreach ($sections->student as $student){
                    foreach ($marks as $mark) {
                        if(isset($student_marks[$student->student_id]) && $mark->student->student_id == $student->student_id){
                            $student_marks[$student->student_id][$mark->subject_id] = $mark;
                        } elseif($mark->student->student_id == $student->student_id) {
                            $student_marks[$student->student_id] = [];
                            $student_marks[$student->student_id][$mark->subject_id] = $mark;
                        }
                    }
                }

// return $student_marks;
                return view('backend.exam.marks.edit', compact('student_marks',
                    'acYear',
                    'class_id',
                    'section_id',
                    'subject_id',
                    'exam_id',
                    'classes',
                    'sections',
                    'subjects',
                    'academic_years',
                    'exams',
                    'examRule'
                ));
            }

            public function updateExamDetails(Request $request)
            {
                $validateRules = [
                    'academic_year_id' => 'nullable|integer',
                    'class_id' => 'required|integer',
                    'section_id' => 'required|integer',
                    'subject_id' => 'required|integer',
                    'exam_id' => 'required|integer',
                    'registrationIds' => 'required|array',
                    'type' => 'required|array',
                    'absent' => 'nullable|array',
                ];

                $this->validate($request, $validateRules);
                if(AppHelper::getInstituteCategory() == 'college') {
                    $acYear = $request->get('academic_year_id');
                }
                else{
                    $acYear = AppHelper::getAcademicYear();
                }

                $class_id = $request->get('class_id',0);
                $section_id = $request->get('section_id',0);
                $subject_id = $request->get('subject_id',0);
                $exam_id = $request->get('exam_id',0);

                //check is result is published?
                $isPublish = DB::table('result_publish')
                    ->where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('exam_id', $exam_id)
                    ->count();

                if($isPublish){
                    return redirect()->route('marks.index')->with('error', 'Can\'t edit marks, because result is published for this exam!');
                }

                // some validation before entry the mark
                $examInfo = Exam::where('status', AppHelper::ACTIVE)
                    ->where('id', $exam_id)
                    ->first();
                if(!$examInfo) {
                    return redirect()->route('marks.create')->with('error', 'Exam Not Found');
                }

                $examRule = ExamRule::where('exam_id',$exam_id);

                if( $subject_id != 0 ) {
                    $examRule->where('subject_id', $subject_id);
                }
                $examRule = $examRule->first();

                if(!$examRule) {
                    // return redirect()->route('marks.create')->with('error', 'Exam rules not found for this subject and exam!');
                    return redirect()->back()->with('error', 'Exam rules not found for this subject and exam!');
                }

                $entryExists = Mark::where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('section_id', $section_id)
                    ->where('exam_id', $exam_id);

                if( $subject_id != 0 ) {
                    $entryExists->where('subject_id', $subject_id);
                }

                $entryExists = $entryExists->count();
                // dd(!$entryExists);
                // if(!$entryExists){
                //     return redirect()->back()->with('error','No marks entry found!');
                // }

                //pull grading information
                $grade = Grade::where('id', $examRule->grade_id)->first();

                if(!$grade){
                    return redirect()->route('marks.create')->with('error','Grading information not found!');
                }
                $gradingRules = json_decode($grade->rules);

                //exam distributed marks rules
                $distributeMarksRules = [];
                foreach (json_decode($examRule->marks_distribution) as $rule){
                    $distributeMarksRules[$rule->type] = [
                        'total_marks' => $rule->total_marks,
                        'pass_marks' => $rule->pass_marks
                    ];
                }

                $distributedMarks = $request->get('type');
                $absent = $request->get('absent');
                $timeStampNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Kolkata'));
                $userId = auth()->user()->id;

                $marksData = [];
                $isInvalid = false;
                $message = '';
                $data = [];
                foreach ($request->get('registrationIds') as $student){
                    $marks = $distributedMarks[$student];

                    // foreach (array_keys($distributedMarks[$student]) as $key) {
                    // For all marks

                    // Remove This Below Comment For make default Absent instade of N/A
                    
                    try{
                        if($subject_id == 0 ) {
                            foreach ($distributedMarks[$student] as $subject => $value) {
                                foreach ($marks[$subject] as $key => $value) {
                                        if( !empty($absent[$student][$subject][$key]) && $absent[$student][$subject][$key]  == 'on' ) {
                                            $marks[$subject][$key] = floatval(-1);
                                        }
                                }
                            }
                        } else {
                            foreach ($distributedMarks[$student] as $subject => $value) {
                                foreach ($marks[$subject] as $key => $value) {
                                        if( !empty($absent[$student][$subject][$key]) && $absent[$student][$subject][$key]  == 'on' ) {
                                            $marks[$subject][$key] = floatval(-1);
                                        }
                                }
                            }
                        }
                    } catch( \Exception $ex) {
                        continue;
                    }
                    foreach ($marks as $sub_id => $mark) {
                        [$isInvalid, $message, $totalMarks, $grade, $point] = $this->processMarksAndCalculateResult($examRule,
                            $gradingRules, $distributeMarksRules, $mark);
                        if($isInvalid){
                            break;
                        }
                        $data = [
                            'academic_year_id' => $acYear,
                            'class_id' => $class_id,
                            'section_id' => $section_id,
                            'registration_id' => $student,
                            'exam_id' => $exam_id,
                            'total_marks' => $totalMarks,
                            'grade' => $grade,
                            'point' => $point,
                            'subject_id' => $sub_id,
                            'marks' =>  json_encode($mark),
                            'present' => isset($absent[$student]) ? '0' : '1',
                            "created_at" => $timeStampNow,
                            "created_by" => $userId,
                        ];
                        
                        Mark::updateOrCreate([
                            'class_id'          =>  $data['class_id'],
                            'section_id'        =>  $data['section_id'],
                            'registration_id'   =>  $data['registration_id'],
                            'exam_id'           =>  $data['exam_id'],
                            'subject_id'        => $sub_id,
                        ],$data);
                    }
                }
                // $sectionInfo = Section::where('id', $section_id)->first();
                // $subjectInfo = Subject::with(['class' => function($query){
                //     $query->select('name','id');
                // }])->where('id', $subject_id)->first();
                //now notify the admins about this record
                
                // NEED TO ASK FOR MULTIPLE UPDATES | KMJ
                    // $msg = "Class {$subjectInfo->class->name}, section {$sectionInfo->name}, {$subjectInfo->name} subject marks updated for {$examInfo->name} exam  by ".auth()->user()->name;
                    // $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
                
                // Notification end
                $sec = Section::findOrFail($section_id);
                return redirect()->route('marks.listing', [$class_id, $sec->name])->with("success","Exam marks updated successfully!");

            }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, $class = null, $section = null)
    {
        $students = collect();
        $acYear = null;
        $class_id = $class;
        $section_id = $section;
        $default_selected_section = $section;
        $subject_id = null;
        $exam_id = null;
        $sections = [];
        $academic_years = [];
        $subjects = [];
        $examRule = null;
        $exams = [];


        if ($section && $class) {
            $sections = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->pluck('name', 'id');

            $exams = Exam::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->orderBy('name', 'asc')->get();

            $default_selected_section = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->where('name', $section_id)
                ->first()->id;
        }
        if ($request->isMethod('post')) {
            if(AppHelper::getInstituteCategory() == 'college') {
                $acYear = $request->get('academic_year_id', 0);
            }
            else{
                $acYear = AppHelper::getAcademicYear();
            }
            $class_id = $request->get('class_id',0);
            $section_id = $request->get('section_id',0);
            $subject_id = $request->get('subject_id',0);
            $exam_id = $request->get('exam_id',0);


            // some validation before full the student
            $examInfo = Exam::where('status', AppHelper::ACTIVE)
                ->where('id', $exam_id)
                ->first();
            if(!$examInfo) {
                return redirect()->back()->with('error', 'Exam Not Found');
            }

            $examRule = ExamRule::where('exam_id',$exam_id);
            if( !empty($subject_id) ) {
                $examRule->where('subject_id', $subject_id);
            }

            $examRule = $examRule->first();

            if(!$examRule) {
                return redirect()->back()->with('error', 'Exam rules not found for this subject and exam!');
            }

            $entryExists = Mark::where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->where('section_id', $section_id)
                ->where('exam_id', $exam_id);

            if( !empty($subject_id) ) {
                $entryExists->where('subject_id', $subject_id);
            }
            $entryExists = $entryExists->count();



            // dd($entryExists);
            // Check Condition for single suject only
            if( $entryExists) {
                return redirect()->back()->with('error','Marks is already exists for this exam!');
            }

            //validation end

            $students = Registration::with(['info' => function($query){
                $query->select('name','id');
                }])->where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->where('section_id', $section_id)
                ->select( 'regi_no', 'roll_no', 'id','student_id')
                ->orderBy('roll_no','asc')
                ->get();

            $sections = Section::with('student')->where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->where('id', $section_id)
                ->first();
            
            $teacherId = 0;
            if(session('user_role_id',0) == AppHelper::USER_TEACHER){
                $teacherId = auth()->user()->teacher->id;
            }
            $subjects = Subject::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->when($teacherId, function ($query) use($teacherId){
                    $query->where('teacher_id', $teacherId);
                });

                if( !empty($subject_id) ) {
                    $subjects->where('id', $subject_id);
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
                ->where('class_id', $class_id);

            if( $exam_id != 0 ) {
                $exams->where('id', $exam_id);
            }
            $exams = $exams->first();
                // ->pluck('name', 'id');

        }

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        return view('backend.exam.marks.add', compact('students',
            'acYear',
            'class_id',
            'section_id',
            'subject_id',
            'exam_id',
            'classes',
            'sections',
            'subjects',
            'academic_years',
            'exams',
            'examRule',
            'default_selected_section',
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validateRules = [
            'academic_year_id' => 'nullable|integer',
            'class_id' => 'required|integer',
            'section_id' => 'required|integer',
            'subject_id' => 'nullable|integer',
            'exam_id' => 'required|integer',
            'registrationIds' => 'required|array',
            'type' => 'required|array',
            'absent' => 'nullable|array',
        ];

        $this->validate($request, $validateRules);
        if(AppHelper::getInstituteCategory() == 'college') {
            $acYear = $request->get('academic_year_id');
        }
        else{
            $acYear = AppHelper::getAcademicYear();
        }

        $class_id = $request->get('class_id',0);
        $section_id = $request->get('section_id',0);
        $subject_id = $request->get('subject_id',0);
        $exam_id = $request->get('exam_id',0);

        // some validation before entry the mark
        $examInfo = Exam::where('status', AppHelper::ACTIVE)
            ->where('id', $exam_id)
            ->first();
        
        if(!$examInfo) {
            return redirect()->route('marks.create')->with('error', 'Exam Not Found');
        }

        $examRule = ExamRule::where('exam_id',$exam_id);
        if( !empty($subject_id) )
            $examRule->where('subject_id', $subject_id);
        $examRule = $examRule->first();

        if(!$examRule) {
            return redirect()->route('marks.create')->with('error', 'Exam rules not found for this subject and exam!');
        }

        $entryExists = Mark::where('academic_year_id', $acYear)
            ->where('class_id', $class_id)
            ->where('section_id', $section_id)
            ->where('exam_id', $exam_id);

        if( !empty($subject_id) )
            $entryExists->where('subject_id', $subject_id);

        $entryExists = $entryExists->count();

        // Remove For Multile Update Marks
        /*
           if(!empty($subject_id) &&  $entryExists){
                return redirect()->route('marks.create')->with('error','This subject marks already exists for this exam!');
            }
        */
        //validation end
        
        //pull grading information
        $grade = Grade::where('id', $examRule->grade_id)->first();

        if(!$grade){
            return redirect()->route('marks.create')->with('error','Grading information not found!');
        }
        $gradingRules = json_decode($grade->rules);

        //exam distributed marks rules
        $distributeMarksRules = [];
        foreach (json_decode($examRule->marks_distribution) as $rule){
            $distributeMarksRules[$rule->type] = [
                'total_marks' => $rule->total_marks,
                'pass_marks' => $rule->pass_marks
            ];
        }

        $distributedMarks = $request->get('type');
        $absent = $request->get('absent');
        $timeStampNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Dhaka'));
        $userId = auth()->user()->id;

        $marksData = [];
        $isInvalid = false;
        $message = '';
        $data = [];

        foreach ($request->get('registrationIds') as $student){
            $marks = $distributedMarks[$student];
            try{
                if($subject_id == 0 ) {
                    foreach ($distributedMarks[$student] as $subject => $value) {
                        foreach ($marks[$subject] as $key => $value) {
                                if( !empty($absent[$student][$subject][$key]) && $absent[$student][$subject][$key]  == 'on' ) {
                                    $marks[$subject][$key] = floatval(-1);
                                }
                        }
                    }
                } else {
                    foreach ($distributedMarks[$student] as $subject => $value) {
                        foreach ($marks[$subject] as $key => $value) {
                                if( !empty($absent[$student][$subject][$key]) && $absent[$student][$subject][$key]  == 'on' ) {
                                    $marks[$subject][$key] = floatval(-1);
                                }
                        }
                    }
                }
            } catch( \Exception $ex) {
                continue;
            }
            foreach ($marks as $sub_id => $mark) {
                [$isInvalid, $message, $totalMarks, $grade, $point] = $this->processMarksAndCalculateResult($examRule,
                    $gradingRules, $distributeMarksRules, $mark);

                if($isInvalid){
                    break;
                }
                $data = [
                    'academic_year_id' => $acYear,
                    'class_id' => $class_id,
                    'section_id' => $section_id,
                    'registration_id' => $student,
                    'exam_id' => $exam_id,
                    'total_marks' => $totalMarks,
                    'grade' => $grade,
                    'point' => $point,
                    'subject_id' => $sub_id,
                    'marks' =>  json_encode($mark),
                    'present' => isset($absent[$student]) ? '0' : '1',
                    "created_at" => $timeStampNow,
                    "created_by" => $userId,
                ];
                Mark::updateOrCreate([
                    'class_id'          =>  $data['class_id'],
                    'section_id'        =>  $data['section_id'],
                    'registration_id'   =>  $data['registration_id'],
                    'exam_id'           =>  $data['exam_id'],
                    'subject_id'        => $sub_id,
                ],$data);
            }
        }
        if($isInvalid){
            return redirect()->route('marks.create')->with('error', $message);
        }

        // DB::beginTransaction();
        // try {

        //     Mark::insert($marksData);
        //     DB::commit();
        // }
        // catch(\Exception $e){
        //     DB::rollback();
        //     $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
        //     return redirect()->route('marks.create')->with("error",$message);
        // }

        $sectionInfo = Section::where('id', $section_id)->first();
        $subjectInfo = Subject::with(['class' => function($query){
            $query->select('name','id');
        }])->where('id', $subject_id)->first();

        // NEED TO CONFIRM FOR MULTIPLE RECORDS
        /*
           now notify the admins about this record 
            $msg = "Class {$subjectInfo->class->name}, section {$sectionInfo->name}, {$subjectInfo->name} subject marks added for {$examInfo->name} exam  by ".auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            Notification end
        */

        return redirect()->route('marks.create')->with("success","Exam marks added successfully!");


    }


    /**
     * Process student entry marks and
     * calculate grade point
     *
     * @param $examRule collection
     * @param $gradingRules array
     * @param $distributeMarksRules array
     * @param $strudnetMarks array
     */
    private function processMarksAndCalculateResult($examRule, $gradingRules, $distributeMarksRules, $studentMarks) {
        $totalMarks = 0;
        $isFail = false;
        $isInvalid = false;
        $message = "";

        foreach ($studentMarks as $type => $marks){
            $marks = floatval($marks);
            $totalMarks += $marks;

            // AppHelper::PASSING_RULES
            if(in_array($examRule->passing_rule, [2,3])){
                if($marks > $distributeMarksRules[$type]['total_marks']){
                    $isInvalid = true;
                    $message = AppHelper::MARKS_DISTRIBUTION_TYPES[$type]. " marks is too high from exam rules marks distribution!";
                    break;
                }

                if($marks < $distributeMarksRules[$type]['pass_marks']){
                    $isFail = true;
                }
            }
        }

        //fraction number make ceiling
        $totalMarks = ceil($totalMarks);

        // AppHelper::PASSING_RULES
        if(in_array($examRule->passing_rule, [1,3])){
            if($totalMarks < $examRule->over_all_pass){
                $isFail = true;
            }
        }

        if($isFail){
            $grade = 'F';
            $point = 0.00;

            return [$isInvalid, $message, $totalMarks, $grade, $point];
        }

        [$grade, $point] = $this->findGradePointFromMarks($gradingRules, $totalMarks);

        return [$isInvalid, $message, $totalMarks, $grade, $point];

    }

    private function findGradePointFromMarks($gradingRules, $marks) {
        $grade = 'F';
        $point = 0.00;

        foreach ($gradingRules as $rule){
            if ($marks >= $rule->marks_from && $marks <= $rule->marks_upto){
                $grade = AppHelper::GRADE_TYPES[$rule->grade];
                $point = $rule->point;
                break;
            }
        }
        return [$grade, $point];
    }

    private function findGradeFromPoint($point, $gradingRules) {
        $grade = 'F';

        foreach ($gradingRules as $rule){
            if($point >= floatval($rule->point)){
                $grade = AppHelper::GRADE_TYPES[$rule->grade];
                break;
            }
        }
        return $grade;
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  $id integer
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $marks = Mark::with(['subject' => function($query){
            $query->select('id','teacher_id');
        }])
            ->with(['student' => function($query){
                $query->with(['info' => function($query){
                    $query->select('name','id');
                }])->select('regi_no','student_id','roll_no','id');
            }])
            ->where('id',$id)
            ->select( 'id','academic_year_id','class_id','subject_id','exam_id','registration_id', 'marks', 'total_marks', 'present')
            ->first();

        if(!$marks){
            abort(404);
        }

        //these code for security purpose,
        // if some user try to edir other user data
        if(session('user_role_id',0) == AppHelper::USER_TEACHER){
            $teacherId = auth()->user()->teacher->id;
            if($marks->subject->teacher_id != $teacherId){
                abort(401);
            }
        }

        //check is result is published?
        $isPublish = DB::table('result_publish')
            ->where('academic_year_id', $marks->academic_year_id)
            ->where('class_id', $marks->class_id)
            ->where('exam_id', $marks->exam_id)
            ->count();

        if($isPublish){
            return redirect()->route('marks.index')->with('error', 'Can\'t edit marks, because result is published for this exam!');
        }

        $examRule = ExamRule::where('exam_id', $marks->exam_id)
            ->where('subject_id', $marks->subject_id)
            ->first();
        if(!$examRule) {
            return redirect()->route('marks.index')->with('error', 'Exam rules not found for this subject and exam!');
        }


        return view('backend.exam.marks.edit-single-marks', compact('marks',
            'examRule'
        ));


    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  $id integer
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $marks = Mark::with(['subject' => function($query){
            $query->select('id','teacher_id','name');
        }])
            ->with(['class' => function($query){
                $query->select('id','name');
            }])
            ->with(['exam' => function($query){
                $query->select('id','name');
            }])
            ->where('id',$id)
            ->first();

        if(!$marks){
            abort(404);
        }

        //these code for security purpose,
        // if some user try to edir other user data
        if(session('user_role_id',0) == AppHelper::USER_TEACHER){
            $teacherId = auth()->user()->teacher->id;
            if($marks->subject->teacher_id != $teacherId){
                abort(401);
            }
        }

        //check is result is published?
        $isPublish = DB::table('result_publish')
            ->where('academic_year_id', $marks->academic_year_id)
            ->where('class_id', $marks->class_id)
            ->where('exam_id', $marks->exam_id)
            ->count();

        if($isPublish){
            return redirect()->route('marks.index')->with('error', 'Can\'t edit marks, because result is published for this exam!');
        }

        $examRule = ExamRule::where('exam_id', $marks->exam_id)
            ->where('subject_id', $marks->subject_id)
            ->first();
        if(!$examRule) {
            return redirect()->route('marks.index')->with('error', 'Exam rules not found for this subject and exam!');
        }

        $validateRules = [
            'type' => 'required|array',
            'absent' => 'nullable',
        ];

        $this->validate($request, $validateRules);

        //pull grading information
        $grade = Grade::where('id', $examRule->grade_id)->first();
        if(!$grade){
            return redirect()->route('marks.create')->with('error','Grading information not found!');
        }
        $gradingRules = json_decode($grade->rules);

        //exam distributed marks rules
        $distributeMarksRules = [];
        foreach (json_decode($examRule->marks_distribution) as $rule){
            $distributeMarksRules[$rule->type] = [
                'total_marks' => $rule->total_marks,
                'pass_marks' => $rule->pass_marks
            ];
        }




        [$isInvalid, $message, $totalMarks, $grade, $point] = $this->processMarksAndCalculateResult($examRule,
            $gradingRules, $distributeMarksRules, $request->get('type'));

        if($isInvalid){
            return redirect()->back()->with('error', $message);
        }

        $data = [
            'marks' => json_encode($request->get('type')),
            'total_marks' => $totalMarks,
            'grade' => $grade,
            'point' => $point,
            'present' => $request->has('absent') ? '0' : '1',
        ];

        $marks->fill($data);
        $marks->save();

        //now notify the admins about this record
        $msg = "Class {$marks->class->name},  {$marks->subject->name} subject marks updated for {$marks->exam->name} exam  by ".auth()->user()->name;
        $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
        // Notification end

        return redirect()->route('marks.index')->with('success', 'Marks entry updated!');


    }

    /**
     * Published Result list
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function resultIndex(Request $request){

        if (AppHelper::getInstituteCategory() == 'college') {
            $acYear = $request->get('academic_year_id', 0);
        } else {
            $acYear = AppHelper::getAcademicYear();
        }
        $class_id = $request->get('class_id', 0);
        $section_id = $request->get('section_id', 0);
        $exam_id = $request->get('exam_id', 0);
        $smstrigger = $request->get('smstrigger', null);
        $students = collect();
        $sections = [];
        $exams = [];
        $academic_years = [];

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
        ->pluck('name', 'id');

        //in post request get the result and show it
        if ($request->isMethod('post')) {

            //check is result is published?
            $isPublish = DB::table('result_publish')
                ->where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->where('exam_id', $exam_id)
                ->count();

            if(!$isPublish){
                return redirect()->back()->with('error', 'Result not published for this class and exam yet!');
            }

            $students = Registration::where('status', AppHelper::ACTIVE)
                ->where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->when($section_id, function ($query) use($section_id){
                    $query->where('section_id', $section_id);
                })
                ->select('id','student_id','regi_no','roll_no','section_id', 'class_id')
                ->with(['result' => function($query) use($exam_id){
                    $query->select('registration_id','total_marks','grade', 'point', 'exam_id')
                        ->where('exam_id', $exam_id);
                    $query->with(['exam' => function($query){
                        $query->select('id', 'name');
                    }]);
                }])
                ->with(['marks' => function($query) use($exam_id){
                    $query->select('registration_id', 'subject_id', 'exam_id', 'marks', 'total_marks', 'grade', 'point')
                        ->where('exam_id', $exam_id);
                    $query->with(['subject' => function($query){
                        $query->select('id', 'name');
                    }]);
                }])
                ->with(['info' => function($query){
                    $query->select('name','id', 'phone_no', 'father_phone_no', 'mother_phone_no', 'guardian_phone_no');
                }])
                ->with(['section' => function($query){
                    $query->select('name','id');
                }])
                ->with(['class' => function($query){
                    $query->select('name','id');
                }])
                ->get();

            if($smstrigger) {
                Acknowledgement::create([
                    'acknowledged' => $smstrigger,
                    'module' => 'ExamResult,SMS'
                ]);
                
                SendExamResult::dispatchNow($students);

                $msg = 'Result message will be delevered to the '. $classes[$class_id] .' students soon!';
                
                return redirect()->route('result.index')->with('success', $msg);
            }

            $sections = Section::where('status', AppHelper::ACTIVE)
            ->where('class_id', $class_id)
            ->pluck('name', 'id');
    
            $exams = Exam::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->pluck('name', 'id');
        }

        //if its college then have to get those academic years
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        return view('backend.exam.result.list', compact('students',
            'acYear',
            'class_id',
            'classes',
            'section_id',
            'sections',
            'academic_years',
            'exams',
            'exam_id'
        ));
    }

    /**
     * Published Result Generate
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function resultGenerate(Request $request){

        //in post request get the result and show it
        if ($request->isMethod('post')) {
            $rules = [
                'class_id' => 'required|integer',
                'exam_id' => 'required|integer',
                'publish_date' => 'required|min:10|max:11',
            ];

            //if it college then need another 2 feilds
            if(AppHelper::getInstituteCategory() == 'college') {
                $rules['academic_year_id'] = 'required|integer';
            }

            $this->validate($request, $rules);


            if (AppHelper::getInstituteCategory() == 'college') {
                $acYear = $request->get('academic_year_id', 0);
            } else {
                $acYear = AppHelper::getAcademicYear();
            }
            $class_id = $request->get('class_id', 0);
            $exam_id = $request->get('exam_id', 0);
            $publish_date = Carbon::createFromFormat('d/m/Y', $request->get('publish_date', date('d/m/Y')));

            //validation start
            //check is result is published?
            $isPublish = DB::table('result_publish')
                ->where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->where('exam_id', $exam_id)
                ->count();

            if($isPublish){
                return redirect()->back()->with('error', 'Result already published for this class and exam!');
            }


            //this class all section mark submitted
            $unsubmittedSections = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->whereDoesntHave('marks', function ($query) use($exam_id, $acYear, $class_id) {
                    $query->select('section_id')
                        ->where('academic_year_id', $acYear)
                        ->where('class_id', $class_id)
                        ->where('exam_id', $exam_id)
                        ->groupBy('section_id');
                })
                ->count();


            if($unsubmittedSections){
                return redirect()->back()->with('error', 'All sections marks are not submitted yet!');
            }

            //this class all subject mark submitted
            // $subjectsCount = Subject::where('status', AppHelper::ACTIVE)
            //     ->where('class_id', $class_id)->count();

            // Updated Query For Subject Count ------- 23 Oct, 2019 
            $subjectsCount = ExamRule::where('class_id', $class_id)->where('exam_id', $exam_id)->count();

            $submittedSubjectMarksBySection = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->with(['marks' => function ($query) use($exam_id, $acYear, $class_id) {
                    $query->select('subject_id','section_id')
                        ->where('academic_year_id', $acYear)
                        ->where('class_id', $class_id)
                        ->where('exam_id', $exam_id)
                        ->groupBy('subject_id','section_id');
                }])
                ->get();


            $havePedningSectionMarks = false;
            $pendingSections = [];
            foreach ($submittedSubjectMarksBySection as $section){
                if(count($section->marks) != $subjectsCount){
                    $pendingSections[] = $section->name;
                    $havePedningSectionMarks = true;
                }
            }
            if($havePedningSectionMarks){
                $message = "Section ".implode(',' , $pendingSections)." all subjects marks not submitted yet!";
                return redirect()->back()->with('error', $message);
            }
            //validation end

            //now generate the result
            // pull default grading system
            $grade_id = AppHelper::getAppSettings('result_default_grade_id');
            $grade = Grade::where('id', $grade_id)->first();
            if(!$grade){
                return redirect()->back()->with('error', 'Result grade system not set! Set it from institute settings.');
            }
            // pull exam info
            $examInfo = Exam::where('status', AppHelper::ACTIVE)
                ->where('id', $exam_id)
                ->first();
            if(!$examInfo) {
                return redirect()->back()->with('error', 'Exam Not Found');
            }

            // pull exam rules subject wise and find combine subject
            $examRules = ExamRule::where('class_id', $class_id)
                ->where('exam_id', $examInfo->id)
                ->select('subject_id','combine_subject_id','passing_rule','marks_distribution','total_exam_marks','over_all_pass')
                ->with(['subject' => function($query){
                    $query->select('id','type');
                }])
                ->get()
                ->reduce(function ($examRules, $rule){
                    $examRules[$rule->subject_id] =[
                        'combine_subject_id' => $rule->combine_subject_id,
                        'passing_rule' => $rule->passing_rule,
                        'marks_distribution' => json_decode($rule->marks_distribution),
                        'total_exam_marks' => $rule->total_exam_marks,
                        'over_all_pass' => $rule->over_all_pass,
                        'subject_type' => $rule->subject->getOriginal('type')
                    ];
                    return $examRules;
                });

            $totalRules = count(array_keys($examRules));

            if($subjectsCount != $totalRules){
                return redirect()->back()->with('error', 'Some subjects exam rules missing!');
            }

            //pull students with marks
            $students = Registration::where('status', AppHelper::ACTIVE)
                ->where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->select('id','fourth_subject','alt_fourth_subject')
                ->with(['marks' => function($query) use($acYear,$class_id,$exam_id){
                    $query->select('registration_id','subject_id','marks', 'total_marks', 'point', 'present')
                        ->where('academic_year_id', $acYear)
                        ->where('class_id', $class_id)
                        ->where('exam_id', $exam_id);
                }])
                ->get();

            $isDataMissing = false;
            $registrationIdForMissing = 0;
            $resultInsertData = [];
            $combineResultInsertData = [];
            $gradingRules = json_decode($grade->rules);
            $userId = auth()->user()->id;

            //loop  the students
            foreach ($students as $student){
                try {
                    $totalMarks = 0;
                    $totalPoint = 0;
                    $totalSubject = 0;
                    $combineSubjectsMarks = [];
                    $isFail = false;

                    //data missing checks
                    $studentMarksSubjectCount = count($student->marks);
                    if ($studentMarksSubjectCount != $subjectsCount) {
                        $isDataMissing = true;
                        $registrationIdForMissing = $student->id;
                        break;
                    }
                    foreach ($student->marks as $marks) {
                        //find combine subjects
                        $isAndInCombineSubject = $this->isAndInCombine($marks->subject_id, $examRules);
                        if ($isAndInCombineSubject) {
                            $combineSubjectsMarks[$marks->subject_id] = $marks;
                            //skip for next subject
                            continue;
                        }
                        //find 4th subject AppHelper::SUBJECT_TYPE
                        $is4thSubject = ($examRules[$marks->subject_id]['subject_type'] == 2) ? 1 : 0;
                        if ($is4thSubject) {

                            if ($student->fourth_subject == $marks->subject_id && $marks->point >= $examInfo->elective_subject_point_addition) {
                                $totalPoint += ($marks->point - $examInfo->elective_subject_point_addition);
                            }

                            //if its college then may have student exchange their 4th subject
                            //with main subject
                            if(AppHelper::getInstituteCategory() == 'college') {
                                if ($student->alt_fourth_subject == $marks->subject_id) {
                                    $totalPoint += $marks->point;

                                    //if fail then result will be fail
                                    if (intval($marks->point) == 0) {
                                        $isFail = true;
                                    }
                                    $totalSubject++;
                                }
                            }
                            //end college logic

                            $totalMarks += $marks->total_marks;

                            //skip for next subject
                            continue;
                        }

                        //process not combine and 4th subjects
                        if (!$isAndInCombineSubject && !$is4thSubject) {

                            //if its college then may have student exchange their 4th subject
                            //with main subject
                            if(AppHelper::getInstituteCategory() == 'college') {
                                if ($student->fourth_subject == $marks->subject_id) {
                                    if($marks->point >= $examInfo->elective_subject_point_addition){
                                        $totalPoint += ($marks->point - $examInfo->elective_subject_point_addition);
                                    }

                                    $totalMarks += $marks->total_marks;

                                    //skip for next subject
                                    continue;
                                }
                            }
                            //end college logic


                            $totalMarks += $marks->total_marks;
                            $totalPoint += $marks->point;
                            $totalSubject++;
                            if (intval($marks->point) == 0) {
                                $isFail = true;
                            }

                        }
                    }

                    //now process combine subjects
                    foreach ($examRules as $subject_id => $data) {
                        if ($data['combine_subject_id'] != null) {
                            $pairTotalMarks = 0;
                            $pairFail = false;
                            $totalSubject++;
                            $cs = explode(",", trim($data['combine_subject_id']));
                            $subjectMarks = $combineSubjectsMarks[$subject_id];
                            $combineTotalMarks = $subjectMarks->total_marks;
                            foreach($cs as $pair) {
                                if(!isset($combineSubjectsMarks[$pair])) continue;
                                $pairSubjectMarks = $combineSubjectsMarks[$pair];
                                $pairSubjectMarks = $combineSubjectsMarks[$pair];
                                [$pairFail, $combineTotalMarks, $pairTotalMarks] = $this->processCombineSubjectMarks($subjectMarks, $pairSubjectMarks, $data, $examRules[$pair]);
                            }
                            
                            $totalMarks += $pairTotalMarks;

                            if ($pairFail) {
                                //AppHelper::GRADE_TYPES
                                $pairGrade = "F";
                                $pairPoint = 0.00;
                                $isFail = true;
                            } else {

                                [$pairGrade, $pairPoint] = $this->findGradePointFromMarks($gradingRules, $pairTotalMarks);
                                $totalPoint += $pairPoint;
                            }

                            //need to store in db for marks sheet print
                            $combineResultInsertData[] = [
                                'registration_id' => $student->id,
                                'subject_id' => $subject_id,
                                'exam_id' => $examInfo->id,
                                'total_marks' => $combineTotalMarks,
                                'grade' => $pairGrade,
                                'point' => $pairPoint,
                            ];

                        }
                    }

                    // dd([$totalPoint, $totalSubject]);
                    $finalPoint = ($totalPoint / $totalSubject);
                    // dd([$gradingRules, $finalPoint, AppHelper::GRADE_TYPES]);
                    if ($isFail) {
                        //AppHelper::GRADE_TYPES
                        $finalGrade = 'F';
                    } else {
                        $finalGrade = $this->findGradeFromPoint($finalPoint, $gradingRules);
                    }

                    $timeStampNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Kolkata'));
                    $resultInsertData[] = [
                        'academic_year_id' => $acYear,
                        'class_id' => $class_id,
                        'registration_id' => $student->id,
                        'exam_id' => $examInfo->id,
                        'total_marks' => $totalMarks,
                        'grade' => $finalGrade,
                        'point' => $finalPoint,
                        "created_at" => $timeStampNow,
                        "created_by" => $userId,
                        "updated_at" =>  $timeStampNow,  # new \Datetime()
                        'updated_by' => $userId,
                    ];

                }
                catch (\Exception $e){
                    throw new \Exception($e);
                    Log::error($e);
                    $isDataMissing = true;
                    $registrationIdForMissing = $student->id;
                    break;
                }

            }

            //if have any invalid data and can't process
            //result then show error message
            if($isDataMissing){
                $student = Registration::where('id', $registrationIdForMissing)->with(["info" => function($query){
                    $query->select('name','id');
                }])
                    ->with(["section" => function($query){
                        $query->select('name','id');
                    }])
                    ->select('id','student_id','section_id','regi_no','roll_no')
                    ->first();
                $message = "Student '{$student->info->name}', Section: {$student->section->name}, Regi No.:{$student->regi_no}, Roll No.:{$student->roll_no} has invalid marks data!";
                return redirect()->back()->with('error', $message);
            }


            //now insert into db with transaction enabled

            DB::beginTransaction();
            try {

                Result::insert($resultInsertData);
                DB::table('result_publish')->insert([
                    'academic_year_id' => $acYear,
                    'class_id' => $class_id,
                    'exam_id' => $exam_id,
                    'publish_date' => $publish_date->format('Y-m-d')
                ]);
                DB::table('result_combines')->insert($combineResultInsertData);
                DB::commit();
            }
            catch(\Exception $e){
                DB::rollback();
                $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
                return redirect()->back()->with("error",$message);
            }

            return redirect()->back()->with("success","Result generated successfully.");
        }


        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');
        $academic_years = [];
        //if its college then have to get those academic years
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }
        $exams = [];


        return view('backend.exam.result.generate', compact('classes', 'academic_years','exams'));
    }

    private function isAndInCombine($subject_id, $rules){
        $isCombine = false;
        foreach ($rules as $subject => $data){
            $cs = explode(",", trim($data['combine_subject_id']));
            if($subject == $subject_id && (!empty($cs) && $cs[0])){
                $isCombine = true;
                break;
            }

            if(in_array($subject_id, $cs)){
                $isCombine = true;
                break;
            }
        }

        return $isCombine;
    }
    private function processCombineSubjectMarks($subjectMarks, $pairSubjectMarks, $subjectRule, $pairSubjectRule){
        $pairFail = false;
        $combineTotalMarks = ($subjectMarks->total_marks + $pairSubjectMarks->total_marks);

        if($subjectRule['total_exam_marks'] == $pairSubjectRule['total_exam_marks']){
            //dividing factor
            $totalMarks = ($combineTotalMarks/2);
        }
        else{
            //if both subject exam marks not same then it must be 2:1 ratio
            //Like: subject marks 100 pair subject marks 50
            $totalMarks = ($combineTotalMarks/ 1.5);
        }

        //fraction number make ceiling
        $totalMarks = ceil($totalMarks);

        $passingRule = $subjectRule['passing_rule'];
        // AppHelper::PASSING_RULES
        if(in_array($passingRule, [1,3])){
            if($totalMarks < $subjectRule['over_all_pass']){
                $pairFail = true;
            }
        }

        //if any subject absent then its fail
        if($subjectMarks->present == 0 || $pairSubjectMarks->present == 0){
            $pairFail = true;
        }

        // AppHelper::PASSING_RULES
        if(!$pairFail && in_array($passingRule, [2,3])){

            //acquire marks
            $combineDistributedMarks = [];
            foreach (json_decode($subjectMarks->marks) as $key => $distMarks){
                $combineDistributedMarks[$key] = floatval($distMarks);

            }

            foreach (json_decode($pairSubjectMarks->marks) as $key => $distMarks){
                $combineDistributedMarks[$key] += floatval($distMarks);

            }


            //passing rules marks
            $combineDistributeMarks = [];
            foreach ($subjectRule['marks_distribution'] as $distMarks){
                $combineDistributeMarks[$distMarks->type] = floatval($distMarks->pass_marks);
            }

            foreach ($pairSubjectRule['marks_distribution'] as $key => $distMarks){
                $combineDistributeMarks[$distMarks->type] += floatval($distMarks->pass_marks);

            }

            //now check for pass
            foreach ($combineDistributeMarks as $key => $value){
                if($combineDistributedMarks[$key] < $value){
                    $pairFail = true;
                }
            }

        }


        return [$pairFail, $combineTotalMarks, $totalMarks];

    }

    /**
     * Published Result Delete
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function resultDelete(Request $request){

        //in post request get the result and show it
        if ($request->isMethod('post')) {
            $rules = [
                'class_id' => 'required|integer',
                'exam_id' => 'required|integer',
            ];

            //if it college then need another 2 feilds
            if(AppHelper::getInstituteCategory() == 'college') {
                $rules['academic_year_id'] = 'required|integer';
            }

            $this->validate($request, $rules);


            if (AppHelper::getInstituteCategory() == 'college') {
                $acYear = $request->get('academic_year_id', 0);
            } else {
                $acYear = AppHelper::getAcademicYear();
            }
            $class_id = $request->get('class_id', 0);
            $exam_id = $request->get('exam_id', 0);

            //validation start
            //check is result is published?
            $isPublish = DB::table('result_publish')
                ->where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->where('exam_id', $exam_id)
                ->count();

            if(!$isPublish){
                return redirect()->back()->with('error', 'Result not published for this class and exam!');
            }


            //now delete data from db with transaction enabled
            DB::beginTransaction();
            try {

                $studentIds = Result::where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('exam_id', $exam_id)
                    ->pluck('registration_id');

                DB::table('results')
                    ->where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('exam_id', $exam_id)
                    ->delete();

                DB::table('result_publish')
                    ->where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('exam_id', $exam_id)
                    ->delete();

                DB::table('result_combines')
                    ->where('exam_id', $exam_id)
                    ->whereIn('registration_id', $studentIds)
                    ->delete();

                DB::commit();
            }
            catch(\Exception $e){
                DB::rollback();
                $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
                return redirect()->back()->with("error",$message);
            }

            return redirect()->back()->with("success","Result deleted successfully.");
        }


        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');
        $academic_years = [];
        //if its college then have to get those academic years
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }
        $exams = [];

        return view('backend.exam.result.delete', compact('classes', 'academic_years','exams'));
    }
}
