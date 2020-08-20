<?php

namespace App\Http\Controllers\Backend;

use Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Chapters;
use App\IClass;
use App\Subject;
use App\Http\Helpers\AppHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use \stdClass;

class ChapterController extends Controller
{

    /*
     * Homework Summary
     */
    public function subjectSummary() 
    {
        $studentView = FALSE;
        if(!auth()->user()) {
            abort(404);
        }
        if(auth()->user()->role == 'Student' || (isset(auth()->user()->role['role_id']) && !empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            $studentView = true;
            $chapters = 0;
            // $studentID = DB::table('students')->select('id')->where('user_id', auth()->user()->id)->first();
            // $studentData = DB::table('registrations')->select('class_id')->where('student_id', '=', $studentID->id)->first();
            // if(!empty($studentData)) {
            //     $query->where('chapters.class_id', '=', $studentData->class_id);
            // }
            $class_id = auth()->user()->student->register->class_id;
            $subjects = Subject::withCount('chapters')->where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->get();
            // return $subjects;
            return view('backend.chapters.students.summary', compact('subjects'));
        }
        $teacherSubjects = '';
        $submission_date = date('d/m/Y');
        if(isset(auth()->user()->role['role_id']) && !empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
            $teacherID = DB::table('employees')->select('id')->where('user_id', auth()->user()->id)->first();
            $teacherClasses = AppHelper::getTeacherClasses($teacherID->id);
            $teacherSubjects = AppHelper::getTeacherSubjects($teacherID->id);
            $classes = IClass::with(['subject' => function($query) use ($teacherID){
                $query->where('teacher_id', $teacherID->id);
                $query->withCount('chapters');
            }])->where('status', AppHelper::ACTIVE)->whereIn('id', $teacherClasses)->orderBy('order','asc')->get();
        } else {
            $classes = IClass::with(['subject' => function($query){
                $query->withCount('chapters');
            }])->where('status', AppHelper::ACTIVE)->orderBy('order','asc')->get();
        }
        // return $classes;
        return view('backend.chapters.summary', compact('classes'));
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $classes = ''; $studentView = false;
        $studentView = FALSE;
        $subjects = [];
        $class_id = $request->query->get('class', 0);
        $subject_id = $request->query->get('subject', 0);
        $query = DB::table('chapters')->select('chapters.*', 'i_classes.name as className', 'subjects.name as subjectName')
        ->leftJoin('i_classes', 'chapters.class_id', '=', 'i_classes.id')
        ->leftJoin('subjects', 'chapters.subject_id', '=', 'subjects.id')
        ->whereNull('chapters.deleted_at')
        ->orderBy('created_at', 'ASC');

        if($subject_id) {
            $query->where('chapters.subject_id', $subject_id);
        }

        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            $studentView = true;
            $studentID = DB::table('students')->select('id')->where('user_id', auth()->user()->id)->first();
            $studentData = DB::table('registrations')->select('class_id')->where('student_id', '=', $studentID->id)->first();
            if(!empty($studentData)) {
                $query->where('chapters.class_id', '=', $studentData->class_id);
            }
            $class_id = auth()->user()->student->register->class_id;
            $subjects = Subject::where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->pluck('name', 'id');
        } else {
            if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
                $teacherID = DB::table('employees')->select('id')->where('user_id', auth()->user()->id)->first();
                $teacherClasses = AppHelper::getTeacherClasses($teacherID->id);
                $teacherSubjects = AppHelper::getTeacherSubjects($teacherID->id);
                $classes = IClass::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherClasses)->orderBy('order', 'asc')->pluck('name', 'id');
                $query->whereIn('chapters.class_id', $teacherClasses);
                $query->whereIn('chapters.subject_id', $teacherSubjects);
            } else {
                $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->pluck('name', 'id');
            }
            if($class_id) {
                $subjects = Subject::where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->pluck('name', 'id');
            }
        }

        if((auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) && empty($studentData)) {
            $chapters = array();
        } else {
            $chapters = $query->get();
        }
        return view('backend.chapters.list', compact('chapters', 'classes', 'class_id', 'subject_id', 'studentView', 'subjects'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            abort(404);
        }
        $chapter = null;
        $teacherSubjects = '';
        if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
            $teacherID = DB::table('employees')->select('id')->where('user_id', auth()->user()->id)->first();
            $teacherClasses = AppHelper::getTeacherClasses($teacherID->id);
            $teacherSubjects = AppHelper::getTeacherSubjects($teacherID->id);
            $classes = IClass::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherClasses)->orderBy('order','asc')->pluck('name', 'id');
        } else {
            $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order','asc')->pluck('name', 'id');
        }
        $class_id = $request->query->get('class', 0);
        $subject = $request->query->get('subject', 0);
        $subjects = [];
        if($class_id) {
            if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
                $subjects = Subject::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherSubjects)->where('class_id', $class_id)->pluck('name', 'id');
            } else {
                $subjects = Subject::where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->pluck('name', 'id');
            }
        }
        return view('backend.chapters.add', compact('chapter', 'classes', 'subjects', 'subject', 'class_id'));
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
            'class_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'description' => 'required',
        ];
        $this->validate($request, $rules, [
            'title.required' => 'You need to provide a name for the chapter!',
            'description.required' => 'Providing chapter summary will make it easier for the students to understand!'
        ]);

        $data = $request->input();
        $data['user_id'] = auth()->user()->id;

        DB::beginTransaction();
        try {
            $chapter = Chapters::create($data);
            DB::commit();

            return redirect()->route('chapter.index')->with('success', "Chapter added!");
        } catch (\Exception $ex) {
            Log::error($ex);
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return redirect()->route('chapter.index')->with('error', $message);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $chapter = Chapters::select('chapters.*', 'i_classes.name as className', 'subjects.name as subjectName')
        ->leftJoin('i_classes', 'chapters.class_id', '=', 'i_classes.id')
        ->leftJoin('subjects', 'chapters.subject_id', '=', 'subjects.id')
        ->where('chapters.id', $id)
        ->first();

        if(!$chapter) {
            abort(404);
        }

        $studentView = false;
        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            $studentView = true;
        }

        return view('backend.chapters.view', compact('chapter', 'studentView'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $chapter = Chapters::where('id', $id)->first();

        if(!$chapter || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))) {
            abort(404);
        }
        $teacherSubjects = '';
        $class_id = 0;
        if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
            $teacherID = DB::table('employees')->select('id')->where('user_id', auth()->user()->id)->first();
            $teacherClasses = AppHelper::getTeacherClasses($teacherID->id);
            $teacherSubjects = AppHelper::getTeacherSubjects($teacherID->id);
            $classes = IClass::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherClasses)->orderBy('order','asc')->pluck('name', 'id');
            $subjects = Subject::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherSubjects)->where('class_id', $chapter->class_id)->pluck('name', 'id');
        } else {
            $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order','asc')->pluck('name', 'id');
            $subjects = Subject::where('status', AppHelper::ACTIVE)->where('class_id', $chapter->class_id)->pluck('name', 'id');
        }
        return view('backend.chapters.add', compact('chapter', 'classes', 'subjects', 'class_id'));
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
        $chapter = Chapters::where('id', $id)->first();

        if(!$chapter || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))){
            abort(404);
        }

        $rules =  [
            'title' => 'required|min:5|max:255',
            'status' => 'required',
            'class_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'description' => 'required',
        ];
        $this->validate($request, $rules);
        
        $data = $request->input();

        $chapter->fill($data);
        $chapter->save();

        return redirect()->route('chapter.index')->with('success', 'Chapter updated!');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $chapter = Chapters::where('id', $id)->first();
        if(!$chapter || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))) {
            abort(404);
        }
        try {
            $chapter->delete();
            Chapters::destroy($id);
            return redirect()->route('chapter.index')->with('success', 'Chapter deleted!');
        } catch (\Exception $ex) {
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return redirect()->route('chapter.index')->with('error', $message);
        }
    }

}
