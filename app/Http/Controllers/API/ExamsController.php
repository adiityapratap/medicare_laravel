<?php

namespace App\Http\Controllers\API;

use App\Exam;
use App\ExamRule;
use App\Http\Controllers\Controller;
use App\Http\Helpers\AppHelper;
use App\Http\Resources\Exam\ExamListCollection;
use App\Http\Resources\Exam\MarksCollection;
use App\Http\Resources\Exam\RuleCollection;
use App\IClass;
use App\Mark;
use App\Subject;
use Illuminate\Http\Request;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class ExamsController extends Controller
{

    public function getExamList(Request $request)
    {
    	$ids = IClass::where('status', '1')->pluck('id')->toArray();
    	$user = JWTAuth::parseToken()->authenticate();
    	if (!$user) {
    	    return response()->json(['success' => false, 'message' => 'User not found'], 404);
    	}

    	if( empty($request->class_id) || !in_array($request->class_id, $ids) ) {
    		return response()->json(['success' => false, 'message' => 'Invalid class ID'], 412);
    	}

    	$exams = Exam::where('status', AppHelper::ACTIVE)
    				->where('class_id', $request->class_id)->get();
    	
        return new ExamListCollection($exams);
    }

    public function getmarks(Request $request)
    {
    	$marks = Mark::with(['student' => function($query){
    	    $query->with(['info' => function($query){
    	        $query->select('name','id');
    	    }])->select('regi_no','student_id','roll_no','id');
    	    }])
    	    ->where('class_id', $request->class_id)
    	    ->where('section_id', $request->section_id)
    	    ->where('exam_id', $request->exam_id)
    	    ->get();

        return new MarksCollection($marks);
    }
    public function getExamRules(Request $request, $id)
    {
        $rules = ExamRule::where('exam_id', $id)->get();
        return new RuleCollection($rules);
    }
}
