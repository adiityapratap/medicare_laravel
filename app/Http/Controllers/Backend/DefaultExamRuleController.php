<?php

namespace App\Http\Controllers\Backend;


use DB;
use Log;
use App\Grade;
use App\Subject;
use App\IClass;
use App\ExamRule;
use App\Http\Helpers\AppHelper;
use App\DefaultExamRule;
use App\ExamRuleTempClass;
use Carbon\Carbon;
use Illuminate\Support\Facades\Input;
use App\ExamRulesTemplate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
class DefaultExamRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        
       
        $examtemplate =  ExamRulesTemplate::with(['templateclass' =>  function($query){

			$query->select('*')->with(['class' => function ($query) {
				$query->select('id','name');
			    }]);
            }])->paginate(25);
                
                // dd($examtemplate);
               
                // return $examtemplate;
            return view("backend.exam.rule.template.list",compact('examtemplate'));
    
    }
    public function subjectindex(Request $request){
        
        $class_id = $request->query->get('class_id');
        // return $class_id;
        // $class_id = explode(",",$class_id);
        $subjects = Subject::whereIn('class_id',$class_id)
            ->with("class")
            ->where('status', AppHelper::ACTIVE)
            ->get();
        return response()->json($subjects);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        //
        $rule = null;
        $combine_subject = [];
        $subject_id = null;
        $passing_rule = null;
        // $exam_id = null;
        $grade_id = null;
        $class_id = null;
       
        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');
        
        $exams = [];//Exam::where('status', AppHelper::ACTIVE)->pluck('name', 'id');
        $grades = Grade::pluck('name', 'id');
        $subjects = [];

        // return response()->json(['subject'=>$subjects]);
        // $rule =ExamRule::get('marks_distribution');
        $marksDistributionTypes=null;
        // Not allow to select main subject as combine subject
        $combine_subject_not_allow = [];
        return view('backend.exam.rule.template.create', compact('rule',
            'combine_subject',
            'subject_id',
            'class_id',
            'grade_id',
            'passing_rule',
            'classes',
            'exams',
            'grades',
            'subjects',
            'combine_subject_not_allow',
            'marksDistributionTypes'
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
        
        $class_id = Input::get('class_id',[]);
        $grade_id = Input::get('grade_id');
        $passing_rule =Input::get('passing_rule');
        $exam_name = Input::get('examname');
        $subject_id = Input::get('subject_id',[]);
        $combine_subject =Input::get('combine_subject_id',[]);
        $total_exam_marks = Input::get('total_exam_marks',[]);
        $over_all_pass = Input::get('over_all_pass',[]);
        $markdistrribution_type = Input::get('marks_distribution_types',[]);
        $total_marks = Input::get('total_marks',[]);
        $pass_marks = Input::get('pass_marks',[]);

        // return $request->all();
        
        if($request->isMethod('post')){
			$rules=[
                'class_id' => 'required',
                'grade_id' => 'required',
                'passing_rule' => 'required',
				'examname' => 'required',
				'grade_id' => 'required|numeric',
				'subject_id' => 'required',
                'type' => 'required',
                'total_exam_marks' =>'required',
                'over_all_pass' => 'required'
			];
            
			// $this->validate($request, $rules);
			
			// defaultexamtable
			$defaultExamTable=[];
			DB::beginTransaction();
			try {		
                // dd($request->all());
                
				$fid = ExamRulesTemplate::insertGetId([
                    'name' => $exam_name,
                    'grade_id' => $grade_id,
                    'passing_rule' => $passing_rule,
                    "created_at" =>  Carbon::now(), # new \Datetime()
                    "updated_at" =>  Carbon::now(),  # new \Datetime()
                    'updated_by' => auth()->user()->id,
                    'created_by' => auth()->user()->id,
                ]);
                
                foreach($class_id as $c){
                    $examruletemp_class[] = array(
						'class_id' => $c,
						'template' => $fid,
                        "created_at" =>  Carbon::now(), # new \Datetime()
                        "updated_at" =>  Carbon::now(),  # new \Datetime()
                        'updated_by' => auth()->user()->id,
                        'created_by' => auth()->user()->id,
					);
                }
                ExamRuleTempClass::insert($examruletemp_class);

                for($i=0;$i<count($subject_id);$i++) {
                    for($j=0;$j<count($markdistrribution_type[$i]);$j++){
                        $default_temp[] = array(
                            'template' => $fid,
                            'subject_id' => $subject_id[$i],
                            'combine_subject' => isset($combine_subject[$i]) ? json_encode($combine_subject[$i]) : '',
                            'marks_distribution' => $markdistrribution_type[$i][$j],
                            'total_exam_marks' => $total_marks[$i][$j],
                            'over_all_pass' => $pass_marks[$i][$j],
                            "created_at" =>  Carbon::now(), # new \Datetime()
                            "updated_at" =>  Carbon::now(),  # new \Datetime()
                            'updated_by' => auth()->user()->id,
                            'created_by' => auth()->user()->id,
                        );
                    }
				}
               DefaultExamRule::insert($default_temp);
                
				DB::commit();
			}
			catch(\Exception $e){
				DB::rollback();
				throw new \Exception($e);
			}
			return redirect()->route('template.index')->with('success', 'Default Exam Rule Added Succesfully!');
		}

		
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\DefaultExamRule  $defaultExamRule
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $data = ExamRulesTemplate::with(['template'=> function($query){
            $query->select("*")->with(['subject'=> function($query) {
                $query->select('id', 'name');
            }]);
        },'grade'=>function($query){
            $query->select('id','name');
        },'templateclass'])->where('id',$id)->get()->first();

        // foreach($data as $key=>$node ){
        //     $kc = $node->template;
        // }
        
        // $rule = ExamRulesTemplate::with(['template','templateclass'])->findOrFail($id);
        $class_id =[];
        foreach($data->templateclass as $class){
            array_push($class_id, $class->class_id);
        }
    
        $combine_subject = Subject::whereIn('class_id',$class_id)
            ->where('status', AppHelper::ACTIVE)
            ->pluck('name','id');

            // return $combine_subject;
        //  view('backend.exam.rule.defaultlist')->with('dat',json_encode($kc))->render();
        return response()->json(['data'=>$data ,'combine_subj'=>$combine_subject]);
    }
       
    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\DefaultExamRule  $defaultExamRule
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $rule = ExamRulesTemplate::with(['template','templateclass', 'grade'])->get()->first();
        
        //populate old value of class name as class_id
        $class_id =[];
        foreach($rule->templateclass as $class){
            array_push($class_id,$class->class_id);
        }
       
        $subject_id = [];
        $combine_subject = [];
        foreach($rule->template as $subj){
            array_push ($subject_id, $subj->subject_id);
            $combine_subject[$subj->subject_id] = json_decode($subj->combine_subject);
        }
        $subject_id = array_unique($subject_id);
        
        //get ll the value of marks
        $marksDistributionTypes = [];
        $total_exam_marks = [];
        $over_all_pass = [];

        foreach($rule->template as $temp){
            array_push($marksDistributionTypes,$temp->marks_distribution);
            array_push($total_exam_marks,$temp->total_exam_marks);
            array_push($over_all_pass,$temp->over_all_pass);
        }

        $passing_rule = $rule->passing_rule;
        $grade_id = $rule->grade_id;
        $marksDistributionTypes = $rule->marks_distribution;
    
        // return $rule;
        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
                ->pluck('name', 'id');

        $subjects = Subject::whereIn('class_id',$class_id)
        ->select('id', 'name', 'class_id')
        ->with(['class' => function($query){
            $query->select('id', 'name');
        }])
        ->where('status', AppHelper::ACTIVE)
        ->get();
        
        $grades = Grade::pluck('name', 'id');

        $totalMarks = 0;
        $passingMarks = 0;
        if($rule->grade){
            $marks = [];
            foreach (json_decode($rule->grade->rules) as $g){
                $marks[] = $g->marks_from;
                $marks[] = $g->marks_upto;
            }

            sort($marks);
            //passing marks will be the last position in the array
            $totalMarks = $marks[count($marks) - 1];
            //passing marks will be the 3rd position in the array
            $passingMarks = $marks[2];
        }
        // Not allow to select main subject as combine subject
        $combine_subject_not_allow = Subject::where('class_id', $rule->class_id)
            ->where('status', AppHelper::ACTIVE)
            ->where('id', '<>', $subject_id)
            ->pluck('name', 'id');

            // return $rule;
        return view('backend.exam.rule.template.create', compact(
            'rule',
            'totalMarks',
            'passingMarks',
            'combine_subject',
            'subject_id',
            'grade_id',
            'class_id',
            'passing_rule',
            'subjects',
            'grades',
            'classes',
            'marksDistributionTypes',
            'total_exam_marks',
            'combine_subject_not_allow'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\DefaultExamRule  $defaultExamRule
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $class_id = Input::get('class_id',[]);
        $previous_class = Input::get('previous_class', '');
        $previous_class = explode(",", $previous_class);
        $deleted = array_diff($previous_class, $class_id);
        $added = array_diff($class_id, $previous_class);

        // return $request->all();
        DB::beginTransaction();
        try {
            foreach ($deleted as $key => $tc) {
                ExamRuleTempClass::where('id', $tc)->delete();
            }
            foreach ($added as $key => $tc) {
                ExamRuleTempClass::create([
                    'class_id' => $tc,
                    'template' => $id
                ]);
            }

            $name = Input::get('examname');
            $grade_id = Input::get('grade_id');
            $passing_rule =Input::get('passing_rule');

            $template = ExamRulesTemplate::with(['template','templateclass'])->find($id);
            $template->update([
                'name' => $name,
                'grade_id' => $grade_id, 
                'passing_rule' => $passing_rule
            ]);

            $mdtid = Input::get('mdtid',[]);
            $subject_id = Input::get('existing_subject_id',[]);
            $combine_subject =Input::get('existing_combine_subject_id',[]);
            $markdistrribution_type = Input::get('existing_marks_distribution_types',[]);
            $total_marks = Input::get('existing_total_marks',[]);
            $pass_marks = Input::get('existing_pass_marks',[]);
            
            for($i=0; $i<count($subject_id); $i++) {
                for($j=0; $j<count($markdistrribution_type[$i]); $j++){
                    DefaultExamRule::where('id', $mdtid[$i][$j])->update(
                        array(
                            'template' => $fid,
                            'subject_id' => $subject_id[$i],
                            'combine_subject' => isset($combine_subject[$i]) ? json_encode($combine_subject[$i]) : '',
                            'marks_distribution' => $markdistrribution_type[$i][$j],
                            'total_exam_marks' => $total_marks[$i][$j],
                            'over_all_pass' => $pass_marks[$i][$j],
                            "updated_at" =>  Carbon::now(),  # new \Datetime()
                            'updated_by' => auth()->user()->id,
                        )
                    );
                }
            }
           
            $subject_id = Input::get('subject_id',[]);
            $combine_subject =Input::get('combine_subject_id',[]);
            $markdistrribution_type = Input::get('marks_distribution_types',[]);
            $total_marks = Input::get('total_marks',[]);
            $pass_marks = Input::get('pass_marks',[]);

            for($i=0;$i<count($subject_id);$i++) {
                for($j=0;$j<count($markdistrribution_type[$i]);$j++){
                    $default_temp[] = array(
                        'template' => $fid,
                        'subject_id' => $subject_id[$i],
                        'combine_subject' => isset($combine_subject[$i]) ? json_encode($combine_subject[$i]) : '',
                        'marks_distribution' => $markdistrribution_type[$i][$j],
                        'total_exam_marks' => $total_marks[$i][$j],
                        'over_all_pass' => $pass_marks[$i][$j],
                        "created_at" =>  Carbon::now(), # new \Datetime()
                        "updated_at" =>  Carbon::now(),  # new \Datetime()
                        'updated_by' => auth()->user()->id,
                        'created_by' => auth()->user()->id,
                    );
                }
            }
            DefaultExamRule::insert($default_temp);

            DB::commit();
            return redirect()->route('template.index')->with('success','Exam Rule Template Updated Successfully!');
        }
        catch(\Exception $e){
            Log::error($e);
            DB::rollback();
            throw new \Exception($e);
            $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
            return redirect()->route('template.edit', ['id' => $id])->with('error', $message);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\DefaultExamRule  $defaultExamRule
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        $examtemplate = ExamRulesTemplate::findOrFail($id);
        $examtemplate->delete();

        return redirect()->route('examtemp.index')->with('success', 'Exam Rule Template  Deleted!');
    }

    public function deleteMdt($id) {
        if($id) {
			DB::beginTransaction();
            try {
                $default_rule = DefaultExamRule::findOrFail($id);
                $default_rule->delete();

				DB::commit();
                return response()->json(['success' => TRUE, 'data' => $default_rule->id, 'message' => 'Destribution type deleted.']);
            }
			catch(\Exception $e){
				Log::error($e);
                DB::rollback();
                return response()->json(['success' => FALSE, 'data' => $default_rule->id, 'message' => 'Failed to delete the destribution type.']);
			}
        }
    }
}
