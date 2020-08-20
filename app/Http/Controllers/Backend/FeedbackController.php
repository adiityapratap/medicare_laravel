<?php

namespace App\Http\Controllers\Backend;

use App\IClass;
use App\Feedback;
use App\Student;
use App\Question;
use App\Subject;
use App\Registration;
use App\Employee;
use App\Section;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use app\Http\Helpers\AppHelper;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
 
        $feedback = Feedback::with(['registration','teacher' =>  function($query){
			$query->select('name','id')->distinct();
        }])->get();
        
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')
            ->pluck('name', 'id');
        
        $teacher = Employee::where('status', AppHelper::ACTIVE)->pluck('name','id')->unique();
        
        return view('backend.feedback.response.list',compact('feedback','classes','teacher'));
        
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
       
        $data = Question::get();
        
       
        $classId = Auth::user()->student->register->class_id;
        $subj = Subject::Where('class_id',$classId)->pluck('teacher_id');
        $teacher = Employee::whereIn('id',$subj)->pluck('name','id')->unique();
        
        
        return view('backend.feedback.response.create',compact('data','teacher'));

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

            $question = Input::get('question', []);
            $feedback = Input::get('feedback',[]);
            $teacher_id = Input::get('teacher_id');
            $response = Input::get('response');

            $student_id = Auth::user()->student->register->student_id;
            $classId = Auth::user()->student->register->class_id;
        // $this->validate($request,[
        //     'teacher_id' => 'required',
        //     'feedback' => 'required',
        //     'parent_response' => 'required|min:12|max:255'
        // ]);
            // $question = array_keys($question);
            
            

            $timeStampNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Kolkata'));

            $data = new Feedback;
            $data->student_id = $student_id;
            $data->teacher_id = $teacher_id;
            $data->class_id = $classId;
            $data->created_at = $timeStampNow;
            $data->updated_at = $timeStampNow;
            $data->feedback = json_encode($feedback);
            $data->parent_response = json_encode($response);

            $data->save();
            
        return redirect()->route('feedback.create')->with('success','Feedback Updated Successfully!');
        // Feedback::insert($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Feedback  $feedback
     * @return \Illuminate\Http\Response
     */
    public function show(Feedback $feedback)
    {
        //  
        $data = Feedback::findorFail($feedback->id);
        $feed =json_decode($data->feedback,true);
        $array = array_keys($feed);
        $question = Question::whereIn('id',$array)->pluck('question','id');
        
        return response()->json(['data'=>$data,'question'=>$question]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Feedback  $feedback
     * @return \Illuminate\Http\Response
     */
    public function edit(Feedback $feedback)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Feedback  $feedback
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Feedback $feedback)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Feedback  $feedback
     * @return \Illuminate\Http\Response
     */
    public function destroy(Feedback $feedback)
    {
        //
    }
}
