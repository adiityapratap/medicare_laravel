<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\AcademicYear;
use App\Registration;
use App\Student;
use App\User;
use App\Http\Helpers\AppHelper;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserController extends Controller {
    public $successStatus = 200;

    public function updateUser(Request $request, $id){
        $user = JWTAuth::parseToken()->authenticate();

        if($id) {
            $updatable = User::find($id);
        } else {
            $updatable = $user;
        }

        if (!$user || !$updatable) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        if(isset($updatable->role)) {
            $roleId = $updatable->role->role_id;
    
            if ($roleId == AppHelper::USER_TEACHER) {
                return $this->updateTeacher($request, $updatable);
            }
    
            if ($roleId == AppHelper::USER_STUDENT) {
                return $this->updateStudent($request, $updatable);
            }
        }
        return response()->json(['success' => false, 'message' => 'This token not belongs to student or teacher'], 500);
        die;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Item  $item
     * @return \Illuminate\Http\Response
     */
    private function updateStudent($request, $user, $id=NULL)
    {
        $student =  Student::where('user_id', $user->id)->first();
        if(!$student){
            return response()->json(['success' => false, 'message' => 'Student not found'], 404);
        }

        $regiInfo = Registration::where('student_id', $student->id)->first();
        if(!$regiInfo){
            return response()->json(['success' => false, 'message' => 'Student not found'], 404);
        }

        $data = $request->input();

        if(isset($data['nationality']) && $data['nationality'] == 'Other'){
            $data['nationality']  = $data['nationality_other'];
        }

        if($request->hasFile('photo')) {
            $oldFile = $student->getFirstMedia(config('app.name').'/students/');
            if(!empty($oldFile)) {
                $oldFile->delete();
            }
            $student->addMedia($request->file('photo'))->toMediaCollection(config('app.name').'/students/','s3');
        }
        
        // now check if student academic information changed, if so then log it
        $isChanged = false;
        $logData = [];
        $timeNow = Carbon::now();

        $message = 'Something went wrong!';
        DB::beginTransaction();
        try {
            //
            if(!$student->user_id && $user->id){
                $data['user_id'] = $user->id;
            }


            // now save student
            $student->fill($data);
            $student->save();

            //if have changes then insert log
            if($isChanged){
                DB::table('student_info_log')->insert($logData);
            }
            // now commit the database
            DB::commit();

            return response()->json(['success' => true, 'data' => 'Student updated!'], $this->successStatus);
        }
        catch(\Exception $e){
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
//            dd($message);
        }
        return response()->json(['success' => false, 'data' => 'Bad request'], 403);
    }

     /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Item  $item
     * @return \Illuminate\Http\Response
     */
    private function updateTeacher($request, $user, $id=NULL) {
        return response()->json(['success' => false, 'data' => 'Bad request'], 403);
    }
}

