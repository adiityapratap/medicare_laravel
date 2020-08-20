<?php

namespace App\Http\Controllers\API;

use App\IClass;
use App\Feedback;
use App\Student;
use App\Question;
use App\Subject;
use App\Registration;
use App\Employee;
use App\Section;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use app\Http\Helpers\AppHelper;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Auth;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class FeedbackController extends Controller
{
    public $status = 200;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $data = Question::select('id','question')->get();
        
        if(count($data)<=0){

            return response()->json(['success' => false, 'message' => 'No Feedback Question Available','data' => $data],$this->status);
            die;

        }

        return response()->json(['success' => true, 'message' => 'Feedback Question list ','data' => $data],$this->status);
        die;
        
    }

    /**
     * Show the form for creating a new resource.
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function listTeacher(Request $request)
    {
        //
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
    
        $classId = Auth::user()->student->register->class_id;
        $subj = Subject::Where('class_id',$classId)->pluck('teacher_id');
        $teacher = Employee::whereIn('id',$subj)->distinct()->select('id','name')->get();

       if(count($teacher)<=0){
        return response()->json(['success' => false, 'message' => 'No Feedback Available','data' => $teacher],$this->status);
        die;
        }

       return response()->json(['success' => true , 'message' => 'Teacher which got feedback', 'data' => $teacher],$this->status);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function submitfeedback(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $feedback = Input::get('feedback',[]);
        $teacher_id = Input::get('teacher_id');
        $response = Input::get('response');
        $student_id = Auth::user()->student->register->student_id;
        $classId = Auth::user()->student->register->class_id;

        $data = new Feedback;
        $data->student_id = $student_id;
        $data->teacher_id = $teacher_id;
        $data->class_id = $classId;
        $data->feedback = json_encode($feedback);
        $data->parent_response = json_encode($response);
        $data->save();

        if($data->save()){
            return response()->json(['success' => true, 'message' => 'Feedback Store Sucessfully','data'=>$data],$this->status);
            die;
        }else{
            return response()->json(['success' => false, 'message' => 'Feedback Insertion Error !','data'=>$data],$this->status);
            die;
        }
       
    }
}
