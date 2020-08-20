<?php

namespace App\Http\Controllers\Backend;

use DB;
use Log;
use PDF;
use App\Exam;
use App\ExamRulesTemplate;
use App\DefaultExamRule;
use App\ExamRule;
use App\Grade;
use App\Http\Controllers\Controller;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Subject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Template;
use App\ExamTimeTable;
use App\Registration;

class ExamController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // check for ajax request here
        if($request->ajax()){

            //exam list by class
            $class_id = $request->query->get('class_id', 0);
            if($class_id){
                $exams = Exam::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $class_id)
                    ->select('name as text', 'id')
                    ->orderBy('name', 'asc')->get();

                return response()->json($exams);
            }

            // single exam details
            $exam_id = $request->query->get('exam_id', 0);
            $examInfo = Exam::select('marks_distribution_types')
                ->where('id',$exam_id)
                ->where('status', AppHelper::ACTIVE)
                ->first();
            if($examInfo){
                $marksDistributionTypes = [];
                foreach (json_decode($examInfo->marks_distribution_types) as $type){
                    $marksDistributionTypes[] = [
                        'id' => $type,
                        'text' => AppHelper::MARKS_DISTRIBUTION_TYPES[$type]
                    ];
                }

                return response()->json($marksDistributionTypes);
            }
            return response('Exam not found!', 404);
        }

        $class_id = $request->query->get('class',0);
        $exams = Exam::iclass($class_id)->with('class')->get();

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');
        $iclass = $class_id;

        return view('backend.exam.list', compact('exams','classes', 'iclass'));
    }

    /**
     * Display a listing exam for public use
     *
     * @return \Illuminate\Http\Response
     */
    public function indexPublic(Request $request)
    {
        // check for ajax request here
        if($request->ajax()){

            //exam list by class
            $class_id = $request->query->get('class_id', 0);
            if($class_id){
                $exams = Exam::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $class_id)
                    ->select('name as text', 'id')
                    ->orderBy('name', 'asc')->get();

                return response()->json($exams);
            }

            // single exam details
            $exam_id = $request->query->get('exam_id', 0);
            $examInfo = Exam::select('marks_distribution_types')
                ->where('id',$exam_id)
                ->where('status', AppHelper::ACTIVE)
                ->first();
            if($examInfo){
                $marksDistributionTypes = [];
                foreach (json_decode($examInfo->marks_distribution_types) as $type){
                    $marksDistributionTypes[] = [
                        'id' => $type,
                        'text' => AppHelper::MARKS_DISTRIBUTION_TYPES[$type]
                    ];
                }

                return response()->json($marksDistributionTypes);
            }
            return response('Exam not found!', 404);
        }

        return response('Bad request!', 400);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $exam = null;
        $marksDistributionTypes = [1,2];

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');

        return view('backend.exam.add', compact('exam', 'marksDistributionTypes','classes'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $this->validate(
            $request, [
                'name' => 'required|max:255',
                'class_id' => 'required|integer',
                'elective_subject_point_addition' => 'required|numeric',
                'marks_distribution_types' => 'required|array',
            ]
        );

        
        $data = $request->all();

        if($data['template_id']) {
            $mdtOk = TRUE;
    
            foreach ($data['marks_distribution_types'] as $mdt) {
                $count = DefaultExamRule::where('marks_distribution', $mdt)->count();
                if(!$count) {
                    $mdtOk = FALSE;
                    break;
                }
            }
    
            if(!$mdtOk) {
                return redirect()->route('template.index')->with('error', 'All mark distribution types are not configured in the template. Please update');
            }
        }

        $data['marks_distribution_types'] = json_encode($data['marks_distribution_types']);

        DB::beginTransaction();
        try {
            // now save employee
            $exam = Exam::create($data);

            if($data['template_id']) {
                $rules = ExamRulesTemplate::where('id', $data['template_id'])->with('template')->first();
                $subjects = [];
                $inputs = [];
                foreach ($rules->template as $key => $rule){
                    if(!isset($subjects[$rule->subject_id])) {
                        $subjects[$rule->subject_id] = [];
                        $subjects[$rule->subject_id][] = $rule;
                    }else{
                        $subjects[$rule->subject_id][] = $rule;
                    }
                }
                foreach ($subjects as $subject_id => $subject){
                    $marksDistribution = [];
                    $combine_subject = json_decode($subject[0]->combine_subject);
                    foreach ($subject as $key => $rule){
                        $marksDistribution[] = [
                            'type' => $rule->marks_distribution,
                            'total_marks' => $rule->total_exam_marks,
                            'pass_marks' => $rule->over_all_pass,
                        ];
                    }
                    $inputs[] = [ 
                        'class_id' => $data['class_id'], 
                        'subject_id' => $subject_id,  
                        'grade_id' => $rules->grade_id, 
                        'passing_rule' => $rules->passing_rule,
                        'exam_id' => $exam->id,
                        'combine_subject_id' => $combine_subject ? implode(',', $combine_subject) : '',
                        'marks_distribution' => json_encode($marksDistribution),
                        "created_at" =>  Carbon::now(), # new \Datetime()
                        "updated_at" =>  Carbon::now(),  # new \Datetime()
                        'updated_by' => auth()->user()->id,
                        'created_by' => auth()->user()->id, 
                    ];
                }

                // return $inputs;
                ExamRule::insert($inputs); 
            }
            //now notify the admins about this record
            $msg = $data['name']." exam added by ".auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            DB::commit();

            return redirect()->route('exam.create')->with('success', 'Exam added!');
        }
        catch(\Exception $e){
            Log::error($e);
            DB::rollback();
            // throw new \Exception($e);
            $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
            return redirect()->route('exam.create')->with('error', $message);
        }
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  $id integer
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $exam = Exam::findOrFail($id);
        //todo: need protection to massy events. like modify after used or delete after user
        $marksDistributionTypes = json_decode($exam->marks_distribution_types,true);

        return view('backend.exam.add', compact('exam', 'marksDistributionTypes'));


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

        $exam = Exam::findOrFail($id);
        //todo: need protection to massy events. like modify after used or delete after user
        $this->validate(
            $request, [
                'name' => 'required|max:255',
                'elective_subject_point_addition' => 'required|numeric',
                'marks_distribution_types' => 'required|array',
            ]
        );


        $data = $request->all();
        unset($data['class_id']);
        $data['marks_distribution_types'] = json_encode($data['marks_distribution_types']);

        $exam->fill($data);
        $exam->save();

        return redirect()->route('exam.index')->with('success', 'Exam Updated!');
    }


    /**
     * Destroy the resource
     */
    public function destroy($id) {
        $exam = Exam::findOrFail($id);
        $exam->delete();
        //todo: need protection to massy events. like modify after used or delete after user
        return redirect()->route('exam.index')->with('success', 'Exam Deleted!');
    }

    /**
     * status change
     * @return mixed
     */
    public function changeStatus(Request $request, $id=0)
    {

        $exam =  Exam::findOrFail($id);
        if(!$exam){
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }

        $exam->status = (string)$request->get('status');

        $exam->save();

        return [
            'success' => true,
            'message' => 'Status updated.'
        ];

    }


    /**
     * grade  manage
     * @return \Illuminate\Http\Response
     */
    public function gradeIndex(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {//
            $this->validate($request, [
                'hiddenId' => 'required|integer',
            ]);
            $grade = Grade::findOrFail($request->get('hiddenId'));
            $haveRules = ExamRule::where('grade_id', $grade->id)->count();
            if($haveRules){
                return redirect()->route('exam.grade.index')->with('error', 'Can not delete! Grade used in exam rules.');
            }

            $grade->delete();

            //now notify the admins about this record
            $msg = $grade->name." grade deleted by ".auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            return redirect()->route('exam.grade.index')->with('success', 'Record deleted!');
        }

        // check for ajax request here
        if($request->ajax()){
            $grade_id = $request->query->get('grade_id', 0);
            $gradeInfo = Grade::select('rules')
                ->where('id',$grade_id)
                ->first();
            if($gradeInfo){
                $marks = [];
                foreach (json_decode($gradeInfo->rules) as $rule){
                    $marks[] = $rule->marks_from;
                    $marks[] = $rule->marks_upto;
                }

                sort($marks);
                //passing marks will be the last position in the array
                $totalMarks = $marks[count($marks) - 1];
                //passing marks will be the 3rd position in the array
                $passingMarks = $marks[2];

                $data = [
                    'totalMarks' => $totalMarks,
                    'passingMarks' => $passingMarks,
                ];

                return response()->json($data);
            }
            return response('Grade not found!', 404);
        }
        //for get request
        $grades = Grade::get();
        return view('backend.exam.grade.list', compact('grades'));
    }

    /**
     * grade create, read, update manage
     * @return \Illuminate\Http\Response
     */
    public function gradeCru(Request $request, $id=0)
    {
        //for save on POST request
        if ($request->isMethod('post')) {

            //protection to prevent massy event. Like edit grade after its use in rules
            // or marks entry
            if($id){
                $grade = Grade::find($id);
                //if grade use then can't edit it
                if($grade) {
                    $haveRules = ExamRule::where('grade_id', $grade->id)->count();
                    if ($haveRules) {
                        return redirect()->route('exam.grade.index')->with('error', 'Can not Edit! Grade used in exam rules.');
                    }
                }
            }

            $this->validate($request, [
                'name' => 'required|max:255',
                'grade' => 'required|array',
                'point' => 'required|array',
                'marks_from' => 'required|array',
                'marks_upto' => 'required|array',
            ]);

            $rules = [];
            $inputs = $request->all();
            foreach ($inputs['grade'] as $key => $value){
                $rules[] = [
                    'grade' => $value,
                    'point' => $inputs['point'][$key],
                    'marks_from' => $inputs['marks_from'][$key],
                    'marks_upto' => $inputs['marks_upto'][$key]
                ];
            }

            $data = [
                'name' => $request->get('name'),
                'rules' => json_encode($rules)
            ];

            Grade::updateOrCreate(
                ['id' => $id],
                $data
            );

            if(!$id){
                //now notify the admins about this record
                $msg = $data['name']." graded added by ".auth()->user()->name;
                $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
                // Notification end
            }


            $msg = "Grade ";
            $msg .= $id ? 'updated.' : 'added.';

            return redirect()->route('exam.grade.index')->with('success', $msg);
        }

        //for get request
        $grade = Grade::find($id);

        //if grade use then can't edit it
        if($grade) {
            $haveRules = ExamRule::where('grade_id', $grade->id)->count();
            if ($haveRules) {
                return redirect()->route('exam.grade.index')->with('error', 'Can not Edit! Grade used in exam rules.');
            }
        }

        return view('backend.exam.grade.add', compact('grade'));
    }

    /**
     * rule  manage
     * @return \Illuminate\Http\Response
     */
    public function ruleIndex(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {//
            $this->validate($request, [
                'hiddenId' => 'required|integer',
            ]);
            $rules = ExamRule::findOrFail($request->get('hiddenId'));
            $rules->delete();

            //now notify the admins about this record
            $msg = "Exam rules deleted by ".auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            return redirect()->route('exam.rule.index')->with('success', 'Record deleted!');
        }

        //for get request
        $rules = collect();

        $class_id = $request->query->get('class_id',0);
        $exam_id = $request->query->get('exam_id',0);

        if($class_id && $exam_id){
            $rules = ExamRule::where('class_id', $class_id)
                ->where('exam_id', $exam_id)
                ->with(['subject' => function($query){
                    $query->select('name','id');
                }])
                ->with(['grade' => function($query){
                    $query->select('name','id');
                }])
                ->with(['combineSubject' => function($query){
                    $query->select('name','id');
                }])
                ->get();
        }

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');
        $exams = Exam::where('class_id', $class_id)
            ->where('status', AppHelper::ACTIVE)
            ->pluck('name', 'id');

        

        return view('backend.exam.rule.list', compact('rules', 'classes', 'exams','class_id','exam_id'));
    }

    /**
     * rule create, read manage
     * @return \Illuminate\Http\Response
     */
    public function ruleCreate(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {
            $validateRules = [
                'class_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'exam_id' => 'required|integer',
                'grade_id' => 'required|integer',
                'combine_subject_id.*' => 'nullable|integer',
                'passing_rule' => 'required|integer',
                'total_exam_marks' => 'required|numeric',
                'over_all_pass' => 'required|numeric',
                'type' => 'required|array',
                'total_marks' => 'required|array',
                'pass_marks' => 'required|array',
            ];

            $this->validate($request, $validateRules);

            $inputs = $request->all();
        
            //validation check of existing rule
            $entryExists = ExamRule::where('subject_id', $inputs['subject_id'])
                ->where('exam_id', $inputs['exam_id'])->where('deleted_at', null)->count();

            // dd($entryExists);
            if($entryExists){
                return redirect()->route('exam.rule.create')->with('error', 'Rule already exists for this subject and exam!');
            }

            //validation end

            $marksDistribution = [];
            foreach ($inputs['type'] as $key => $value){
                $marksDistribution[] = [
                    'type' => $value,
                    'total_marks' => $inputs['total_marks'][$key],
                    'pass_marks' => $inputs['pass_marks'][$key],
                ];
            }

            $inputs['marks_distribution'] = json_encode($marksDistribution);
            if( array_key_exists('combine_subject_id', $inputs) ) {
                $inputs['combine_subject_id'] = implode(',', $inputs['combine_subject_id']);
            }
            ExamRule::create($inputs); 


            //now notify the admins about this record
            $msg = "Exam rule added by ".auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            $msg = "New exam rule added.";
            return redirect()->route('exam.rule.create')->with('success', $msg);
        }

        //for get request
        $rule = null;
        $combine_subject = null;
        $subject_id = null;
        $passing_rule = null;
        $exam_id = null;
        $grade_id = null;

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');
        $exams = [];//Exam::where('status', AppHelper::ACTIVE)->pluck('name', 'id');
        $grades = Grade::pluck('name', 'id');
        $subjects = [];
        // Not allow to select main subject as combine subject
        $combine_subject_not_allow = [];
        return view('backend.exam.rule.add', compact('rule',
            'combine_subject',
            'subject_id',
            'exam_id',
            'grade_id',
            'passing_rule',
            'classes',
            'exams',
            'grades',
            'subjects',
            'combine_subject_not_allow'
        ));
    }

    /**
     * rule update and edit manage
     * @return \Illuminate\Http\Response
     */
    public function ruleEdit(Request $request, $id=0){
        $rule = ExamRule::findOrFail($id);
        //for save on POST request
        if ($request->isMethod('post')) {
            $validateRules = [
                'exam_id' => 'required|integer',
                'grade_id' => 'required|integer',
                'combine_subject_id.*' => 'nullable|integer',
                'passing_rule' => 'required|integer',
                'total_exam_marks' => 'required|numeric',
                'over_all_pass' => 'required|numeric',
                'type' => 'required|array',
                'total_marks' => 'required|array',
                'pass_marks' => 'required|array',
            ];

            $this->validate($request, $validateRules);
            $combine_subject_id = [];
            if( !empty($request->combine_subject_id) ) {
                foreach ($request->combine_subject_id as $id) {
                    if( !empty($id) ) $combine_subject_id[] = $id;
                }
                $request['combine_subject_id'] = implode(',', $combine_subject_id);
            }
            $inputs = $request->all();
            unset($inputs['subject_id']);
            //validation end

            $marksDistribution = [];
            foreach ($inputs['type'] as $key => $value){
                $marksDistribution[] = [
                    'type' => $value,
                    'total_marks' => $inputs['total_marks'][$key],
                    'pass_marks' => $inputs['pass_marks'][$key],
                ];
            }
            $inputs['marks_distribution'] = json_encode($marksDistribution);

            $rule->fill($inputs);
            $rule->save();
            
            //now notify the admins about this record
            $msg = "Exam rule updated by ".auth()->user()->name;
            // $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end
            $msg = "Exam rule updated.";
            return redirect()->route('exam.rule.index')->with('success', $msg);
        }


        $combine_subject = $rule->combine_subject_id;
        $passing_rule = $rule->passing_rule;
        $subject_id = $rule->subject_id;
        $exam_id = $rule->exam_id;
        $grade_id = $rule->grade_id;

        $subjects = Subject::where('class_id', $rule->class_id)
            ->where('status', AppHelper::ACTIVE)
            ->pluck('name', 'id');

        $exams = Exam::where('class_id', $rule->class_id)
            ->where('status', AppHelper::ACTIVE)
            ->pluck('name', 'id');
        $grades = Grade::pluck('name', 'id');

        // Not allow to select main subject as combine subject
            $combine_subject_not_allow = Subject::where('class_id', $rule->class_id)
                ->where('status', AppHelper::ACTIVE)
                ->where('id', '<>', $subject_id)
                ->pluck('name', 'id');

        return view('backend.exam.rule.add', compact('rule',
            'combine_subject',
            'subject_id',
            'exam_id',
            'grade_id',
            'passing_rule',
            'subjects',
            'exams',
            'grades',
            'combine_subject_not_allow'
        ));

    }


    /*
     * Admit Card Index
     */
    public function admitCardIndex(Request $request) {
        $students = collect();

        $class_id = $request->query->get('class_id',0);
        $exam_id = $request->query->get('exam_id',0);

        if($class_id && $exam_id){
            $settings = AppHelper::getAppSettings();
            $acYear = $settings['academic_year'];
            $students = Registration::where('class_id', $class_id)
                ->where('academic_year_id', $acYear)
                ->with('student')
                ->orderBy('student_id','asc')
                ->get();
        }

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->pluck('name', 'id');
        $exams = Exam::where('class_id', $class_id)->where('status', AppHelper::ACTIVE)->pluck('name', 'id');

        return view('backend.exam.admitCard.list', compact('students', 'classes', 'exams','class_id','exam_id'));
    }

    /*
     * Admit Card
     */
    public function getAdmitCard(Request $request, $print=0) {
        $id = $request->get('examID', 0);
        $studentID = $request->get('studentID', 0);

        $students = array();
        if(empty($studentID) && !empty($print)) {
            // class students
            $examInfo = Exam::select('class_id')->where('id', $id)->first();
            $settings = AppHelper::getAppSettings();
            $acYear = $settings['academic_year'];
            $students = Registration::where('class_id', $examInfo->class_id)
                ->where('academic_year_id', $acYear)
                ->with('student')
                ->orderBy('student_id','asc')
                ->get()->toArray();
            // class students
        } else {
            $students = Registration::where('student_id', $studentID)
                ->with('student')
                ->get()->toArray();
        }

        $templateId = AppHelper::getAppSettings('admit_card_template');
        $templateBG = AppHelper::getAppSettings('admit_card_template_BG');
        $templateConfig = Template::where('id', $templateId)->where('type', 6)->first();

        if(!$templateConfig){
            return redirect()->route('administrator.template.admitCard.index')->with('error', 'Template not found!');
        }

        //get institute information
        $instituteInfo = AppHelper::getAppSettings('institute_settings');

        $templateConfig = json_decode($templateConfig->content);
        $templateConfig->logo = (!empty($templateConfig->logo))?'data:image/png;base64,'.$templateConfig->logo:(($print)?public_path('storage/logo/' . $instituteInfo['logo']):asset('storage/logo/' . $instituteInfo['logo']));

        $examTimeTable = ExamTimeTable::with('subject', 'exam')->where('exam_id', $id)->get();

        if($print) {
            // return view('backend.exam.admitCard.admitCard', compact('templateConfig', 'instituteInfo', 'templateBG', 'examTimeTable', 'students', 'print', 'id', 'studentID'));
            $pdf = PDF::loadView('backend.exam.admitCard.admitCard', compact('templateConfig', 'instituteInfo', 'templateBG', 'examTimeTable', 'students', 'print', 'id', 'studentID'))->setPaper('a4', 'portrait');
            return $pdf->stream('admit-card.pdf');
        } else {
            $view = view('backend.exam.admitCard.admitCard', compact('templateConfig', 'instituteInfo', 'templateBG', 'examTimeTable', 'students', 'print', 'id', 'studentID'))->render();
            return response()->json(['html' => $view]);
        }

    }

    public function getRulesTemplate($id) {
        $templates = ExamRulesTemplate::with(['templateclass' => function($query) use ($id){
            $query->where('class_id', $id);
        }])->whereNull('deleted_at')
        ->pluck('name', 'id');
        return response()->json($templates);
    }

}
