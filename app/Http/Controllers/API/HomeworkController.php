<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\IClass;
use App\User;
use App\Homework;
use App\HomeworkSubmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\AppHelper;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Facades\Storage;
use \stdClass;
use App\Jobs\HomeworkCreated;

class HomeworkController extends Controller
{

    public $successStatus = 200;

    protected $hasher;
    public function __construct(HasherContract $hasher)
    {
        $this->hasher = $hasher;
    }

    /*
     * Return Homework(s)
     */
    public function getHomework(Request $request) {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $homeworkID = $request->get('id', 0);

        $query = Homework::select('homeworks.*', 'i_classes.name as className', 'sections.name as sectionName', 'subjects.name as subjectName')
        ->leftJoin('i_classes', 'homeworks.class_id', '=', 'i_classes.id')
        ->leftJoin('sections', 'homeworks.section_id', '=', 'sections.id')
        ->leftJoin('subjects', 'homeworks.subject_id', '=', 'subjects.id')
        ->orderBy('submission_date', 'ASC');

        if(!empty($homeworkID)) {
            $query->where('homeworks.id', $homeworkID);
        }

        $homeworks = $query->get();

        $homeworks = $query->get();
        if(!empty($homeworks)) {
            foreach($homeworks as $key => $homework) {
                $homeworks[$key]->attachment = $homework->getFirstMediaUrl(config('app.name').'/homeworks/');
            }
        }

        return response()->json(['success' => ($query) ? true : false, 'data' => $homeworks], $this->successStatus);
        die;
    }

    /*
     * Create Homework
     */
    public function store(Request $request) {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }

        $data = $request->input();
        /*if($request->hasFile('attachment')) {
            $storagepath = $request->file('attachment')->store('public/homeworks');
            $fileName = basename($storagepath);
            $data['attachment'] = $fileName;
        } else {
            $data['attachment'] = $request->get('oldAttachment','');
        }*/
        $hwFile = '';
        if(!empty($data['attachment'])) {
            $hwFile = $data['attachment'];
            unset($data['attachment']);
        }
        $data['submission_date'] = Carbon::createFromFormat('d/m/Y', $data['submission_date'])->toDateString();
        $data['user_id'] = $user->id;

