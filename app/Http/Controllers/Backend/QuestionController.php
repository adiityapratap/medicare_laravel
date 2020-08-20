<?php

namespace App\Http\Controllers\Backend;

use App\Question;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Carbon\Carbon;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $question = Question::get();
        
        return view('backend.feedback.list',compact('question'));
        
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $question = null;
        return view('backend.feedback.create',compact('question'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $timeStampNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Kolkata'));
        $question = Input::get('question',[]);
        foreach($question as $q){
            $question_insert[] = array(
                'question' => $q,
                'created_at' => $timeStampNow
            );
        }
        
         Question::insert($question_insert);

        return redirect()->route('question.index')->with('success', 'Question added Successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //editing for a perticular question
        $question = Question::find($id);
        return view('backend.feedback.create',compact('question'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        $this->validate($request,[
            'question'=>'required'
        ]);
        $question = Input::get('question',[]);
        
        Question::whereId($id)->update(['question' => reset($question)]);

        return redirect()->route('question.index')->with('success', 'Question updated Successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        $delete = Question::findOrfail($id);
        $delete->delete();
        return redirect()->route('question.index')->with('success','Question deleted Successfully!');
    }
}
