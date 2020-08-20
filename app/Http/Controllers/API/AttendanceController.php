<?php

namespace App\Http\Controllers\API;

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
use App\AppMeta;
use App\StudentAttendance;
use App\EmployeeAttendance;
use DateTime;
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
use App\Template;
use JWTAuth;
use stdClass;
use Tymon\JWTAuth\Exceptions\JWTException;

class AttendanceController extends Controller
{
    public $successStatus = 200;

    protected $hasher;

    public function __construct(HasherContract $hasher)
    {
        $this->hasher = $hasher;
    }

    public function getAttendanceDetails(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $roleId = $user->role->role_id;
        $userId = $user->id;
        $attendance_date = $request->attendance_date;
        // return $this->getStudentAttendance($userId,$attendance_date);

        if ($roleId == AppHelper::USER_TEACHER) {
            return response()->json(['success' => false, 'message' => 'This token not belongs to student'], 500);

            //  return $this->getTeacherProfile($userId);
        }

        if ($roleId == AppHelper::USER_STUDENT) {
            return $this->getStudentAttendance($userId, $attendance_date);
        }
        return response()->json(['success' => false, 'message' => 'This token not belongs to student or teacher'], 500);
        die;
    }

    public function getStudentAttendance($userId, $attendence_date)
    {
        if (AppHelper::getInstituteCategory() == 'college') {
            $rules['academic_year'] = 'required|integer';
        }

        $student_id = Student::where('user_id', $userId)->first();
        $registrations = Registration::where('student_id', $student_id->id)->first();
        $month = Carbon::createFromFormat('!m/Y', $attendence_date)->timezone(env('APP_TIMEZONE', 'Asia/Kolkata'));
        $classId = $registrations->class_id;
        $sectionId = $registrations->section_id;
        if (AppHelper::getInstituteCategory() == 'college') {
            $academicYearId = $request->get('academic_year', 0);
        } else {
            $academicYearId = AppHelper::getAcademicYear();
        }
        $monthStart = $month->startOfMonth()->copy();
		$monthEnd = $month->endOfMonth()->copy();

        $students = Registration::where('status', AppHelper::ACTIVE)
            ->where('student_id', $student_id->id)
            ->with(['info' => function ($query) {
                $query->select('name', 'id');
            }])
            ->select('id', 'student_id', 'roll_no', 'regi_no')
            ->orderBy('roll_no', 'asc')
            ->get();
        $studentIds = $students->pluck('id');
        $attendanceData = \App\StudentAttendance::select('registration_id', 'attendance_date', 'present', 'status')
            ->where('registration_id', $registrations->id)
            ->whereDate('attendance_date', '>=', $monthStart->format('Y-m-d'))
            ->whereDate('attendance_date', '<=', $monthEnd->format('Y-m-d'))
            ->get()
            ->reduce(function ($attendanceData, $attendance) {
                $inLate = 0;
                if (strpos($attendance->status, '1') !== false) {
                    $inLate = 1;
                }
                $attendanceData[$attendance->registration_id][$attendance->getOriginal('attendance_date')] = [
                    'present' => $attendance->getOriginal('present'),
                    'inLate' => $inLate
                ];

                return $attendanceData;
            });

        $wekends = AppHelper::getAppSettings('weekends');
        if ($wekends) {
            $wekends = json_decode($wekends);
        }
        //pull holidays
        $calendarData = \App\AcademicCalendar::where(function ($q) {
            $q->where('is_holiday', '1')
                ->orWhere('is_exam', '1');
        })
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereDate('date_from', '>=', $monthStart->format('Y-m-d'))
                    ->whereDate('date_from', '<=', $monthEnd->format('Y-m-d'))
                    ->orWhere(function ($q) use ($monthStart, $monthEnd) {
                        $q->whereDate('date_upto', '>=', $monthStart->format('Y-m-d'))
                            ->whereDate('date_upto', '<=', $monthEnd->format('Y-m-d'));
                    });
            })
            ->select('date_from', 'date_upto', 'is_holiday', 'is_exam', 'class_ids')
            ->get()
            ->reduce(function ($calendarData, $calendar) use ($monthEnd, $monthStart, $wekends) {

                $startDate = $calendar->date_from;
                $endDate = $calendar->date_upto;
                if ($calendar->date_upto->gt($monthEnd)) {
                    $endDate = $monthEnd;
                }

                if ($calendar->date_from->lt($monthStart)) {
                    $startDate = $monthStart;
                }

                $cladendarDateRange = AppHelper::generateDateRangeForReport($startDate, $endDate, true, $wekends, true);
                foreach ($cladendarDateRange as $date => $value) {
                    $symbols = 'H';
                    if ($calendar->is_exam == 1) {
                        $symbols = 'E';
                    }
                    $calendarData[$date] = $symbols;
                }
                return $calendarData;
            });


        $monthDates = AppHelper::generateDateRangeForReport($monthStart, $monthEnd, true, $wekends);


        $filters = [];
        if (AppHelper::getInstituteCategory() == 'college') {
            $academicYearInfo = AcademicYear::where('id', $academicYearId)->first();
            $filters[] = "Academic year: " . $academicYearInfo->title;
        }
        $section = Section::where('id', $sectionId)
            ->with(['class' => function ($q) {
                $q->select('name', 'id');
            }])
            ->select('id', 'class_id', 'name')
            ->first();

        $filters[] = "Class: " . $section->class->name;
        $filters[] = "Section: " . $section->name;

        return response()->json([
            'monthDates' => $monthDates,
            'students' => $students,
            'attendanceData' => $attendanceData,
            'calendarData' => $calendarData,
        ]);


    }

    public function getclassdata(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $date = date('Y-m-d');
        $class = IClass::leftjoin('sections', 'sections.class_id', '=', 'i_classes.id')->leftjoin('registrations', 'registrations.section_id', 'sections.id')->
        select(DB::raw('CONCAT(i_classes.name , " ", sections.name) as name'), 'i_classes.id as classid', 'sections.id as sectionid', DB::raw("(SELECT count(*) FROM registrations
                         WHERE registrations.section_id = sections.id
                       ) as students"))
            ->distinct()->get();
        if (!empty($class)) {
            $i = 0;
            foreach ($class as $data) {
                $findattend = DB::table('student_attendances')->leftjoin('registrations', 'student_attendances.registration_id', '=', 'registrations.id')->where('registrations.section_id', $data->sectionid)->where('student_attendances.present', '1')->whereDate('attendance_date', $date)->count();
                $class[$i]["present"] = $findattend;
                $i++;
            }
        }
        return response()->json($class);
    }

    public function students(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $date = date('Y-m-d');
        $input = $request->all();
        $json = array();
        $i = 0;
        $section = Section::where('id', $input['section_id'])->select('id')->first();
        if (!empty($section)) {
            $json = DB::table('student_attendances')->leftjoin('registrations', 'student_attendances.registration_id', '=', 'registrations.id')->leftjoin('students', 'students.id', 'registrations.student_id')->where('registrations.section_id', $section->id)->whereDate('attendance_date', $date)->select('students.name as student_name', 'students.id', 'student_attendances.present')->get();
            if (empty($json->toArray())) {
                $json = Registration::where('registrations.section_id', $section->id)->leftjoin('students', 'students.id', 'registrations.student_id')->select('students.name as student_name', 'students.id')->get();
                if (!empty($json)) {
                    $i = 0;
                    foreach ($json as $value) {
                        $json[$i]['present'] = '';
                        $i++;
                    }
                }

            }
        }
        return response()->json($json);
    }

    public function attendancestudents(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $input = $request->all();
        $absentIds = [];
        if(isset($input['attendance_date'])) {
            $attendance_date = date("Y-m-d", strtotime($input['attendance_date']));
            $date = date('Y-m-d h:i:s', strtotime($input['attendance_date']));
        }else{
            $attendance_date = date('Y-m-d');
            $date = date('Y-m-d h:i:s');
        }
        // $date= date('2019-05-13');
        $json = array();
        $i = 0;
        $dateTimeNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Kolkata'));
        $section = Section::where('id', $input['section_id'])->select('id')->first();
        if (!empty($section)) {
            $data = Registration::where('section_id', $input['section_id'])->select('id', 'student_id', 'academic_year_id', 'class_id', 'shift')->get();
            foreach ($data as $value) {
                $settings = AppMeta::select('meta_key', 'meta_value')->get();
                $metas = [];
                foreach ($settings as $setting) {
                    $metas[$setting->meta_key] = $setting->meta_value;
                }
                if (isset($metas['shift_data'])) {
                    $shiftData = json_decode($metas['shift_data'], true);
                    $formatedShiftData = [];
                    foreach ($shiftData as $shift => $times) {
                        $formatedShiftData[$shift] = [
                            'start' => Carbon::parse($times['start'])->format('h:i a'),
                            'end' => Carbon::parse($times['end'])->format('h:i a')
                        ];
                    }
                    $metas['shift_data'] = $formatedShiftData;
                }
                $start = $metas['shift_data'][$value->shift]['start'];
                $end = $metas['shift_data'][$value->shift]['end'];
                $inTime = date('Y-m-d h:i:s', strtotime($attendance_date . ' ' . $start));
                $outTime = date('Y-m-d h:i:s', strtotime($attendance_date . ' ' . $end));
                $timeDiff = '08:00:00';

                if (in_array($value->student_id, $input['ids'])) {
                    $present = '0';
                    $absentIds[] = $value->id;
                } else {
                    $present = '1';
                }
                $attendanceas = array(
                    "academic_year_id" => $value->academic_year_id,
                    "class_id" => $value->class_id,
                    "registration_id" => $value->id,
                    "attendance_date" => $attendance_date,
                    "in_time" => $inTime,
                    "out_time" => $outTime,
                    "staying_hour" => $timeDiff,
                    "status" => '1',
                    "present" => $present,
                    "created_at" => $date,
                    "created_by" => auth()->user()->id,
                );
                $getdata = StudentAttendance::where('registration_id', $value->id)->whereDate('attendance_date', $attendance_date)->first();
                if (empty($getdata)) {
                    StudentAttendance::insert($attendanceas);
                } 
                $i++;
            }
            $message = "Attendance saved successfully.";
            $sendNotification = AppHelper::getAppSettings('student_attendance_notification');
            if($sendNotification != "0") {
                if($sendNotification == "1"){
                    //then send sms notification

                    //get sms gateway information
                    $gateway = AppMeta::where('id', AppHelper::getAppSettings('student_attendance_gateway'))->first();
                    if(!$gateway){
                        redirect()->route('student_attendance.create')->with("warning",$message." But SMS Gateway not setup!");
                    }

                    //get sms template information
                    $template = Template::where('id', AppHelper::getAppSettings('student_attendance_template'))->first();
                    if(!$template){
                        redirect()->route('student_attendance.create')->with("warning",$message." But SMS template not setup!");
                    }

                    $res = AppHelper::sendAbsentNotificationForStudentViaSMS($absentIds, $attendance_date);

                }
            }
            return response()->json(['success' => true, 'message' => 'Attendance saved successfully.'], 200);
            die;
        }
        return response()->json(['success' => false, 'message' => 'Invalid details.'], 200);
        die;
    }


    public function updateStudentsAttendance(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $input = $request->all();
        if(isset($input['attendance_date'])) {
            $attendance_date = date("Y-m-d", strtotime($input['attendance_date']));
            $date = date('Y-m-d h:i:s', strtotime($input['attendance_date']));
        }else{
            $attendance_date = date('Y-m-d');
            $date = date('Y-m-d h:i:s');
        }
        // $date= date('2019-05-13');
        $students = $input['students'];
        $json = array();
        $i = 0;
        
        if (!empty($students)) {
            $updated = 0;
            foreach ($students as $student) {
                $reg = Registration::where('student_id', $student['id'])->select('id', 'student_id', 'academic_year_id', 'class_id', 'shift')->get()->first();
                $attData = StudentAttendance::where('registration_id', $reg->id)->whereDate('attendance_date', $attendance_date)->first();
                if (isset($attData->present) && ($attData->getOriginal('present') != $student['present'])) {
                    StudentAttendance::where('id', $attData->id)->update(['present' => strval($student['present'])]);
                    $updated++;
                }
            }
            return response()->json(['success' => true, 'message' => "$updated attendance entries updated successfully."], 200);
            die;
        }
        return response()->json(['success' => false, 'message' => 'Invalid details.'], 200);
        die;
    }


    public function attendancestaff(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $input = $request->all();
        if(isset($input['attendance_date'])) {
            $attendance_date = date("Y-m-d", strtotime($input['attendance_date']));
            $date = date('Y-m-d h:i:s', strtotime($input['attendance_date']));
        }else{
            $attendance_date = date('Y-m-d');
            $date = date('Y-m-d h:i:s');
        }
        $json = array();
        $i = 0;
        $dateTimeNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Kolkata'));

        $emp_data = Employee::where('id', $input['id'])->select('*')->first();

        if (!empty($emp_data)) {
            $settings = AppMeta::select('meta_key', 'meta_value')->get();
            $metas = [];
            foreach ($settings as $setting) {
                $metas[$setting->meta_key] = $setting->meta_value;
            }
            if (isset($metas['shift_data'])) {
                $shiftData = json_decode($metas['shift_data'], true);
                $formatedShiftData = [];
                foreach ($shiftData as $shift => $times) {
                    $formatedShiftData[$shift] = [
                        'start' => Carbon::parse($times['start'])->format('h:i a'),
                        'end' => Carbon::parse($times['end'])->format('h:i a')
                    ];
                }
                $metas['shift_data'] = $formatedShiftData;
            }
            $start = $metas['shift_data']['Morning']['start'];
            $end = $metas['shift_data']['Morning']['end'];
            $inTime = date('Y-m-d h:i:s', strtotime($attendance_date . ' ' . $start));
            $outTime = date('Y-m-d h:i:s', strtotime($attendance_date . ' ' . $end));

            $attendances[] = [
                "employee_id" => $emp_data->id,
                "attendance_date" => $attendance_date,
                "in_time" => $inTime,
                "out_time" => $outTime,
                "working_hour" => '08:00:00',
                "status" => '',
                "present" => $input['status'],
                "created_at" => $dateTimeNow,
                "created_by" => auth()->user()->id,
            ];

            $getdata =
                EmployeeAttendance::where('employee_id', $emp_data->id)->whereDate('attendance_date', $attendance_date)->first();
            if (empty($getdata)) {
                EmployeeAttendance::insert($attendances);
            } else {
                EmployeeAttendance::where('id', $getdata->id)->update(['present' => $input['status']]);
            }
            return response()->json(['success' => true, 'message' =>
                'Attendance saved successfully.'], 200);
            die;
        }
        return response()->json(['success' => false, 'message' => 'Invalid
		details.'], 200);
        die;
    }

    /**
     * @param $class_id
     * @param $section_id
     * @param $acYear
     * @param $attendance_date
     * @param bool $isCount
     * @return mixed
     */
    private function getAttendanceByFilters($class_id, $section_id, $acYear, $attendance_date, $isCount = false) {
        $att_date = Carbon::createFromFormat('d/m/Y',$attendance_date)->toDateString();
        return $attendances = Registration::where('academic_year_id', $acYear)
            ->where('class_id', $class_id)
            ->where('section_id', $section_id)
            ->where('status', AppHelper::ACTIVE)
            ->with(['student' => function ($query) {
                $query->select('name','id');
            }])
            ->whereHas('attendance' , function ($query) use($att_date, $class_id, $acYear) {
                $query->select('id','registration_id')
                    ->where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->whereDate('attendance_date', $att_date);
            })
            ->select('id','regi_no','roll_no','student_id')
            ->orderBy('roll_no','asc')
            ->CountOrGet($isCount);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function getAllClassDetails()
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $today = new DateTime('now');
        $attendance_date = date_format($today, 'd/m/Y');
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }else{
            $acYear = AppHelper::getAcademicYear();
        }
        // Build class list
        $classlist = [];
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->select('name', 'id')->get();

        foreach($classes as $key => $class) {
            $sections = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class->id)
                ->orderBy('name','asc')
                ->select('name', 'id')->get();
            foreach($sections as $key1 => $section) {
                //Now get the student count
                $students = Registration::where('academic_year_id', $acYear)
                    ->where('class_id', $class->id)
                    ->where('section_id', $section->id)
                    ->where('status', AppHelper::ACTIVE)
                    ->count();

                //now fetch attendance data
                if($class->id && $section->id && $acYear && strlen($attendance_date) >= 10) {
                    $att_date = Carbon::createFromFormat('d/m/Y',$attendance_date)->toDateString();
                    $present = Registration::where('academic_year_id', $acYear)
                        ->where('class_id', $class->id)
                        ->where('section_id', $section->id)
                        ->where('status', AppHelper::ACTIVE)
                        ->whereHas('attendanceSingleDay', function($query) use($att_date, $class, $acYear) {
                            $query->select('id')
                                ->where('academic_year_id', $acYear)
                                ->where('class_id', $class->id)
                                ->where('present', AppHelper::ACTIVE)
                                ->whereDate('attendance_date', $att_date);
                        })
                        ->select('id')
                        ->count();
                    $classlist[] = [
                        'class_id' => $class->id,
                        'class_name' => $class->name,
                        'section_id' => $section->id,
                        'section_name' => $section->name,
                        'students' => $students,
                        'present' => $present,
                        'absent' => $students - $present,
                        'recorded' => $this->getAttendanceByFilters($class->id, $section->id, $acYear, $attendance_date, true)
                    ];
                }
            }
        }
        return response()->json($classlist);
    }
}
