<?php

namespace App\Http\Controllers\API;

use Log;
use App\AcademicYear;
use App\Employee;
use App\IClass;
use App\Models\PasswordResets;
use App\Permission;
use App\Registration;
use App\Role;
use App\Section;
use App\smsLog;
use App\Student;
use App\Subject;
use App\User;
use App\UserRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\AppHelper;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Traits\MessageTrait;

class ApiController extends Controller
{
    use MessageTrait;
    
    public $successStatus = 200;

    protected $hasher;

    public function __construct(HasherContract $hasher)
    {
        $this->hasher = $hasher;
    }

    /**
     * Handle an authentication attempt.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return Response
     */
    public function login(Request $request)
    {

        $username = $request->get('username');
        $password = $request->get('password') ? $request->get('password') : '';
        $remember = $request->get('remember');
        $rememberValue = (int)$remember ? true : false;

        try {
            if (!$token = JWTAuth::attempt(['username' => $username, 'password' => $password, 'status' => AppHelper::ACTIVE], ['exp' => Carbon::now()->addDay()->timestamp])) {
                return response()->json(['success' => false, 'message' => 'Your email/password combination was incorrect OR account disabled!'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'message' => 'could_not_create_token'], 500);
        }

        $user = auth()->user();
        $role_name = \App\Role::where('id', $user->role->role_id)
            ->first();
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'role_id' => $user->role->role_id,
            'role_name' => $role_name->name,
            'token' => $token
        ];

        return response()->json(['success' => true, 'data' => $userData], $this->successStatus);

        die;
    }

    /**
     * Handle an user logout.
     *
     * @return Response
     */
    public function logout()
    {
        Auth::logout();
        die;
    }

    /**
     * Handle  Profile.
     *
     * @return Response
     */
    public function getProfile()
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $roleId = $user->role->role_id;
        $userId = $user->id;

        if ($roleId == AppHelper::USER_TEACHER) {
            return $this->getTeacherProfile($userId, $user);
        }