        DB::beginTransaction();
        try {
            $homework = Homework::create($data);

            if($request->hasFile('attachment')) {
                $homework->addMedia($request->file('attachment'))->toMediaCollection(config('app.name').'/homeworks/','s3');
            } elseif(!empty($hwFile)) {
                $homework->addMediaFromBase64($hwFile)->toMediaCollection(config('app.name').'/homeworks/','s3');
            }

            DB::commit();

            // Notifications
            $classStudents = DB::table('registrations')->selectRaw('GROUP_CONCAT(students.user_id) as userIDs')->leftJoin('students', 'students.id', '=', 'registrations.student_id')->where('class_id', $data['class_id'])->where('section_id', $data['section_id'])->first();
            $notiMessage = "Homework added by ".$user->name;
            AppHelper::notifyUsers($classStudents->userIDs, $notiMessage);
            HomeworkCreated::dispatch($homework->id, explode(',', $classStudents->userIDs));
            // Notifications

            return response()->json(['success' => true, 'data' => $data], $this->successStatus);
        } catch (\Exception $ex) {
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return response()->json(['success' => false, 'data' => $message], $this->successStatus);
        }
        die;
    }

    /*
     * Update Homework
     */
    public function update(Request $request, $id) {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }

        $homework = Homework::where('id', $id)->first();
        if (!$homework) {
            return response()->json(['success' => false, 'message' => 'Homework not found!'], 404);
        }

        $data = $request->input();
        $hwFile = '';
        if(!empty($data['attachment'])) {
            $hwFile = $data['attachment'];
            unset($data['attachment']);
        }

        if($request->hasFile('attachment') || !empty($hwFile)) {
            $oldFile = $homework->getFirstMedia(config('app.name').'/homeworks/');
            if(!empty($oldFile)) {
                $oldFile->delete();
            }
            if($request->hasFile('attachment')) {
                $homework->addMedia($request->file('attachment'))->toMediaCollection(config('app.name').'/homeworks/','s3');
            } elseif(!empty($hwFile)) {
                $homework->addMediaFromBase64($hwFile)->toMediaCollection(config('app.name').'/homeworks/','s3');
            }
        }
        /*if($request->hasFile('attachment')) {
            $storagepath = $request->file('attachment')->store('public/homeworks');
            $fileName = basename($storagepath);
            $data['attachment'] = $fileName;

            //if file change then delete old one
            $oldFile = $homework->attachment;
            if( !empty($oldFile) ) {
                $file_path = "public/homeworks/".$oldFile;
                Storage::delete($file_path);
            }
        }*/
        $data['submission_date'] = Carbon::createFromFormat('d/m/Y', $data['submission_date'])->toDateString();

        try {
            Homework::where('id', $id)->update($data);

            // Notifications
            $classStudents = DB::table('registrations')->selectRaw('GROUP_CONCAT(students.user_id) as userIDs')->leftJoin('students', 'students.id', '=', 'registrations.student_id')->where('class_id', $data['class_id'])->where('section_id', $data['section_id'])->first();
            $notiMessage = "Homework updated by ".$user->name;
            AppHelper::notifyUsers($classStudents->userIDs, $notiMessage);
            // Notifications

            return response()->json(['success' => true, 'data' => "Homework updated!"], $this->successStatus);
        } catch (\Exception $ex) {
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return response()->json(['success' => false, 'data' => $message], $this->successStatus);
        }
        die;
    }

    /*
     * Delete Homework
     */
    public function destroy($id) {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }

        $homework = Homework::where('id', $id)->first();
        if(!$homework) {
            return response()->json(['success' => false, 'message' => 'Homework not found!'], 404);
        }
        try {
            /*$attachedFile = $homework->attachment;
            if( !empty($attachedFile) ) {
                $file_path = "public/homeworks/".$attachedFile;
                Storage::delete($file_path);
            }*/
            $homework->delete();
            Homework::destroy($id);
            return response()->json(['success' => true, 'data' => "Homework deleted!"], $this->successStatus);
        } catch (\Exception $ex) {
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return response()->json(['success' => false, 'data' => $message], $this->successStatus);
        }
        die;
    }

    /*
     * Update Submission Status
     */
    public function updateSubmissionStatus(Request $request, $submissionID) {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }
        $submission = DB::table('homework_submissions')->where('id', $submissionID)->first();
        if(!$submission) {
            return response()->json(['success' => false, 'message' => 'Submission not found!'], 404);
        }

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
            $notiMessage = "Homework marked as $status by ".$user->name;
            AppHelper::notifyUsers($classStudents->userIDs, $notiMessage);
            // Notification

            return response()->json(['success' => true, 'data' => "Submission status updated!"], $this->successStatus);
        } catch(\Exception $ex) {
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return response()->json(['success' => false, 'data' => $message], $this->successStatus);
        }
        die;
    }

    /*
     * Return Student Homeworks
     */
    public function getStudentHomeworks($studentID) {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }

        $studentData = DB::table('registrations')->select('class_id', 'section_id')->where('student_id', '=', $studentID)->first();
        if(!$studentData) {
            return response()->json(['success' => false, 'message' => 'Student not found!'], 404);
        }

        // $attachmentURLPrefix = asset('storage/homeworks').'/';
        $homeworks = Homework::select('id', 'title', 'description', 'attachment', 'submission_date')
        ->where('class_id', '=', $studentData->class_id)
        ->where('section_id', '=', $studentData->section_id)
        ->orderBy('submission_date', 'ASC')
        ->get();
        if(!empty($homeworks)) {
            foreach($homeworks as $key => $homework) {
                // $homeworks[$key]->attachment = (!empty($homework->attachment))?$attachmentURLPrefix.$homework->attachment:'';
                $homeworks[$key]->attachment = $homework->getFirstMediaUrl(config('app.name').'/homeworks/');;
                $submissions = DB::table('homework_submissions')->select('id', 'status')
                ->where('homework_id', $homework->id)
                ->where('student_id', $studentID)
                ->get();
                $homeworks[$key]->submissions = $submissions;
            }
        }
        return response()->json(['success' => true, 'data' => $homeworks], $this->successStatus);
        die;
    }

    /*
     * Submit Homework
     */
    public function submitHomework(Request $request) {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }

        $homeworkID = $request->input('homework_id');
        $homework = Homework::where('id', $homeworkID)->first();
        $studentData = DB::table('students')->select('id')->where('user_id', $user->id)->first();
        $homeworkSubmission = HomeworkSubmission::where('homework_id', $homeworkID)->where('student_id', $studentData->id)->first();
        if(!$homework) {
            return response()->json(['success' => false, 'message' => 'Homework not found!'], 404);
        }
        $data['homework_id'] = $homeworkID;
        $data['student_id'] = $studentData->id;
        $data['status'] = 'pending';

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
            } elseif(!empty($request->input('attachment'))) {
                $homeworkSubmission->addMediaFromBase64($request->input('attachment'))->toMediaCollection(config('app.name').'/homework_submissions/','s3');
            }
            $homework->fill(array('status' => 'incomplete'));
            $homework->save();
            DB::commit();

            // Notifications
            $notiMessage = "Homework submitted by ".$user->name;
            AppHelper::notifyUsers($homework->user_id, $notiMessage);
            // Notifications

            return response()->json(['success' => true, 'data' => "Homework submitted!"], $this->successStatus);
        } catch (\Exception $ex) {
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            return response()->json(['success' => false, 'data' => $message], $this->successStatus);
        }
        die;
    }

}
