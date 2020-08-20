<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Homework;
use App\HomeworkSubmission;
use App\User;
use App\IClass;
use App\Section;
use App\Subject;
use App\Http\Helpers\AppHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use \stdClass;
use App\Jobs\HomeworkCreated;


class HomeworkController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // echo '<pre>';print_r(auth()->user()->role['role_id']);
        $classes = '';$sections = []; $studentView = false;
        $class_id = $request->query->get('class', 0);
        $section_id = $request->query->get('section', 0);
        $submission_date = $request->query->get('submission_date', date('d/m/Y'));
        $query = DB::table('homeworks')->select('homeworks.*', 'i_classes.name as className', 'sections.name as sectionName', 'subjects.name as subjectName')
        ->leftJoin('i_classes', 'homeworks.class_id', '=', 'i_classes.id')
        ->leftJoin('sections', 'homeworks.section_id', '=', 'sections.id')
        ->leftJoin('subjects', 'homeworks.subject_id', '=', 'subjects.id')
        ->orderBy('submission_date', 'ASC');

        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            $studentView = true;
            $studentID = DB::table('students')->select('id')->where('user_id', auth()->user()->id)->first();
            $studentData = DB::table('registrations')->select('class_id', 'section_id')->where('student_id', '=', $studentID->id)->first();
            if(!empty($studentData)) {
                $query->where('homeworks.class_id', '=', $studentData->class_id);
                $query->where('homeworks.section_id', '=', $studentData->section_id);
            }
        } else {
            if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
                $teacherID = DB::table('employees')->select('id')->where('user_id', auth()->user()->id)->first();
                $teacherClasses = AppHelper::getTeacherClasses($teacherID->id);
                $teacherSubjects = AppHelper::getTeacherSubjects($teacherID->id);
                $classes = IClass::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherClasses)->orderBy('order', 'asc')->pluck('name', 'id');
                $query->whereIn('homeworks.class_id', $teacherClasses);
                $query->whereIn('homeworks.subject_id', $teacherSubjects);
            } else {
                $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->pluck('name', 'id');
            }
            $subm_date = Carbon::createFromFormat('d/m/Y', $submission_date)->toDateString();
            if($class_id && $section_id) {
                $sections = Section::where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->pluck('name', 'id');
                $query->where('homeworks.class_id', '=', $class_id);
                $query->where('homeworks.section_id', '=', $section_id);
            }
            $query->whereDate('homeworks.submission_date', $subm_date);
        }

        if((auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) && empty($studentData)) {
            $homeworks = array();
        } else {
            $homeworks = $query->get();
        }
        return view('backend.homeworks.list', compact('homeworks', 'classes', 'sections', 'class_id', 'section_id', 'submission_date', 'studentView'));
    }

    /*
     * Homework Summary
     */
    public function homeworkSummary() 
    {
        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            abort(404);
        }
        $teacherSubjects = '';
        $submission_date = date('d/m/Y');
        $classlist = [];
        if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
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

                //Now get the homeworks and submissions count
                if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
                    $classlist[$class_id]->{$section_id}->homeworks = Homework::where('class_id', $class_id)->where('section_id', $section_id)->whereIn('subject_id', $teacherSubjects)->count();
                    $homeworkIDs = Homework::selectRaw('GROUP_CONCAT(id) as homeworkIDs')->where('class_id', $class_id)->where('section_id', $section_id)->whereIn('subject_id', $teacherSubjects)->first();
                } else {
                    $classlist[$class_id]->{$section_id}->homeworks = Homework::where('class_id', $class_id)->where('section_id', $section_id)->count();
                    $homeworkIDs = Homework::selectRaw('GROUP_CONCAT(id) as homeworkIDs')->where('class_id', $class_id)->where('section_id', $section_id)->first();
                }
                $classlist[$class_id]->{$section_id}->submissions = DB::table('homework_submissions')->whereIn('homework_id', explode(',', $homeworkIDs->homeworkIDs))->count();
            }
        }
        
        return view('backend.homeworks.summary', compact('classlist', 'submission_date'));
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
        $homework = null;
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
        $section_id = $request->query->get('section', 0);
        $submission_date = $request->query->get('submission_date', date('d/m/Y'));
        $sections = [];
        $subjects = [];
        if($class_id && $section_id) {
            $sections = Section::where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->pluck('name', 'id');
            if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
                $subjects = Subject::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherSubjects)->where('class_id', $class_id)->pluck('name', 'id');
            } else {
                $subjects = Subject::where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->pluck('name', 'id');
            }
        }
        return view('backend.homeworks.add', compact('homework', 'classes', 'sections', 'subjects', 'class_id', 'section_id', 'submission_date'));
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
            'section_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'description' => 'required',
            'submission_date' => 'required',
            'attachment' => 'mimes:jpeg,jpg,png,ppt,pdf|max:3072',
        ];
        $this->validate($request, $rules);

        $data = $request->input();
        /*if($request->hasFile('attachment')) {
            $storagepath = $request->file('attachment')->store('public/homeworks');
            $fileName = basename($storagepath);
            $data['attachment'] = $fileName;
        } else {
            $data['attachment'] = $request->get('oldAttachment','');
        }*/
        $data['submission_date'] = Carbon::createFromFormat('d/m/Y', $data['submission_date'])->toDateString();
        unset($data['oldAttachment']);
        $data['user_id'] = auth()->user()->id;

        DB::beginTransaction();
        try {
            $homework = Homework::create($data);

            if($request->hasFile('attachment')) {
                $homework->addMedia($request->file('attachment'))->toMediaCollection(config('app.name').'/homeworks/','s3');
            }

            DB::commit();

            // Notifications
            $classStudents = DB::table('registrations')->selectRaw('GROUP_CONCAT(students.user_id) as userIDs')->leftJoin('students', 'students.id', '=', 'registrations.student_id')->where('registrations.class_id', $data['class_id'])->where('registrations.section_id', $data['section_id'])->whereNotNull('students.user_id')->whereNull('registrations.deleted_at')->where('registrations.status', AppHelper::ACTIVE)->whereNull('students.deleted_at')->where('students.status', AppHelper::ACTIVE)->first();
            $notiMessage = "Homework added by ".auth()->user()->name;
            AppHelper::notifyUsers($classStudents->userIDs, $notiMessage);
            HomeworkCreated::dispatch($homework->id, explode(',', $classStudents->userIDs));
            // Notifications

            return redirect()->route('homework.index')->with('success', "Homework added!");
        } catch (\Exception $ex) {
            Log::error($ex);
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return redirect()->route('homework.index')->with('error', $message);
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
        $homework = Homework::select('homeworks.*', 'i_classes.name as className', 'sections.name as sectionName', 'subjects.name as subjectName')
        ->leftJoin('i_classes', 'homeworks.class_id', '=', 'i_classes.id')
        ->leftJoin('sections', 'homeworks.section_id', '=', 'sections.id')
        ->leftJoin('subjects', 'homeworks.subject_id', '=', 'subjects.id')
        ->where('homeworks.id', $id)
        ->first();

        if(!$homework) {
            abort(404);
        }

        $homework->attachment = $homework->getFirstMediaUrl(config('app.name').'/homeworks/');

        $studentView = false;
        if(auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT)) {
            $studentView = true;
        }

        return view('backend.homeworks.view', compact('homework', 'studentView'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $homework = Homework::where('id', $id)->first();

        if(!$homework || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))) {
            abort(404);
        }
        $teacherSubjects = '';
        $class_id = $section_id = 0;
        if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
            $teacherID = DB::table('employees')->select('id')->where('user_id', auth()->user()->id)->first();
            $teacherClasses = AppHelper::getTeacherClasses($teacherID->id);
            $teacherSubjects = AppHelper::getTeacherSubjects($teacherID->id);
            $classes = IClass::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherClasses)->orderBy('order','asc')->pluck('name', 'id');
            $subjects = Subject::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherSubjects)->where('class_id', $homework->class_id)->pluck('name', 'id');
        } else {
            $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order','asc')->pluck('name', 'id');
            $subjects = Subject::where('status', AppHelper::ACTIVE)->where('class_id', $homework->class_id)->pluck('name', 'id');
        }
        $sections = Section::where('status', AppHelper::ACTIVE)->where('class_id', $homework->class_id)->pluck('name', 'id');

        return view('backend.homeworks.add', compact('homework', 'classes', 'sections', 'subjects', 'class_id', 'section_id'));
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
        $homework = Homework::where('id', $id)->first();

        if(!$homework || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))){
            abort(404);
        }

        $rules =  [
            'title' => 'required|min:5|max:255',
            'status' => 'required',
            'class_id' => 'required|integer',
            'section_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'description' => 'required',
            'submission_date' => 'required',
            'attachment' => 'mimes:word,pdf|max:3072',
        ];
        $this->validate($request, $rules);
        
        $data = $request->input();

        if($request->hasFile('attachment')) {
            $oldFile = $homework->getFirstMedia(config('app.name').'/homeworks/');
            if(!empty($oldFile)) {
                $oldFile->delete();
            }
            $homework->addMedia($request->file('attachment'))->toMediaCollection(config('app.name').'/homeworks/','s3');
        }
        /*if($request->hasFile('attachment')) {
            $storagepath = $request->file('attachment')->store('public/homeworks');
            $fileName = basename($storagepath);
            $data['attachment'] = $fileName;

            //if file change then delete old one
            $oldFile = $request->get('oldAttachment','');
            if( !empty($oldFile) ) {
                $file_path = "public/homeworks/".$oldFile;
                Storage::delete($file_path);
            }
        } else {
            $data['attachment'] = $request->get('oldAttachment','');
        }*/
        $data['submission_date'] = Carbon::createFromFormat('d/m/Y', $data['submission_date'])->toDateString();
        unset($data['oldAttachment']);

        $homework->fill($data);
        $homework->save();

        // Notifications
        $classStudents = DB::table('registrations')->selectRaw('GROUP_CONCAT(students.user_id) as userIDs')->leftJoin('students', 'students.id', '=', 'registrations.student_id')->where('registrations.class_id', $data['class_id'])->where('registrations.section_id', $data['section_id'])->whereNotNull('students.user_id')->whereNull('registrations.deleted_at')->where('registrations.status', AppHelper::ACTIVE)->whereNull('students.deleted_at')->where('students.status', AppHelper::ACTIVE)->first();
        $notiMessage = "Homework updated by ".auth()->user()->name;
        AppHelper::notifyUsers($classStudents->userIDs, $notiMessage);
        // Notifications

        return redirect()->route('homework.index')->with('success', 'Homework updated!');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $homework = Homework::where('id', $id)->first();
        if(!$homework || (auth()->user()->role == 'Student' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_STUDENT))) {
            abort(404);
        }
        try {
            /*$attachedFile = $homework->attachment;
            if( !empty($attachedFile) ) {
                $file_path = "public/homeworks/".$attachedFile;
                Storage::delete($file_path);
            }*/
            $homework->delete();
            Homework::destroy($id);
            return redirect()->route('homework.index')->with('success', 'Homework deleted!');
        } catch (\Exception $ex) {
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return redirect()->route('homework.index')->with('error', $message);
        }
    }

    /*
     * Return homework submission form
     */
    public function submissionForm(Request $request) {
        if($request->ajax()) {
            $homeworkID = $request->get('homeworkID');
            $view = view("backend.homeworks.submissionForm", compact('homeworkID'))->render();
            return response()->json(['html' => $view]);
        }
    }

    /*
     * Submit Homework
     */
    public function submitHomework(Request $request, $homeworkID) {
        $homework = Homework::where('id', $homeworkID)->first();
        $studentData = DB::table('students')->select('id')->where('user_id', auth()->user()->id)->first();
        $homeworkSubmission = HomeworkSubmission::where('homework_id', $homeworkID)->where('student_id', $studentData->id)->first();
        if(!$homework) {
            abort(404);
        }
        $rules =  [
            'attachment' => 'mimes:word,pdf|max:3072',
        ];
        $this->validate($request, $rules);
        $data = $request->input();
        /*if($request->hasFile('attachment')) {
            $storagepath = $request->file('attachment')->store('public/homework_submissions');
            $fileName = basename($storagepath);
            $data['attachment'] = $fileName;
        } else {
            $data['attachment'] = NULL;
        }
        $oldFile = (!empty($homeworkSubmission) && !empty($homeworkSubmission->attachment))?$homeworkSubmission->attachment:'';
        if( !empty($oldFile) ) {
            $file_path = "public/homework_submissions/".$homeworkSubmission->attachment;
            Storage::delete($file_path);
        }*/
        $data['homework_id'] = $homeworkID;
        $data['student_id'] = $studentData->id;
        $data['status'] = 'pending';
        unset($data['_token']);

        DB::beginTransaction();
        try {
            if(!empty($homeworkSubmission)) {
                $oldFile = $homeworkSubmission->getFirstMedia(config('app.name').'/homework_submissions/');
                if(!empty($oldFile)) {
                    $oldFile->delete();
                }
                $data['count'] = $homeworkSubmission->count + 1;
                $data['updated_at'] = date('Y-m-d H:i:s');
                HomeworkSubmission::where('id', $homeworkSubmission->id)->update($data);
            } else {
                $data['count'] = 1;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = NULL;
                $homeworkSubmission = HomeworkSubmission::create($data);
            }
            if($request->hasFile('attachment')) {
                $homeworkSubmission->addMedia($request->file('attachment'))->toMediaCollection(config('app.name').'/homework_submissions/','s3');
            }
            $homework->fill(array('status' => 'incomplete'));
            $homework->save();
            DB::commit();

            // Notifications
            $notiMessage = "Homework submitted by ".auth()->user()->name;
            AppHelper::notifyUsers($homework->user_id, $notiMessage);
            // Notifications

            return redirect()->route('homework.index')->with('success', "Homework submitted!");
        } catch (\Exception $ex) {
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return redirect()->route('homework.index')->with('error', $message);
        }
    }

    /*
     * Return Homework Submissions
     */
    public function homeworkSubmissions(Request $request) {
        if($request->ajax()) {
            $homeworkID = $request->get('homeworkID');
            $submissions = HomeworkSubmission::select('homework_submissions.*', 'students.name as studentName')
            ->leftJoin('students', 'homework_submissions.student_id', '=', 'students.id')
            ->where('homework_id', $homeworkID)
            ->get();
            $view = view("backend.homeworks.homeworkSubmissions", compact('submissions'))->render();
            return response()->json(['html' => $view]);
        }
    }

    /*
     * Update Homework Submission Status
     */
    public function updateSubmissionStatus(Request $request) {
        if($request->ajax()) {
            $submissionID = $request->input('submissionID');
            $status = $request->input('status');
            DB::beginTransaction();
            try {
                $data['status'] = $status;
                DB::table('homework_submissions')->where('id', $submissionID)->update($data);
                DB::commit();

                // Update Homework if all submissions received and completed
                $homeworkSubData = DB::table('homework_submissions')->select('homework_id', 'student_id')->where('id', $submissionID)->first();
                $homeworkID = $homeworkSubData->homework_id;
                $homeworkData = Homework::select('class_id', 'section_id', 'subject_id')->where('id', $homeworkID)->first();
                $countClassStudents = DB::table('registrations')->where('class_id', $homeworkData->class_id)->where('section_id', $homeworkData->section_id)->count();
                $countSubmissions = DB::table('homework_submissions')->where('homework_id', $homeworkID)->where('status', 'complete')->count();
                if($countClassStudents == $countSubmissions) {
                    $homeworkStatus = 'complete';
                } else {
                    $homeworkStatus = 'incomplete';
                }
                Homework::where('id', $homeworkID)->update(array('status' => $homeworkStatus));
                // Update Homework if all submissions received and completed

                // Notification
                $classStudents = DB::table('students')->selectRaw('GROUP_CONCAT(students.user_id) as userIDs')->where('id', $homeworkSubData->student_id)->first();
                $notiMessage = "Homework marked as $status by ".auth()->user()->name;
                AppHelper::notifyUsers($classStudents->userIDs, $notiMessage);
                // Notification

                return response()->json(['message' => 'Status updated!', 'status' => 'success', 'hwStatus' => ucfirst($homeworkStatus), 'homeworkID' => $homeworkID]);
            } catch(\Exception $ex) {
                DB::rollback();
                $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
                return response()->json(['message' => $message, 'status' => 'success']);
            }
        }
    }

}
