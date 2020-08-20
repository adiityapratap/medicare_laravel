<?php

namespace App\Http\Controllers\Backend;

use Log;
use App\AcademicYear;
use App\CircularNotification;
use App\Employee;
use App\Homework;
use App\HomeworkSubmission;
use App\IClass;
use App\FeeCol;
use App\Models\PasswordResets;
use App\Permission;
use App\Registration;
use App\Role;
use App\Section;
use App\smsLog;
use App\Student;
use App\StudentAttendance;
use App\Subject;
use App\Template;
use App\User;
use App\UserRole;
use App\MessageNotification;
use App\voiceLog;
use App\MessageUserMapping;
use App\AppMeta;
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
use App\Http\Helpers\SmsHelper;
use App\Traits\MessageTrait;
use App\Traits\InstituteUsersTrait;

class UserController extends Controller
{
    use MessageTrait;
    use InstituteUsersTrait;

    protected $hasher;

    public function __construct(HasherContract $hasher)
    {
        $this->hasher = $hasher;
    }


    /**
     * Handle an authentication attempt.
     *
     * @return Response
     */
    public function login()
    {
        if(Auth::user()) {
            return redirect()->route('dashboard');
        }
        return view('backend.user.login');
    }

    /**
     * Handle an authentication attempt.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return Response
     */

    public function authenticate(Request $request)
    {

        //validate form
        $this->validate(
            $request, [
                'username' => 'required',
                'password' => 'required',
            ]
        );

        $username = $request->get('username');
        $password = $request->get('password');
        $remember = $request->has('remember');

        if (Auth::attempt(['username' => $username, 'password' => $password, 'status' => AppHelper::ACTIVE], $remember)) {
            session(['user_session_sha1' => AppHelper::getUserSessionHash()]);
            session(['user_role_id' => auth()->user()->role->role_id]);

            $appSettings = AppHelper::getAppSettings();

            $msgType = "success";
            $msg = "Welcome back " . Auth::user()->name;

            if (!count($appSettings)) {
                $msgType = "warning";
                $msg = "Please update institute information <a href='" . route('settings.institute') . "'> <b>Here</b></a>";
            }
            Auth::logoutOtherDevices($password);

            return redirect()->intended('dashboard')->with($msgType, $msg);

        }
        return redirect()->route('login')->with('error', 'Your email/password combination was incorrect OR account disabled!');
    }


    /**
     * Handle an user logout.
     *
     * @return Response
     */
    public function logout()
    {
        Auth::logout();
        return redirect()->route('login')->with('success', 'Your are now logged out!');
    }

    /**
     * Handle an user lock screen.
     *
     * @return Response
     */
    public function lock()
    {
        $username = auth()->user()->username;
        $name = auth()->user()->name;
        Auth::logout();
        return view('backend.user.lock', compact('username', 'name'));
    }

    /**
     * Handle an user forgot password.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Illuminate\Validation\ValidationException
     */
    public function forgot(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {
            //validate form

            $this->validate($request, [
                'email' => 'required|email',
                'captcha' => 'required|captcha'
            ]);

            $user = User::where('email', $request->get('email'))->first();
            if (!$user) {
                return redirect()->route('forgot')->with('error', 'Account not found on this system!');
            }

            // We will send the password reset link to this user. Once we have attempted
            // to send the link, we will examine the response then see the message we
            // need to show to the user. Finally, we'll send out a proper response.
            $response = $this->broker()->sendResetLink(
                $request->only('email')
            );


            if (Password::RESET_LINK_SENT) {
                return redirect()->route('forgot')->with('success', 'A mail has been send to your e-mail address. Follow the given instruction to reset your password.');

            }

            return redirect()->route('forgot')->with('error', 'Password reset link could not send! Try again later or contact support.');


        }


        return view('backend.user.forgot');
    }


    /**
     * Get the broker to be used during password reset.
     *
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker()
    {
        return Password::broker();
    }

    /**
     * Handle an user reset password.
     *
     * @return Response
     */
    public function reset(Request $request, $token)
    {

        //for save on POST request
        if ($request->isMethod('post')) {
            //validate form

            $this->validate($request, [
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|confirmed|min:6|max:50',
            ]);

            $token = $request->get('token');
            $email = $request->get('email');
            $password = $request->get('password');
            $reset = PasswordResets::where('email', $email)->first();
            if ($reset) {
                //token expiration checking, allow 24 hrs only
                $today = Carbon::now(env('APP_TIMEZONE', 'Asia/Dhaka'));
                $createdDate = Carbon::parse($reset->created_at);
                $hoursGone = $today->diffInHours($createdDate);
                if ($this->hasher->check($token, $reset->token) && $hoursGone <= 24) {
                    $user = User::where('email', '=', $email)->first();
                    $user->password = bcrypt($password);
                    $user->save();
                    $reset->delete();

                    return redirect()->route('login')->with('success', 'Password successfully reset. Login now :)');

                }
            }

            return redirect()->route('forgot')->with('error', 'User not found with this mail or token invalid or expired!');
        }

        return view('backend.user.reset', compact('token'));
    }

