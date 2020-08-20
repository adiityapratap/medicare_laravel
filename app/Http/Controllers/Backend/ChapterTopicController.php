<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\ChapterTopic;
use App\Chapters;
use App\IClass;
use App\Subject;
use App\Http\Helpers\AppHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Traits\FileUploadTrait;
use Carbon\Carbon;
use \stdClass;

class ChapterTopicController extends Controller
{
    use FileUploadTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($chapter_id)
    {
        $query = DB::table('topics')->select('topics.*', 'chapters.title as chaptersName', 'i_classes.name as className', 'subjects.name as subjectName')
        ->leftJoin('chapters', 'topics.chapter_id', '=', 'chapters.id')
        ->leftJoin('subjects', 'chapters.subject_id', '=', 'subjects.id')
        ->leftJoin('i_classes', 'chapters.class_id', '=', 'i_classes.id')
        ->where('topics.chapter_id', $chapter_id)
        ->where('topics.deleted_at', NUll)
        ->orderBy('created_at', 'ASC');

        $chaptertopics = $query->get();
        $chapter = Chapters::where('id', $chapter_id)->first();

        return view('backend.chapterstopics.list', compact('chaptertopics', 'chapter', 'chapter_id'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($chapter_id)
    {   
        $files=[];
        $source = 'url';
        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            abort(404);
        }

        $chapter = Chapters::select('chapters.*', 'i_classes.name as className', 'subjects.name as subjectName')
        ->leftJoin('i_classes', 'chapters.class_id', '=', 'i_classes.id')
        ->leftJoin('subjects', 'chapters.subject_id', '=', 'subjects.id')
        ->where('chapters.id', $chapter_id)
        ->first();

        if(!$chapter) {
            abort(404);
        }
        $chaptertopic = null;
        return view('backend.chapterstopics.add', compact('chapter','chaptertopic', 'files', 'source'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules =  [
            'title' => 'required|min:5|max:255',
            'status' => 'required',
            'description' => 'required',
        ];
        $this->validate($request, $rules, [
            'title.required' => 'You need to provide a name for the topic!',
            'description.required' => 'Providing topic summary will make it easier for the students to understand!'
        ]);

        $data = $request->input();
        $chapter_id = (!empty($data['chapter_id'])) ? $data['chapter_id'] : '';
        DB::beginTransaction();
        try {
            $chaptertopic = ChapterTopic::create($data);
            if($request->input('fileurl') || $request->input('document')) {
                $this->processfiles($request, $chaptertopic, 'chapters');
            }
            DB::commit();

            return redirect()->route('topic.index',$chapter_id)->with('success', "Chapter Topic added!");
        } catch (\Exception $ex) {
            //Log::error($ex);
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return redirect()->route('topic.index',$chapter_id)->with('error', $message);
        }

    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $chaptertopic = ChapterTopic::where('id', $id)->first();
        $files = $this->retrieveFiles($chaptertopic, 'chapters');
        $source = $this->getSource($chaptertopic, 'chapters');
        $chapter = (!empty($chaptertopic->chapter)) ? $chaptertopic->chapter : '';
        if(!$chaptertopic || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))) {
            abort(404);
        }
        
        return view('backend.chapterstopics.add', compact('chaptertopic','chapter', 'files', 'source'));
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
        $chaptertopic = ChapterTopic::where('id', $id)->first();
        $chapter_id = (!empty($chaptertopic->chapter_id)) ? $chaptertopic->chapter_id : "";

        if(!$chaptertopic || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))){
            abort(404);
        }

        $rules =  [
            'title' => 'required|min:5|max:255',
            'status' => 'required',
            'description' => 'required',
        ];
        $this->validate($request, $rules);
        
        $data = $request->input();

        if($request->input('fileurl') || $request->input('document')) {
            $this->processfiles($request, $chaptertopic, 'chapters');
        }
        $chaptertopic->fill($data);
        $chaptertopic->save();

        return redirect()->route('topic.index',$chapter_id)->with('success', 'Chapter Topic updated!');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $chaptertopic = ChapterTopic::where('id', $id)->first();
        $chaptertopic->files = $this->retrieveFiles($chaptertopic, 'chapters');
        
        $chapter = (!empty($chaptertopic->chapter)) ? $chaptertopic->chapter : '';
        if(!$chapter) {
            abort(404);
        }
        $topiclist = ChapterTopic::where('chapter_id', $chapter->id)->pluck('title', 'id');
        
        $studentView = false;
        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            $studentView = true;
        }

        return view('backend.chapterstopics.view', compact('chapter', 'chaptertopic', 'topiclist', 'studentView'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $chaptertopic = ChapterTopic::where('id', $id)->first();
        $chapter_id = (!empty($chaptertopic->chapter_id)) ? $chaptertopic->chapter_id : "";

        if(!$chaptertopic || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))) {
            abort(404);
        }
        try {
            $chaptertopic->delete();
            ChapterTopic::destroy($id);
            return redirect()->route('topic.index',$chapter_id)->with('success', 'Chapter Topic deleted!');
        } catch (\Exception $ex) {
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return redirect()->route('topic.index',$chapter_id)->with('error', $message);
        }
    }
   
}