        if ($roleId == AppHelper::USER_STUDENT) {
            return $this->getStudentProfile($userId, $user);
        }
        return response()->json(['success' => false, 'message' => 'This token not belongs to student or teacher'], 500);
        die;
    }

    /**
     * Handle Student Profile.
     *
     * @return Response
     */
    public function getStudentProfile($studentId = NULL, $user)
    {
        if (!empty($studentId)) {
            $student = Registration::
                with('student')
                ->with('class')
                ->with('section')
                ->with('acYear')
                ->whereHas('student', function ($query) use ($studentId) {
                    return $query->where('user_id', $studentId);
                })
                ->first();

            $student['student']['photo'] = AppHelper::getS3URL('student', $student['student']['id']);
            $student['notifications'] = $user->unreadNotifications->count();

            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Student details not found'], 404);
            }
            return response()->json(['success' => true, 'data' => $student], $this->successStatus);
        }
        return response()->json(['success' => false, 'message' => 'Student Id is missing'], 500);
        die;
    }

    /**
     * Handle Teacher Profile.
     *
     * @return Response
     */
    public function getTeacherProfile($teacherId = NULL, $user)
    {
        if (!empty($teacherId)) {
            $teacher = Employee::with('user')->where('role_id', AppHelper::EMP_TEACHER)->where('user_id', $teacherId)->first();
            if (!$teacher) {
                return response()->json(['success' => false, 'message' => 'Teacher details not found'], 404);
            }

            $sections = Section::with(['class' => function ($query) {
                $query->select('name', 'id');
            }])
                ->where('teacher_id', $teacher->id)
                ->select('name', 'class_id')
                ->orderBy('name', 'asc')
                ->get();

            $teacher['sections'] = $sections;

            $subjects = Subject::with(['class' => function ($query) {
                $query->select('name', 'id');
            }])
                ->where('teacher_id', $teacher->id)
                ->select('name', 'class_id', 'code', 'id as subject_id')
                ->orderBy('name', 'asc')
                ->get();

            $teacher['subjects'] = $subjects;
            $teacher['notifications'] = $user->unreadNotifications->count();

            $teacher->photo = AppHelper::getS3URL('employee', $teacher->id);
            $teacher->signature = AppHelper::getS3URL('employee_signature', $teacher->id);

            return response()->json(['success' => true, 'data' => $teacher], $this->successStatus);
        }
        return response()->json(['success' => false, 'message' => 'Teacher Id is missing'], 500);
        die;
    }

    /**
     * Handle User Messages.
     *
     * @return Response
     */
    public function getMessages()
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }

        $page_limit = 10;

        if (isset($_GET['limit']) && !empty($_GET['limit'])) {
            $page_limit = $_GET['limit'];
        }

        // $read_status = isset($_GET['read_status']) ? $_GET['read_status'] : "";

        // if (empty($read_status)) {
        // $message_count = $all_messages = DB::table('message_notifications as mn')
        // ->join('message_user_mappings as mm', 'mn.id', '=', 'mm.message_id')
        // ->select('mn.message_type as message_type', DB::raw('count(*) as total'))
        // ->where('mm.user_id', $user->id)
        // ->pluck('total','message_type');
        // }

        $query = DB::table('message_notifications as mn')
            ->join('message_user_mappings as mm', 'mn.id', '=', 'mm.message_id')
            ->select('mn.id', 'mm.student_id', 'mn.message', 'mn.message_type', 'mn.created_at', 'mn.updated_at');

        // if (!empty($read_status)) {
        //     if ($read_status == 'unread') {
        //         $message_status = '0';
        //     }
        //     if ($read_status == 'read') {
        //         $message_status = '1';
        //     }
        //     $query->where('read_status', $message_status);
        // }

        if ($user->role->role_id == AppHelper::USER_STUDENT) {
            $query->where('mm.student_id', $user->id);
            $query->orWhere('mm.all', $user->id);
            $query->orWhere('mm.section_id', $user->student->registration[0]->section_id);
        } else if ($user->role->role_id != AppHelper::USER_STUDENT && $user->role->role_id != AppHelper::USER_ADMIN) {
            $query->where('mm.staff_id', $user->id);
        } else {
            $query->where('mm.all', $user->id);
        }
        $query->orderBy('mn.created_at', 'desc');
        $all_messages = $query->paginate($page_limit)->toArray();

        // if (empty($read_status)) {
        //     $all_messages['unread_count'] = isset($message_count[0]) ? $message_count[0] : 0;
        //     $all_messages['read_count'] = isset($message_count[1]) ? $message_count[1] : 0;
        // }

        unset ($all_messages['first_page_url']);
        unset ($all_messages['from']);
        unset ($all_messages['last_page']);
        unset ($all_messages['last_page_url']);
        unset ($all_messages['next_page_url']);
        unset ($all_messages['path']);
        unset ($all_messages['prev_page_url']);
        unset ($all_messages['to']);

        return response()->json(['success' => true, 'data' => $all_messages], $this->successStatus);
        die;
    }

    public function sendMessages(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        if($user->role->role_id == AppHelper::USER_STUDENT || $user->role->role_id == AppHelper::USER_PARENTS) {
            return response()->json(['success' => false, 'message' => 'Looks like you are not supposed to do this!'], 401);
        }

        ini_set('max_execution_time', 1800);
        
        $data = $request->all();

        if (!isset($data['sections']) && !isset($data['students']) && !isset($data['users'])) {
            return response()->json([
                'success' => false, 
                'message' => 'Please select any one of the class or student or staff'
            ], 400);
        }

        $data['section'] = $data['sections'];
        unset($data['sections']);

        try {
            $this->processmessage($data);

            return response()->json([
                'success' => true, 
                'message' => 'Wonderful, message sent successfully!'
            ], $this->successStatus);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'success' => false, 
                'message' => 'Oops!, I lost you for a moment. Can you please try again?'
            ], 500);
        }
    }

    /**
     * Handle Message Update.
     *
     * @return Response
     */
    public function updateMessages(Request $request)
    {

        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }

        $message_id = $request->get('message_id');
        $read_status = $request->get('read_status');

        if ($message_id == "" || $read_status == "") {
            return response()->json(['success' => false, 'message' => 'Message id or Read status is missing'], 500);
        }

        $query = DB::table('message_notifications')
            ->where(['id' => $message_id, 'user_id' => $user->id])
            ->update(['read_status' => $read_status]);

        $messageData = (object)[];
        if ($query) {
            $messageData = DB::table('message_notifications')
                ->select('id', 'user_id', 'message', 'message_type', 'read_status', 'created_at', 'updated_at')
                ->where('id', $message_id)
                ->first();
        }


        return response()->json(['success' => ($query) ? true : false, 'data' => $messageData], $this->successStatus);
        die;
    }

    /**
     * Handle Message Delete.
     *
     * @return Response
     */
    public function deleteMessages(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }
        $message_ids = $request->get('message_ids');
        // print_r($message_ids);die;
        if (empty($message_ids)) {
            return response()->json(['success' => false, 'message' => 'Message id is missing'], 500);
        }

        $query = DB::table('message_notifications')->whereIn('id', $message_ids)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['success' => ($query) ? true : false], $this->successStatus);
        die;
    }

    /**
     * Handle Get Read/Unread Notification.
     *
     * @return Response
     */
    public function notifications(Request $request, $userid)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }
        $json = array();
        $page_limit = 10;

        if (isset($_GET['limit']) && !empty($_GET['limit'])) {
            $page_limit = $_GET['limit'];
        }
        $input = $request->all();
        if ($input['read'] == 'false') {
            $json = DB::table('notifications')->where('notifiable_id', $userid)
                ->where('read_at', Null)
                ->select('id', 'data as message', 'created_at', 'read_at')
                ->orderBy('created_at', 'desc')
                ->paginate($page_limit)->toArray();
        } else {
            $json = DB::table('notifications')->where('notifiable_id', $userid)
            ->select('id', 'data as message', 'created_at', 'read_at')            
            ->orderBy('created_at', 'desc')
            ->paginate($page_limit)->toArray();
        }
        return response()->json($json);
        die;
    }

    /**
     * Handle Get update read time Notification  .
     *
     * @return Response
     */
    public function updatetimereadnotification(Request $request, $notificationid)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token Error'], 404);
        }
        $input = $request->all();
        $json = DB::table('notifications')->where('id', $notificationid)->where('read_at', Null)->first();
        if (!empty($json)) {
            $data['read_at'] = date('Y-m-d h:i:s');
            DB::table('notifications')->where('id', $notificationid)->update($data);
            $json = 1;
            return response()->json($json);
            die;
        } else {
            $json = 0;
            return response()->json($json);
            die;
        }
    }
}