    /**
     * Handle an authentication attempt.
     *
     * @return Response
     */
    public function dashboard(Request $request)
    {
        $userRoleId = auth()->user()->roles()->first()->id;
        $teachers = 0;
        $employee = 0;
        $students = 0;
        $subjects = 0;
        $homeworkCount = 0;
        $monthWiseSms = [];
        $studentsCount = [];
        $sectionStudentCount = [];
        $attendanceChartPresentData = [];
        $attendanceChartAbsentData = [];
        $attendanceMonthData = [];
        $class = [];
        $section = [];
        $studentBirthdays = [];
        $teacherBirthdays = [];
        $homeworks = [];
        $circulars = [];
        $announcements = [];
        $notifications = [];
        $totalAttendance = 0;
        $totalPresentAttendance = 0;

        //only admin
        if ($userRoleId == AppHelper::USER_ADMIN || $userRoleId == AppHelper::USER_PRINCIPAL) {

            $teachers = Employee::where('role_id', AppHelper::EMP_TEACHER)->count();
            $employee = Employee::count();
            $students = Student::count();
            $subjects = Subject::count();

            $smsSend = smsLog::selectRaw('count(id) as send,MONTH(created_at) as month')
                ->whereYear('created_at', date('Y'))
                ->groupby('month')
                ->get()
                ->reduce(function ($smsSend, $record) {
                    $smsSend[$record->month] = $record->send;

                    return $smsSend;
                });

            $monthWiseSms['Jan'] = $smsSend[1] ?? 0;
            $monthWiseSms['Feb'] = $smsSend[2] ?? 0;
            $monthWiseSms['Mar'] = $smsSend[3] ?? 0;
            $monthWiseSms['Apr'] = $smsSend[4] ?? 0;
            $monthWiseSms['May'] = $smsSend[5] ?? 0;
            $monthWiseSms['Jun'] = $smsSend[6] ?? 0;
            $monthWiseSms['Jul'] = $smsSend[7] ?? 0;
            $monthWiseSms['Aug'] = $smsSend[8] ?? 0;
            $monthWiseSms['Sep'] = $smsSend[9] ?? 0;
            $monthWiseSms['Oct'] = $smsSend[10] ?? 0;
            $monthWiseSms['Nov'] = $smsSend[11] ?? 0;
            $monthWiseSms['Dec'] = $smsSend[12] ?? 0;

        }

        //only admin end

        //except students
        if ($userRoleId != AppHelper::USER_STUDENT) {
            $academicYearId = 0;
            if (AppHelper::getInstituteCategory() == 'college') {
                $academicYearInfo = AcademicYear::where('status', AppHelper::ACTIVE)
                    ->where('title', env('DASHBOARD_STUDENT_COUNT_DEFAULT_ACADEMIC_YEAR', date('Y')))->first();
                if ($academicYearInfo) {
                    $academicYearId = $academicYearInfo->id;
                }
            } else {
                $academicYearId = AppHelper::getAcademicYear();
            }

            //attendance chart data
            $iclasses = IClass::where('status', AppHelper::ACTIVE)
                ->with(['attendance' => function ($query) use ($academicYearId) {
                    $query->selectRaw('class_id,present,count(registration_id) as total')
                        ->where('academic_year_id', $academicYearId)
                        ->whereDate('attendance_date', date('Y-m-d'))
//                        ->whereDate('attendance_date', '2019-03-02')
                        ->groupBy('class_id', 'present');
                }])->orderBy('order', 'asc')
                ->select('id', 'name')
                ->get();

            $attendanceChartPresentData = [];
            $attendanceChartAbsentData = [];
            foreach ($iclasses as $iclass) {
                $attendanceChartPresentData[$iclass->name] = 0;
                $attendanceChartAbsentData[$iclass->name] = 0;
                foreach ($iclass->attendance as $attendanceSummary) {
                    if ($attendanceSummary->present == "Present") {
                        $attendanceChartPresentData[$iclass->name] = $attendanceSummary->total;
                    } else {
                        $attendanceChartAbsentData[$iclass->name] = $attendanceSummary->total;

                    }
                }
            }

            //end

            $studentsCount = IClass::where('status', AppHelper::ACTIVE)
                ->with(['student' => function ($query) use ($academicYearId) {
                    $query->where('status', AppHelper::ACTIVE)
                        ->where('academic_year_id', $academicYearId)
                        ->selectRaw('class_id, count(*) as total')
                        ->groupBy('class_id');
                }])
				->select('id', 'name')
				->orderBy('order', 'asc')
                ->get();

            $selectedClassIds = explode(',', env('DASHBOARD_SECTION_STUDENT_CLASS_CODE', '90,91,92,100,101,102'));
            $selectedClasses = IClass::where('status', AppHelper::ACTIVE)
                ->whereIn('numeric_value', $selectedClassIds)->pluck('id');

            $sectionStudentCount = Section::where('status', AppHelper::ACTIVE)
                ->whereIn('class_id', $selectedClasses)
                ->with(['student' => function ($query) use ($academicYearId) {
                    $query->where('status', AppHelper::ACTIVE)
                        ->where('academic_year_id', $academicYearId)
                        ->selectRaw('section_id, count(*) as total')
                        ->groupBy('section_id');
                }])
                ->with(['class' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->select('id', 'name', 'class_id')
                ->orderBy('class_id', 'asc')
                ->orderBy('name', 'asc')
                ->get();
        }

        //only students
        if ($userRoleId == AppHelper::USER_STUDENT) {
            $classId = Auth::user()->student->register->class_id;
            $sectionId = Auth::user()->student->register->section_id;
            $subjects = Subject::where('class_id', $classId)->count();
            $class = IClass::find($classId);
            $section = Section::find($sectionId);

            $academicYearId = 0;
            if (AppHelper::getInstituteCategory() == 'college') {
                $academicYearInfo = AcademicYear::where('status', AppHelper::ACTIVE)
                    ->where('title', env('DASHBOARD_STUDENT_COUNT_DEFAULT_ACADEMIC_YEAR', date('Y')))->first();
                if ($academicYearInfo) {
                    $academicYearId = $academicYearInfo->id;
                }
            } else {
                $academicYearId = AppHelper::getAcademicYear();
            }

            //attendance chart data
            $attendances = StudentAttendance::where('academic_year_id', $academicYearId)
                ->where('registration_id', Auth::user()->student->register->id)->orderBy('id', 'DESC')->get()->groupBy(function($val) {
                    return Carbon::createFromFormat('d/m/Y', $val->attendance_date)->format('F');
                })->toArray();
            $groupedArray = [];
            foreach ($attendances as $key => $value) {
                foreach ($value as $attendance) {
                    $totalAttendance += 1;
                    $groupedArray[$key]['present'] = $groupedArray[$key]['present'] ?? 0;
                    $groupedArray[$key]['absent'] = $groupedArray[$key]['absent'] ?? 0;
                    $groupedArray[$key]['total'] = $groupedArray[$key]['total'] ?? 0;
                    if ($attendance['present'] == 'Present') {
                        $totalPresentAttendance += 1;
                        $groupedArray[$key]['present'] = $groupedArray[$key]['present'] + 1;
                    } else {
                        $groupedArray[$key]['absent'] = $groupedArray[$key]['absent'] + 1;
                    }
                    $groupedArray[$key]['total'] = $groupedArray[$key]['total'] + 1;
                }
            }
            foreach ($groupedArray as $array) {
                $attendanceChartPresentData[] = $array['present'];
                $attendanceChartAbsentData[] = $array['absent'];
            }
            $attendanceMonthData = json_encode(array_keys($groupedArray));
            $attendanceChartAbsentData = json_encode($attendanceChartAbsentData);
            $attendanceChartPresentData = json_encode($attendanceChartPresentData);

            //Today's birthday
            $today = "'".date('Y-m-d')."'";
            $studentBirthdays = User::whereHas('student.register', function ($query) use ($classId) {
                $query->where('class_id', $classId);
            })->whereHas('student', function ($query) use ($today) {
                $query->whereRaw("DAY(str_to_date(dob, '%d/%m/%Y')) = DAY(str_to_date($today, '%Y-%m-%d')) AND MONTH(str_to_date(dob, '%d/%m/%Y')) = MONTH(str_to_date($today, '%Y-%m-%d'))");
            })->get();
            $teacherBirthdays = Employee::whereHas('subject', function ($query) use ($classId) {
                $query->where('class_id', $classId);
            })->whereRaw("DAY(str_to_date(dob, '%d/%m/%Y')) = DAY(str_to_date($today, '%Y-%m-%d')) AND MONTH(str_to_date(dob, '%d/%m/%Y')) = MONTH(str_to_date($today, '%Y-%m-%d'))")->get();

            //Homework
            $homeworkCount = Homework::whereHas('submission', function($query) {
                $query->where('student_id', Auth::user()->student->id);
            })->where(['status' => 'incomplete'])->count();
            $homeworks = Homework::whereHas('submission', function($query) {
                $query->where('student_id', Auth::user()->student->id);
            })->orderBy('id', 'DESC')->limit(5)->get();

            //Latest Circulars
            $circulars = CircularNotification::whereHas('mapping', function($query) use($sectionId) {
                $query->where('student_id', Auth::user()->student->id)->orWhere('all', 1)->orWhere('section_id', $sectionId);
            })->where('circular_type', 'circular')->orderBy('id', 'DESC')->limit(5)->get();

            //Latest Announcement
            $announcements = CircularNotification::whereHas('mapping', function($query) use($sectionId) {
                $query->where('student_id', Auth::user()->student->id)->orWhere('all', 1)->orWhere('section_id', $sectionId);
            })->where('circular_type', 'announcement')->orderBy('id', 'DESC')->limit(5)->get();

            //Latest notifications
            $notifications = MessageNotification::whereHas('mapping', function($query) use($sectionId) {
                $query->where('student_id', Auth::user()->student->id)->orWhere('all', 1)->orWhere('section_id', $sectionId);
            })->orderBy('id', 'DESC')->limit(5)->get();
        }


        return view('backend.user.dashboard', compact(
            'class',
            'section',
            'teachers',
            'homeworkCount',
            'homeworks',
            'circulars',
            'announcements',
            'notifications',
            'employee',
            'students',
            'subjects',
            'userRoleId',
            'studentsCount',
            'sectionStudentCount',
            'monthWiseSms',
            'totalAttendance',
            'totalPresentAttendance',
            'attendanceChartPresentData',
            'attendanceChartAbsentData',
            'attendanceMonthData',
            'studentBirthdays',
            'teacherBirthdays'
        ));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Illuminate\Validation\ValidationException
     */
    public function profile(Request $request)
    {

        $isPost = false;

        $user = User::where('id', auth()->user()->id)->with('role')->first();


        $userRole = Role::findOrFail($user->role->role_id)->first();


        if ($request->isMethod('post')) {
            $isPost = true;
            //validate form
            $this->validate($request, [
                'username' => 'required|min:5',
                'email' => 'required|email',
                'name' => 'required|min:5|max:255',
                'phone_no' => 'nullable|max:15',
            ]);
            $isExists = false;
            $oldUsername = $user->username;
            $oldEmail = $user->email;
            $newUserName = $request->get('username');
            $newEmail = $request->get('email');
            if ($oldUsername != $newUserName) {
                $existUsers = User::where('username', $newUserName)->count();
                if ($existUsers) {
                    session()->flash('error', 'Username already exists for another account!');
                    $isExists = true;
                }

            }

            if ($oldEmail != $newEmail) {
                $existUsers = User::where('email', $newEmail)->count();
                if ($existUsers) {
                    session()->flash('error', 'Email already exists for another account!');
                    $isExists = true;
                }

            }

            if (!$isExists) {
                $user->name = $request->get('name');
                $user->phone_no = $request->get('phone_no','');
                $user->email = $newEmail;
                $user->username = $newUserName;
                $user->save();

                return redirect()->route('profile')->with('success', 'Profile updated.');

            }

        }

        $userRoleId = session('user_role_id', 0);

        // For student
        if ($userRoleId == AppHelper::USER_STUDENT) {

            $id = Auth::user()->student->register->id;
            // if print id card of this student then
            // Do here
            if($request->query->get('print_idcard',0)) {

                $templateId = AppHelper::getAppSettings('student_idcard_template');
                $templateConfig = Template::where('id', $templateId)->where('type',3)->where('role_id', AppHelper::USER_STUDENT)->first();

                if(!$templateConfig){
                    return redirect()->route('administrator.template.idcard.index')->with('error', 'Template not found!');
                }

                $templateConfig = json_decode($templateConfig->content);

                $format = "format_";
                if($templateConfig->format_id == 2){
                    $format .="two";
                }
                else if($templateConfig->format_id == 3){
                    $format .="three";
                }
                else {
                    $format .="one";
                }

                //get institute information
                $instituteInfo = AppHelper::getAppSettings('institute_settings');


                $students = Registration::where('id', $id)
                    ->where('status', AppHelper::ACTIVE)
                    ->with(['student' => function ($query) {
                        $query->select('name', 'blood_group', 'id', 'photo');
                    }])
                    ->with(['class' => function ($query) {
                        $query->select('name', 'group', 'id');
                    }])
                    ->select('id', 'roll_no', 'regi_no', 'student_id','class_id', 'house', 'academic_year_id')
                    ->orderBy('roll_no', 'asc')
                    ->get();

                if(!$students){
                    abort(404);
                }


                $acYearInfo = AcademicYear::where('id', $students[0]->academic_year_id)->first();
                $session = $acYearInfo->title;
                $validity = $acYearInfo->end_date->format('Y');

                if($templateConfig->format_id == 3){
                    $validity = $acYearInfo->end_date->format('F Y');
                }


                $totalStudent = count($students);

                $side = 'both';
                return view('backend.report.student.idcard.'.$format, compact(
                    'templateConfig',
                    'instituteInfo',
                    'side',
                    'students',
                    'totalStudent',
                    'session',
                    'validity'
                ));
            }

            //get student
            $student = Registration::where('id', $id)
                ->with('student')
                ->with('class')
                ->with('section')
                ->with('acYear')
                ->first();
            /**fees */
            $fees=DB::Table('fee_collection')
			->select(DB::RAW("billNo,payableAmount,paidAmount,dueAmount,DATE_FORMAT(payDate,'%D %M,%Y') AS date"))
			->where('class_id',auth()->user()->student->register->class_id)
			->where('student_id',auth()->user()->student->id)
			->whereNull('deleted_at')
            ->paginate(25);
			$totals = FeeCol::select(DB::RAW('IFNULL(sum(payableAmount),0) as payTotal,IFNULL(sum(paidAmount),0) as paiTotal,(IFNULL(sum(payableAmount),0)- IFNULL(sum(paidAmount),0)) as dueAmount'))
			->where('class_id',auth()->user()->student->register->class_id)
			->where('student_id',auth()->user()->student->id)
			->whereNull('deleted_at')
            ->first();

            if(!$student){
                abort(404);
            }
            $username = '';
            $fourthSubject = '';
            $altfourthSubject = '';

            if($student->fourth_subject) {
                $subjectInfo = Subject::where('id', $student->fourth_subject)->select('name')->first();
                $fourthSubject = $subjectInfo->name;
            }

            if($student->alt_fourth_subject) {
                $subjectInfo = Subject::where('id', $student->alt_fourth_subject)->select('name')->first();
                $altfourthSubject = $subjectInfo->name;
            }

            if($student->student->user_id){
                $user = User::find($student->student->user_id);
                $username = $user->username;
            }

            $student->student->photo = AppHelper::getS3URL('student', $student->student->id);

            return view('backend.student.profile', compact('student', 'username', 'fourthSubject', 'altfourthSubject','fees','totals'));
        }

        return view('backend.user.profile', compact('user', 'isPost', 'userRole'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Illuminate\Validation\ValidationException
     */
    public function changePassword(Request $request)
    {

        if ($request->isMethod('post')) {
            Validator::extend('old_password', function ($attribute, $value, $parameters, $validator) {
                return Hash::check($value, current($parameters));
            });
            $messages = [
                'old_password.old_password' => 'Old passord doesn\'t match!',

            ];

            $user = auth()->user();
            //validate form
            $this->validate($request, [
                'old_password' => 'required|min:6|max:50|old_password:' . $user->password,
                'password' => 'required|confirmed|min:6|max:50',
            ], $messages);

            $user->password = bcrypt($request->get('password'));
            $user->save();
            Auth::logout();
            return redirect()->route('login')->with('success', 'Password successfully change. Login now :)');
        }
        return view('backend.user.change_password');
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::rightJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->leftJoin('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.role_id', '<>', AppHelper::USER_ADMIN)
            ->select('users.*', 'roles.name as role')
            ->get();

        return view('backend.user.list', compact('users'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $roles = Role::where('id', '<>', AppHelper::USER_ADMIN)->pluck('name', 'id');
        $user = null;
        $role = null;
        return view('backend.user.add', compact('roles', 'user', 'role'));
    }

    public function message(Request $request)
    {
        $type = (!empty($request->type)) ? $request->type : 'sms';
        $data = $this->getUsers();
        $sections = $data['sections'];
        unset($data['sections']);
        // echo "<pre>";print_r($data);die;

        return view('backend.user.message', compact('data', 'sections','type'));
    }

    public function storemessage(Request $request)
    {
        ini_set('max_execution_time', 1800);
        $data = $request->all();
        $message_type = (!empty($request->message_category)) ? $request->message_category : '';
        if (!isset($data['section']) && !isset($data['students']) && !isset($data['users'])) {
            return redirect()->back()
                ->with("error", 'Please select any one of the class or student or staff');
        }
        
        try {
            $this->processmessage($data);    

            return redirect()->route('message',$message_type)->with("success", "Wonderful, message sent successfully!");
        } catch (\Exception $e) {
            Log::error($e);
            return redirect()->route('message',$message_type)->with("error", 'Oops!, I lost you for a moment. Can you please try again?');
        }
    }

    /**
     * Show the list of sent messages.
     *
     * @return \Illuminate\Http\Response
     */
    public function sentMessages(Request $request)
    {
        $type = (!empty($request->type)) ? $request->type : 'sms';
        if($type == 'voice') {
            $message_type = 'Voice Call';
            
        } else {
            $message_type = 'General';
            
        }
        $all_message_notifications = MessageNotification::select('id', 'message', 'message_type', 'created_at')->where('message_type',$message_type)->orderBy('id', 'desc')->get()->toArray();
        $view = 'backend.user.sentMessages';
        return view($view, compact('all_message_notifications','type'));
    }

        public function getMessageDetails(Request $request)
        {   
            $message_id = (!empty($request->message)) ? decrypt($request->message) : '';
            $type = (!empty($request->type)) ? decrypt($request->type) : '';
            if($type == 'voice') {
                $message = voiceLog::where('message_id',$message_id)->with('message')->orderBy('id', 'desc')->get();
                $data = [
                    'type'         =>  'Voice Call',
                    'created_at'    => (!empty($message[0]->created_at)) ? $message[0]->created_at : '',
                    'message'       =>  (!empty($message[0]->message->message)) ? $message[0]->message->message : '',
                    'records'       => $message
                ];
                $view = 'backend.partial.voiceMessagedetail';
            } else {
                $message = smsLog::where('message_id',$message_id)->with('message')->orderBy('id', 'desc')->get();
                $data = [
                    'type'         =>  'General',
                    'created_at'    => (!empty($message[0]->created_at)) ? $message[0]->created_at : '',
                    'message'       =>  (!empty($message[0]->message->message)) ? $message[0]->message->message : '',
                    'records'       => $message
                ];
                $view = 'backend.partial.smsMessagedetail';
            }
            
            return json_encode( [   
                'message'      => view($view, compact('data'))->render(),
            ]);
        }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $this->validate(
            $request, [
                'name' => 'required|min:5|max:255',
                'email' => 'email|max:255|unique:users,email',
                'username' => 'required|min:5|max:255|unique:users,username',
                'password' => 'required|min:6|max:50',
                'phone_no' => 'nullable|max:15',
                'role_id' => 'required|numeric',

            ]
        );

        $data = $request->all();

        if ($data['role_id'] == AppHelper::USER_ADMIN) {
            return redirect()->route('user.create')->with("error", 'Do not mess with the system!!!');

        }

        DB::beginTransaction();
        try {
            //now create user
            $user = User::create(
                [
                    'name' => $data['name'],
                    'username' => $request->get('username'),
                    'email' => $data['email'],
                    'phone_no' => isset($data['phone_no']) ? $data['phone_no'] : '',
                    'password' => bcrypt($request->get('password')),
                    'remember_token' => null,
                ]
            );
            //now assign the user to role
            UserRole::create(
                [
                    'user_id' => $user->id,
                    'role_id' => $data['role_id']
                ]
            );

            DB::commit();


            //now notify the admins about this record
            $msg = $data['name'] . " user added by " . auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            return redirect()->route('user.create')->with('success', 'User added!');


        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
            return $message;
            return redirect()->route('user.create')->with("error", $message);
        }

        return redirect()->route('user.create');


    }


    /**
     * Display the specified resource.
     *
     * @param \App\Item $item
     * @return \Illuminate\Http\Response
     */
//    public function show($id)
//    {
//        $user = User::rightJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
//            ->where('user_roles.role_id', '<>', AppHelper::USER_ADMIN)
//            ->where('users.id', $id)
//            ->first();
//        if(!$user){
//            abort(404);
//        }
//
//        return view('backend.user.view', compact('teacher'));
//
//
//    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Item $item
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

        $user = User::rightJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.role_id', '<>', AppHelper::USER_ADMIN)
            ->where('users.id', $id)
            ->select('users.*', 'user_roles.role_id')
            ->first();

        if (!$user) {
            abort(404);
        }


        $roles = Role::where('id', '<>', AppHelper::USER_ADMIN)->pluck('name', 'id');
        $role = $user->role_id;

        return view('backend.user.add', compact('user', 'roles', 'role'));

    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Item $item
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = User::rightJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.role_id', '<>', AppHelper::USER_ADMIN)
            ->where('users.id', $id)
            ->select('users.*', 'user_roles.role_id')
            ->first();

        if (!$user) {
            abort(404);
        }
        //validate form
        $this->validate($request, [
        'name' => 'required|min:5|max:255',
        'email' => 'email|max:255|unique:users,email,'.$user->id,
        'role_id' => 'required|numeric',
         'phone_no' => 'nullable|max:15',

        ]);

        if ($request->get('role_id') == AppHelper::USER_ADMIN) {
            return redirect()->route('user.index')->with("error", 'Do not mess with the system!!!');

        }


        $data['name'] = $request->get('name');
        $data['email'] = $request->get('email');
        $data['phone_no'] = $request->get('phone_no');
        $user->fill($data);
        $user->save();

        if ($user->role_id != $request->get('role_id')) {
            DB::table('user_roles')->where('user_id', $user->id)->update([
                'role_id' => $request->get('role_id'),
                'updated_at' => Carbon::now(),
                'updated_by' => auth()->user()->id
            ]);
        }

        return redirect()->route('user.index')->with('success', 'User updated!');


    }


    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Item $item
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        $user = User::findOrFail($id);

        $userRole = UserRole::where('user_id', $user->id)->first();

        if ($userRole && $userRole->role_id == AppHelper::USER_ADMIN) {
            return redirect()->route('user.index')->with('error', 'Don not mess with the system');

        }

        $message = "Something went wrong!";
        DB::beginTransaction();
        try {

            DB::table('user_roles')->where('user_id', $user->id)->update([
                'deleted_by' => auth()->user()->id,
                'deleted_at' => Carbon::now()
            ]);

            //delink related child record
            //employee and student
            $user->employee()->update(['user_id' => null]);
            $user->student()->update(['user_id' => null]);

            $user->delete();

            DB::commit();

            //now notify the admins about this record
            $msg = $user->name . " user deleted by " . auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            //flash this user permission cache
            Cache::forget('permission' . auth()->user()->id);
            Cache::forget('roles' . auth()->user()->id);

            return redirect()->route('user.index')->with('success', 'User deleted.');


        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
        }

        return redirect()->route('user.index')->with("error", $message);


    }

    /**
     * status change
     * @return mixed
     */
    public function changeStatus(Request $request, $id = 0)
    {

        $user = User::findOrFail($id);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }

        $userRole = UserRole::where('user_id', $user->id)->first();

        if ($userRole && $userRole->role_id == AppHelper::USER_ADMIN) {
            return [
                'success' => false,
                'message' => 'Don not mess with the system!'
            ];

        }

        $user->status = (string)$request->get('status');
        $user->force_logout = (int)$request->get('status') ? 0 : 1;
        $user->save();

        //flash this user permission cache
        Cache::forget('permission' . auth()->user()->id);
        Cache::forget('roles' . auth()->user()->id);

        return [
            'success' => true,
            'message' => 'Status updated.'
        ];

    }


    /**
     * List permissions
     * @param $request
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function permissionlist(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {

            $this->validate($request, [
                'hiddenId' => 'required|integer',
            ]);

            $id = $request->get('hiddenId', 0);
            $permission = Permission::findOrFail($id);

            //check if this role has active user
            $roles = Permission::with(['roles' => function ($query) {
                $query->select('permission_id');
            }])->count();

            if ($roles) {
                return redirect()->route('user.permission_index')->with('error', 'Permission has roles, So can\'t delete it!');
            }


            $message = "Something went wrong!";
            DB::beginTransaction();
            try {

                DB::table('roles_permissions')->where('permission_id', $id)->update([
                    'deleted_by' => auth()->user()->id,
                    'deleted_at' => Carbon::now()
                ]);

                $permission->delete();

                DB::commit();

                //now notify the admins about this record
                $msg = $permission->name . " permission deleted by " . auth()->user()->name;
                $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
                // Notification end

                //flush the permission cache also other cache
                Cache::flush();

                return redirect()->route('user.permission_index')->with('success', 'Permission deleted!');

            } catch (\Exception $e) {
                DB::rollback();
                $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
            }


            return redirect()->route('user.role_index')->with('error', $message);
        }

        //for get request
        $permissions = Permission::get();

        return view('backend.permission.list', compact('permissions'));
    }

    /**
     * permission create
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function permissionCreate(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'slug' => 'required|min:4|max:255|unique:permissions',
                'name' => 'required|min:4|max:255',
                'group' => 'required|min:4|max:255',
            ]);


            $permission = Permission::create([
                'slug' => $request->get('slug'),
                'name' => $request->get('name'),
                'group' => $request->get('group'),
            ]);
            $addedAdmin = DB::table('roles_permissions')->insert(array(
                'role_id' => 1,
                'permission_id' => $permission->id,
            ));

            //now notify the admins about this record
            $msg = $request->get('name') . " permission created by " . auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            //flush the permission cache also other cache
            Cache::flush();

            return redirect()->route('user.permission_index')->with('success', 'Permission Created.');
        }
        return view('backend.permission.add');
    }


    /**
     * update permission
     * @return mixed
     */
    public function updatePermission(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $userRole = UserRole::where('user_id', $user->id)->first();

        if ($userRole && $userRole->role_id == AppHelper::USER_ADMIN) {
            return redirect()->route('user.index')->with('error', 'Don not mess with the system.');

        }

        //for save on POST request
        if ($request->isMethod('post')) {

            $permissionList = $request->get('permissions');

            $message = "Something went wrong!";
            DB::beginTransaction();
            try {

                //now delete previous permissions
                DB::table('users_permissions')->where('user_id', $user->id)->update([
                    'deleted_by' => auth()->user()->id,
                    'deleted_at' => Carbon::now()
                ]);

                //then insert new permissions
                $userPermissions = $this->proccessInputPermissions($permissionList, 'user_id', $user->id, auth()->user()->id);
                DB::table('users_permissions')->insert($userPermissions);

                DB::commit();

                //now notify the admins about this record
                $msg = $user->name . " user permission updated by " . auth()->user()->name;
                $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
                // Notification end

                //flush the permission cache also other cache
                Cache::flush();

                return redirect()->route('user.index')->with('success', 'User Permission Updated.');

            } catch (\Exception $e) {
                DB::rollback();
                $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
            }

            return redirect()->route('user.index')->with('error', $message);
        }


        $userPermissions = DB::table('users_permissions')->where('user_id', $user->id)
            ->whereNull('deleted_at')->pluck('permission_id')->toArray();

        $permissions = Permission::select('id', 'name', 'group')->whereNotIn('group', ['Admin Only', 'Common'])->orderBy('group', 'asc')->get();

        $permissionList = $this->formatPermissions($permissions, $userPermissions);

        return view('backend.user.permission', compact('permissionList', 'user'));

    }


    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Illuminate\Validation\ValidationException
     */
    public function roles(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {

            $this->validate($request, [
                'hiddenId' => 'required|integer',
            ]);

            $id = $request->get('hiddenId', 0);
            $role = Role::findOrFail($id);

            if (!$role->deletable) {
                return redirect()->route('user.role_index')->with('error', 'You can\'t delete this role?');
            }

            //check if this role has active user
            $users = UserRole::where('role_id', $role->id)->count();
            if ($users) {
                return redirect()->route('user.role_index')->with('error', 'Role has users, So can\'t delete it!');
            }


            $message = "Something went wrong!";
            DB::beginTransaction();
            try {

                DB::table('roles_permissions')->where('role_id', $id)->update([
                    'deleted_by' => auth()->user()->id,
                    'deleted_at' => Carbon::now()
                ]);

                $role->delete();

                DB::commit();

                //now notify the admins about this record
                $msg = $role->name . " role deleted by " . auth()->user()->name;
                $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
                // Notification end

                //flush the permission cache also other cache
                Cache::flush();

                return redirect()->route('user.role_index')->with('success', 'Role deleted!');

            } catch (\Exception $e) {
                DB::rollback();
                $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
            }


            return redirect()->route('user.role_index')->with('error', $message);
        }

        //for get request
        $roles = Role::get();

        return view('backend.role.list', compact('roles'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Illuminate\Validation\ValidationException
     */
    public function roleCreate(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'name' => 'required|min:4|max:255|unique:roles',
            ]);


            $role = Role::create([
                'name' => $request->get('name')
            ]);


            $permissionList = $request->get('permissions');

            if (count($permissionList)) {

                $rolePermissions = $this->proccessInputPermissions($permissionList, 'role_id', $role->id, auth()->user()->id);

                DB::table('roles_permissions')->insert($rolePermissions);

            }

            //now notify the admins about this record
            $msg = $request->get('name') . " role created by " . auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            //flush the permission cache also other cache
            Cache::flush();

            return redirect()->route('user.role_index')->with('success', 'Role Created.');
        }


        $permissions = Permission::select('id', 'name', 'group')->whereNotIn('group', ['Admin Only', 'Common'])->orderBy('group', 'asc')->get();

        $permissionList = $this->formatPermissions($permissions);
        $role = null;

        return view('backend.role.add', compact('permissionList', 'role'));
    }

    /**
     * role create
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function roleUpdate(Request $request, $id)
    {
        //check if it is admin role then, reject from modify
        if ($id == AppHelper::USER_ADMIN) {
            return redirect()->route('user.role_index')->with('error', 'Don not mess with the system');
        }

        //collect role info and permissions
        $role = Role::findOrFail($id);

        //for save on POST request
        if ($request->isMethod('post')) {

            $permissionList = $request->get('permissions');

            $message = "Something went wrong!";
            DB::beginTransaction();
            try {

                //now delete previous permissions
                DB::table('roles_permissions')->where('role_id', $id)->update([
                    'deleted_by' => auth()->user()->id,
                    'deleted_at' => Carbon::now()
                ]);

                //then insert new permissions
                $rolePermissions = $this->proccessInputPermissions($permissionList, 'role_id', $role->id, auth()->user()->id);
                DB::table('roles_permissions')->insert($rolePermissions);

                DB::commit();

                //now notify the admins about this record
                $msg = $role->name . " role updated by " . auth()->user()->name;
                $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
                // Notification end

                //flush the permission cache also other cache
                Cache::flush();

                return redirect()->route('user.role_index')->with('success', 'Role Permission Updated.');

            } catch (\Exception $e) {
                DB::rollback();
                $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
            }

            return redirect()->route('user.role_index')->with('error', $message);
        }


        $rolePermissions = DB::table('roles_permissions')->where('role_id', $role->id)->whereNull('deleted_at')->pluck('permission_id')->toArray();

        $permissions = Permission::select('id', 'name', 'group')->whereNotIn('group', ['Admin Only', 'Common'])->orderBy('group', 'asc')->get();

        $permissionList = $this->formatPermissions($permissions, $rolePermissions);

        return view('backend.role.add', compact('permissionList', 'role'));
    }

    private function formatPermissions($permissions, $rolePermissions = null)
    {
        //now build the structure to view on blade
        //$permissionList[group_name][module_name][permission_verb][permission_ids]
        $permissionList = [];

        foreach ($permissions as $permission) {

            $namePart = preg_split("/\s+(?=\S*+$)/", $permission->name);
            $moduleName = $namePart[0];
            $verb = $namePart[1];

            $permissionList[$permission->group][$moduleName][$verb]['ids'][] = $permission->id;

            if ($rolePermissions) {
                $permissionList[$permission->group][$moduleName][$verb]['checked'] = in_array($permission->id, $rolePermissions) ? 1 : 0;

            } else {
                $permissionList[$permission->group][$moduleName][$verb]['checked'] = 0;

            }
        }

        return $permissionList;

    }

    private function proccessInputPermissions($permissionList, $type, $roleOrUserId, $loggedInUser)
    {

        $rolePermissions = [];

        $now = Carbon::now(env('APP_TIMEZONE', 'Asia/Dhaka'));

        if (count($permissionList)) {

            foreach ($permissionList as $permissions) {
                $permissions = explode(',', $permissions);
                foreach ($permissions as $permission) {
                    $rolePermissions[] = [
                        $type => $roleOrUserId,
                        'permission_id' => $permission,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'created_by' => $loggedInUser,
                        'updated_by' => $loggedInUser,
                    ];
                }
            }


        }

        //now add common permissions
        $permissions = Permission::select('id')->where('group', 'Common')->get();
        foreach ($permissions as $permission) {
            $rolePermissions[] = [
                $type => $roleOrUserId,
                'permission_id' => $permission->id,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $loggedInUser,
                'updated_by' => $loggedInUser,
            ];
        }

        return $rolePermissions;
    }

   /**
    *  Dashboard data helper methods
    */
   private function getAcademicYearForDashboard() {
       $academicYearId = 0;
       if (AppHelper::getInstituteCategory() == 'college') {
           $academicYearInfo = AcademicYear::where('status', AppHelper::ACTIVE)
               ->where('title', env('DASHBOARD_STUDENT_COUNT_DEFAULT_ACADEMIC_YEAR',date('Y')))->first();
           if ($academicYearInfo) {
               $academicYearId = $academicYearInfo->id;
           }
       } else {
           $academicYearId = AppHelper::getAcademicYear();
       }

       return $academicYearId;
   }

   private function getStatisticData() {
       $teachers = Cache::rememberForever('teacherCount' , function () {
           return   Employee::where('status', AppHelper::ACTIVE)->where('role_id', AppHelper::EMP_TEACHER)->count();
       });

       $employee = Cache::rememberForever('employeeCount' , function () {
           return Employee::where('status', AppHelper::ACTIVE)->count();
       });
       $academicYearId = $this->getAcademicYearForDashboard();
       $students = Cache::rememberForever('studentCount' , function () use($academicYearId) {
           return Registration::where('status', AppHelper::ACTIVE)
               ->where('academic_year_id', $academicYearId)
               ->count();
       });
       $subjects = Cache::rememberForever('SubjectCount' , function () {
           return Subject::where('status', AppHelper::ACTIVE)->count();
       });

       return [$teachers, $employee, $students, $subjects];
   }

   private function getSmsChartData() {
       $smsSend = Cache::rememberForever('smsChartData' , function () {
           $allMonthSmsSended =  smsLog::selectRaw('count(id) as send,MONTH(created_at) as month')
               ->whereYear('created_at', date('Y'))
               ->groupby('month')
               ->get();

           return $allMonthSmsSended;
       });

       $smsSend = $smsSend->reduce(function ($smsSend, $record){
           $smsSend[$record->month] = $record->send;
           return $smsSend;
       });

       $monthWiseSms['Jan'] = $smsSend[1] ?? 0;
       $monthWiseSms['Feb'] = $smsSend[2] ?? 0;
       $monthWiseSms['Mar'] = $smsSend[3] ?? 0;
       $monthWiseSms['Apr'] = $smsSend[4] ?? 0;
       $monthWiseSms['May'] = $smsSend[5] ?? 0;
       $monthWiseSms['Jun'] = $smsSend[6] ?? 0;
       $monthWiseSms['Jul'] = $smsSend[7] ?? 0;
       $monthWiseSms['Aug'] = $smsSend[8] ?? 0;
       $monthWiseSms['Sep'] = $smsSend[9] ?? 0;
       $monthWiseSms['Oct'] = $smsSend[10] ?? 0;
       $monthWiseSms['Nov'] = $smsSend[11] ?? 0;
       $monthWiseSms['Dec'] = $smsSend[12] ?? 0;

       return $monthWiseSms;
   }

   private function getClassWiseTodayAttendanceCount() {
       $academicYearId = $this->getAcademicYearForDashboard();
       $iclasses = Cache::rememberForever('student_attendance_count' , function () use($academicYearId) {
           return  IClass::where('status', AppHelper::ACTIVE)
               ->with(['attendance' => function ($query) use ($academicYearId) {
                   $query->selectRaw('class_id,present,count(registration_id) as total')
                       ->where('academic_year_id', $academicYearId)
                       ->whereDate('attendance_date', date('Y-m-d'))
//                        ->whereDate('attendance_date', '2019-03-02')
                       ->groupBy('class_id', 'present');
               }])
			   ->select('id', 'name')
			   ->orderBy('order', 'asc')
               ->get();
       });

       $attendanceChartPresentData = [];
       $attendanceChartAbsentData = [];
       foreach ($iclasses as $iclass) {
           $attendanceChartPresentData[$iclass->name] = 0;
           $attendanceChartAbsentData[$iclass->name] = 0;
           foreach ($iclass->attendance as $attendanceSummary) {
               if ($attendanceSummary->present == "Present") {
                   $attendanceChartPresentData[$iclass->name] = $attendanceSummary->total;
               } else {
                   $attendanceChartAbsentData[$iclass->name] = $attendanceSummary->total;

               }
           }
       }

       return [$attendanceChartPresentData, $attendanceChartAbsentData];
   }

   private function getClassWiseStudentCount()
   {
       $academicYearId = $this->getAcademicYearForDashboard();
       $studentCount = Cache::rememberForever('student_count_by_class', function () use ($academicYearId) {
         return  IClass::where('status', AppHelper::ACTIVE)
               ->with(['student' => function ($query) use ($academicYearId) {
                   $query->where('status', AppHelper::ACTIVE)
                       ->where('academic_year_id', $academicYearId)
                       ->selectRaw('class_id, count(*) as total')
                       ->groupBy('class_id');
               }])
			   ->select('id', 'name')
			   ->orderBy('order', 'asc')
               ->get();
       });

       return $studentCount;
   }

   private function getSectionWiseStudentCountForSpecificClasses() {
       $academicYearId = $this->getAcademicYearForDashboard();
       $selectedClassNumericValues = explode(',', env('DASHBOARD_SECTION_STUDENT_CLASS_CODE', '90,91,92,100,101,102'));
       $selectedClasses = Cache::rememberForever('selected_classes_4_dashboard', function () use ($selectedClassNumericValues) {
          return IClass::where('status', AppHelper::ACTIVE)
               ->whereIn('numeric_value', $selectedClassNumericValues)->pluck('id');
       });

       $sectionStudentCount = Cache::rememberForever('student_count_by_section', function () use ($selectedClasses, $academicYearId) {
          return  Section::where('status', AppHelper::ACTIVE)
               ->whereIn('class_id', $selectedClasses)
               ->with(['student' => function ($query) use ($academicYearId) {
                   $query->where('status', AppHelper::ACTIVE)
                       ->where('academic_year_id', $academicYearId)
                       ->selectRaw('section_id, count(*) as total')
                       ->groupBy('section_id');
               }])
               ->with(['class' => function ($query) {
                   $query->select('id', 'name');
               }])
               ->select('id', 'name', 'class_id')
               ->orderBy('class_id', 'asc')
               ->orderBy('name', 'asc')
               ->get();
       });

       return $sectionStudentCount;

   }
}
