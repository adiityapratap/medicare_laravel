<?php

namespace App\Http\Controllers\Backend;

use \stdClass;
use \DateTime;
use App\AcademicYear;
use App\AppMeta;
use App\AttendanceFileQueue;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Jobs\PushStudentAbsentJob;
use App\Registration;
use App\Section;
use App\StudentAttendance;
use App\Template;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Exception;
use Log;
use App\Subject;

class StudentAttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //if student id present  that means come from student profile
        // show fetch the attendance and send json response
        if ($request->ajax() && $request->query->get('student_id', 0)) {
            $id = $request->query->get('student_id', 0);
            $attendances = StudentAttendance::where('registration_id', $id)
                ->select('attendance_date', 'present', 'registration_id')
                ->orderBy('attendance_date', 'asc')
                ->get();
            return response()->json($attendances);

        }

        // get query parameter for filter the fetch
        $class_id = $request->query->get('class', 0);
        $section_id = $request->query->get('section', 0);
        $session_id = $request->query->get('session_id', 0);
        $subject_id = $request->query->get('subject_id', 0);
        $acYear = $request->query->get('academic_year', 0);
        $attendance_date = $request->query->get('attendance_date', date('d/m/Y'));


        //if its college then have to get those academic years
        $academic_years = [];
        if (AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        } else {

            $acYear = $request->query->get('academic_year', AppHelper::getAcademicYear());
        }


        //if its a ajax request that means come from attendance add exists checker
        if ($request->ajax()) {
            $attendances = $this->getAttendanceByFilters($class_id, $section_id, $acYear, $attendance_date, true,$session_id,$subject_id);
            return response()->json($attendances);
        }


        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')
            ->pluck('name', 'id');
        $sections = [];
        $subjects = [];
        $sessions = [];

        //now fetch attendance data
        $attendances = [];
        if ($class_id && $section_id && $acYear && strlen($attendance_date) >= 10) {
            $att_date = Carbon::createFromFormat('d/m/Y', $attendance_date)->toDateString();
            $attendances = Registration::where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->where('section_id', $section_id)
                ->where('status', AppHelper::ACTIVE)
                ->with([
                    'student' => function ($query) {
                        $query->select('name', 'id');
                    }
                ])
                ->with([
                    'attendanceSingleDay' => function ($query) use ($att_date, $class_id, $acYear,$session_id,$subject_id) {
                        $query->select('id', 'present', 'registration_id', 'in_time', 'out_time', 'staying_hour')
                            ->where('academic_year_id', $acYear)
                            ->where('class_id', $class_id)
                            ->whereDate('attendance_date', $att_date);
                            if(!empty($subject_id)) {
                                $query->where('subject', $subject_id);
                            }
                            if(!empty($session_id)) {
                                $query->where('session', $session_id);
                            }
                    }
                ])
                ->whereHas('attendance', function ($query) use ($att_date, $class_id, $acYear,$session_id,$subject_id) {
                    $query->select('id', 'registration_id')
                        ->where('academic_year_id', $acYear)
                        ->where('class_id', $class_id)
                        ->whereDate('attendance_date', $att_date);
                        if(!empty($subject_id)) {
                            $query->where('subject', $subject_id);
                        }
                        if(!empty($session_id)) {
                            $query->where('session', $session_id);
                        }
                })
                ->select('id', 'regi_no', 'roll_no', 'student_id')
                ->orderBy('roll_no', 'asc')
                ->get();

            $sections = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->pluck('name', 'id');
                
            $subjects = Subject::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->pluck('name', 'id');
            $sessions = new stdClass();
            $attendanceSessions = AppHelper::getAppSettings('attendance_sessions');
            if (!empty($attendanceSessions)) {
                foreach($attendanceSessions as $times){
                    $id = (!empty($times['session_no'])) ? $times['session_no'] : '';
                    $from = (!empty($times['from'])) ? Carbon::parse($times['from'])->format('h:i a') : '';
                    $to = (!empty($times['to'])) ? Carbon::parse($times['to'])->format('h:i a') : '';
                    $name = $from.':'.$to;
                    $sessions->{$id} = $name;
                }
                
            }
        }

        return view('backend.attendance.student.list', compact(
            'academic_years',
            'classes',
            'sections',
            'acYear',
            'class_id',
            'section_id',
            'session_id',
            'subject_id',
            'attendance_date',
            'attendances',
            'subjects',
            'sessions'
        ));

    }


    public function attendenceSummary()
    {
        //if its college then have to get those academic years
        $academic_years = [];
        $today = new DateTime('now');
        $attendance_date = date_format($today, 'd/m/Y');
        $attendance_type = AppHelper::getAppSettings('attendance_type');
        $current_session = AppHelper::getCurrentSession();
        $current_session_id = (!empty($current_session['session_no'])) ? $current_session['session_no'] : '';

        // if(AppHelper::getInstituteCategory() == 'college') {
        //     $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        // }else{
        $acYear = AppHelper::getAcademicYear();
        // }
        // Build class list
        $classlist = [];
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')
            ->pluck('name', 'id');

        foreach ($classes as $class_id  => $class) {
            $sections = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->orderBy('name', 'asc')
                ->pluck('name', 'id');
            foreach ($sections as $section_id => $section) {
                if (!isset($classlist[$class_id])) {
                    $classlist[$class_id] = new stdClass();
                }
                $classlist[$class_id]->{$section_id} = new stdClass();
                $classlist[$class_id]->{$section_id}->class = $class;
                $classlist[$class_id]->{$section_id}->section = $section;
                $classlist[$class_id]->{$section_id}->htmlclass = filter_var($class, FILTER_SANITIZE_NUMBER_INT);

                //Now get the student count
                $classlist[$class_id]->{$section_id}->students = Registration::where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('section_id', $section_id)
                    ->where('status', AppHelper::ACTIVE)
                    ->count();

                //now fetch attendance data
                if ($class_id && $section_id && $acYear && strlen($attendance_date) >= 10) {
                    $att_date = Carbon::createFromFormat('d/m/Y', $attendance_date)->toDateString();
                    $classlist[$class_id]->{$section_id}->present = Registration::where('academic_year_id', $acYear)
                        ->where('class_id', $class_id)
                        ->where('section_id', $section_id)
                        ->where('status', AppHelper::ACTIVE)
                        ->whereHas('attendanceSingleDay', function ($query) use ($att_date, $class_id, $acYear,$current_session_id,$attendance_type) {
                            $query->select('id')
                                ->where('academic_year_id', $acYear)
                                ->where('class_id', $class_id)
                                ->where('present', AppHelper::ACTIVE)
                                ->whereDate('attendance_date', $att_date);
                                if($attendance_type == 'session_attendance') {
                                    $query->where('session', $current_session_id);
                                }
                                
                        })
                        ->select('id')
                        ->count();
                    $classlist[$class_id]->{$section_id}->recorded = $this->getAttendanceByFilters($class_id,
                        $section_id, $acYear, $attendance_date, true,$current_session_id);
                }
            }
        }

        return view('backend.attendance.student.summary', compact(
            'classlist',
            'attendance_date'
        ));
    }


    private function getAttendanceByFilters($class_id, $section_id, $acYear, $attendance_date, $isCount = false,$session_id='',$subject_id='')
    {
        $att_date = Carbon::createFromFormat('d/m/Y', $attendance_date)->toDateString();
        return $attendances = Registration::where('academic_year_id', $acYear)
            ->where('class_id', $class_id)
            ->where('section_id', $section_id)
            ->where('status', AppHelper::ACTIVE)
            ->with([
                'student' => function ($query) {
                    $query->select('name', 'id');
                }
            ])->whereHas('attendance', function ($query) use ($att_date, $class_id, $acYear,$session_id,$subject_id) {
                $query->select('id', 'registration_id')
                    ->where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->whereDate('attendance_date', $att_date);
                if(!empty($session_id)) {
                    $query->where('session', $session_id);
                }
                if(!empty($subject_id)) {
                    $query->where('subject', $subject_id);
                }
            })->select('id', 'regi_no', 'roll_no', 'student_id')->orderBy('roll_no', 'asc')->CountOrGet($isCount);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        $students = collect();
        $academic_year = '';
        $class_name = '';
        $section_name = '';
        $academic_years = [];
        $attendance_date = date('d/m/Y');
        $acYear = null;
        $class_id = null;
        $section_id = null;
        $sections = null;
        $metas = null;
        $session_id = null;
        $subject_id = null;
        $subject_name = null;

        if (AppHelper::getInstituteCategory() == 'college') {
            $acYear = $request->get('academic_year_id', 0);
        } else {
            $acYear = AppHelper::getAcademicYear();
        }

        if ($request->isMethod('get')) {
            $class_id = $request->query->get('class', 0);
            $section_id = $request->query->get('section', 0);
            $attendance_date = $request->query->get('attendance_date', '');
            $session_id = $request->query->get('session_id', '');
            $subject_id = $request->query->get('subject_id', '');
        }
        if ($request->isMethod('post')) {
            $class_id = $request->get('class_id', 0);
            $section_id = $request->get('section_id', 0);
            $attendance_date = $request->get('attendance_date', '');
            $session_id = $request->get('session_id', '');
            $subject_id = $request->get('subject_id', '');
        }
        if ($class_id && $section_id && $acYear && strlen($attendance_date) >= 10) {
            $attendances = $this->getAttendanceByFilters($class_id, $section_id, $acYear, $attendance_date, true,$session_id,$subject_id);
            if ($attendances) {
                return redirect()->route('student_attendance.create')->with("error", "Attendance already exists!");
            }

            $students = Registration::with([
                'info' => function ($query) {
                    $query->select('name', 'id')
                        ->orderBy('name', 'asc');
                }
            ])->where('academic_year_id', $acYear)
                ->where('class_id', $class_id)
                ->where('section_id', $section_id)
                ->select('regi_no', 'roll_no', 'id', 'student_id')
                ->get()
                ->sortBy(function ($studentInfo, $key) {
                    return $studentInfo->info->name;
                });
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
            $attendance_type = AppHelper::getAppSettings('attendance_type');
            if($attendance_type == 'session_attendance') {
                if (isset($metas['attendance_sessions'])) {
                    $attendanceSessions = json_decode($metas['attendance_sessions'], true);
                    $formatedShiftData = [];
                    foreach ($attendanceSessions as $shift => $times) {
                        $activeSession = (!empty($times['session_no'])) ? $times['session_no'] : '';
                        if($activeSession == $session_id) {
                            $from = Carbon::parse($times['from']);
                            $to = Carbon::parse($times['to']);
                            $diff_in_sec = $to->diffInSeconds($from);
                            $total_hours =  gmdate('H:i', $diff_in_sec);
                            $formatedShiftData['Morning'] = [
                                'start' => Carbon::parse($times['from'])->format('h:i a'),
                                'end' => Carbon::parse($times['to'])->format('h:i a'),
                                'total_hours' => $total_hours
                            ];
                        }
                    }
                    $metas['shift_data'] = $formatedShiftData;
                }
            } else if($attendance_type == 'subject_attendance') {
                $subject = AppHelper::getSubjectById($subject_id);
                $subject_name = (!empty($subject->name)) ? $subject->name : '';
                $formatedShiftData['Morning'] = [
                    'start' => Carbon::parse(0)->format('h:i a'),
                    'end' => Carbon::parse(0)->format('h:i a'),
                    'total_hours' => 0
                ];
                $metas['shift_data'] = $formatedShiftData;
            }

            $classInfo = IClass::where('status', AppHelper::ACTIVE)
                ->where('id', $class_id)
                ->first();
            $class_name = $classInfo->name;
            $sectionsInfo = Section::where('status', AppHelper::ACTIVE)
                ->where('id', $section_id)
                ->where('class_id', $class_id)
                ->first();
            $section_name = $sectionsInfo->name;


            if (AppHelper::getInstituteCategory() == 'college') {
                $acYearInfo = AcademicYear::where('status', '1')->where('id', $acYear)->first();
                $academic_year = $acYearInfo->title;
            }
        }

        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        if (AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        $sessions = new stdClass();
        $attendanceSessions = AppHelper::getAppSettings('attendance_sessions');
        if (!empty($attendanceSessions)) {
            foreach($attendanceSessions as $times){
                $id = (!empty($times['session_no'])) ? $times['session_no'] : '';
                $from = (!empty($times['from'])) ? Carbon::parse($times['from'])->format('h:i a') : '';
                $to = (!empty($times['to'])) ? Carbon::parse($times['to'])->format('h:i a') : '';
                $name = $from.':'.$to;
                $sessions->{$id} = $name;
            }
        }

        // return $students;
        // print('<br/>');exit;
        return view('backend.attendance.student.add', compact(
            'academic_years',
            'classes',
            'sections',
            'students',
            'metas',
            'class_name',
            'academic_year',
            'section_name',
            'attendance_date',
            'class_id',
            'section_id',
            'acYear',
            'session_id',
            'subject_id',
            'subject_name',
            'sessions'
        ));

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //validate form
        $messages = [
            'registrationIds.required' => 'This section has no students!',
            'outTime.required' => 'Out time missing!',
            'inTime.required' => 'In time missing!',
        ];
        $rules = [
            'class_id' => 'required|integer',
            'section_id' => 'required|integer',
            'attendance_date' => 'required|min:10|max:11',
            'registrationIds' => 'required|array',
            'inTime' => 'required|array',
            'outTime' => 'required|array',

        ];
        //if it college then need another 2 feilds
        if (AppHelper::getInstituteCategory() == 'college') {
            $rules['academic_year'] = 'required|integer';
        }

        $this->validate($request, $rules, $messages);


        //check attendance exists or not
        $class_id = $request->get('class_id', 0);
        $section_id = $request->get('section_id', 0);
        $session_id = $request->get('session_id', 0);
        $subject_id = $request->get('subject_id', 0);
        $attendance_date = $request->get('attendance_date', '');
        if (AppHelper::getInstituteCategory() == 'college') {
            $acYear = $request->query->get('academic_year', 0);
        } else {

            $acYear = AppHelper::getAcademicYear();
        }
        $attendances = $this->getAttendanceByFilters($class_id, $section_id, $acYear, $attendance_date, true,$session_id,$subject_id);

        if ($attendances) {
            return redirect()->route('student_attendance.create')->with("error", "Attendance already exists!");
        }


        //process the insert data
        $students = $request->get('registrationIds');
        $attendance_date = Carbon::createFromFormat('d/m/Y', $request->get('attendance_date'))->format('Y-m-d');
        $dateTimeNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Dhaka'));
        $inTimes = $request->get('inTime');
        $outTimes = $request->get('outTime');

        //fetch institute shift running times
        $shiftData = AppHelper::getAppSettings('shift_data');
        if ($shiftData) {
            $shiftData = json_decode($shiftData, true);
        }
        $shiftRuningTimes = [];

        foreach ($shiftData as $shift => $times) {
            $shiftRuningTimes[$shift] = [
                'start' => Carbon::createFromFormat('d/m/Y H:i:s',
                    $request->get('attendance_date') . ' ' . $times['start']),
                'end' => Carbon::createFromFormat('d/m/Y H:i:s', $request->get('attendance_date') . ' ' . $times['end'])
            ];
        }

        $studentsShift = Registration::whereIn('id', $students)
            ->get(['id', 'shift'])
            ->reduce(function ($studentsShift, $student) {
                $studentsShift[$student->id] = $student->shift;
                return $studentsShift;
            });

        $attendances = [];
        $absentIds = [];
        $parseError = false;

        foreach ($students as $student) {

            $inTime = Carbon::createFromFormat('d/m/Y h:i a',
                $request->get('attendance_date') . ' ' . $inTimes[$student]);
            $outTime = Carbon::createFromFormat('d/m/Y h:i a',
                $request->get('attendance_date') . ' ' . $outTimes[$student]);

            if ($outTime->lessThan($inTime)) {
                $message = "Out time can't be less than in time!";
                $parseError = true;
                break;
            }

            if ($inTime->diff($outTime)->days > 1) {
                $message = "Can\'t stay more than 24 hrs!";
                $parseError = true;
                break;
            }

            $timeDiff = $inTime->diff($outTime)->format('%H:%I');
            $isPresent = ($timeDiff == "00:00") ? "0" : "1";
            $status = [];

            //late or early out find
            if ($timeDiff != "00:00" && strlen($studentsShift[$student]) && isset($shiftRuningTimes[$studentsShift[$student]])) {

                if ($inTime->greaterThan($shiftRuningTimes[$studentsShift[$student]]['start'])) {
                    $status[] = 1;
                }

                if ($outTime->lessThan($shiftRuningTimes[$studentsShift[$student]]['end'])) {
                    $status[] = 2;
                }


            }

            $attendances[] = [
                "academic_year_id" => $acYear,
                "class_id" => $class_id,
                "registration_id" => $student,
                "attendance_date" => $attendance_date,
                "in_time" => $inTime,
                "out_time" => $outTime,
                "staying_hour" => $timeDiff,
                "status" => implode(',', $status),
                "present" => $isPresent,
                "created_at" => $dateTimeNow,
                "created_by" => auth()->user()->id,
                'subject' => $subject_id,
                'session' => $session_id
            ];

            if (!$isPresent) {
                $absentIds[] = $student;
            }
        }

        if ($parseError) {
            return redirect()->route('student_attendance.create')->with("error", $message);
        }

//        dd($attendances, $absentIds);

        DB::beginTransaction();
        try {

            StudentAttendance::insert($attendances);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
            return redirect()->route('student_attendance.create')->with("error", $message);
        }


        $message = "Attendance saved successfully.";
        //check if notification need to send?
        //todo: need uncomment these code on client deploy
        $sendNotification = AppHelper::getAppSettings('student_attendance_notification');
        if ($sendNotification != "0") {
            if ($sendNotification == "1") {
                //then send sms notification

                //get sms gateway information
                $gateway = AppMeta::where('id', AppHelper::getAppSettings('student_attendance_gateway'))->first();
                if (!$gateway) {
                    redirect()->route('student_attendance.create')->with("warning",
                        $message . " But SMS Gateway not setup!");
                }

                //get sms template information
                $template = Template::where('id', AppHelper::getAppSettings('student_attendance_template'))->first();
                if (!$template) {
                    redirect()->route('student_attendance.create')->with("warning",
                        $message . " But SMS template not setup!");
                }

                $res = AppHelper::sendAbsentNotificationForStudentViaSMS($absentIds, $attendance_date);

            }
        }

        //push job to queue
        //todo: need comment these code on client deploy
        // PushStudentAbsentJob::dispatch($absentIds, $attendance_date);


        return redirect()->route('student_attendance.summary')->with("success", $message);
    }


    /**
     * status change
     * @return mixed
     */
    public function changeStatus(Request $request, $id = 0)
    {
        $attendance =  StudentAttendance::findOrFail($id);

        if (!$attendance) {
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }
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
         if($request->get('status') == 1)
         {
                   $end = $metas['shift_data']['Morning']['end'];
                   $start = $metas['shift_data']['Morning']['start'];
                   $date = $attendance->attendance_date;
                   $inTime = Carbon::createFromFormat('d/m/Y h:i a', $date.' '.$start);
                   $outTime = Carbon::createFromFormat('d/m/Y h:i a', $date.' '.$end);

                   $timeDiff  = $inTime->diff($outTime)->format('%H:%I');
                $attendance->present = (string)$request->get('status');
                $attendance->in_time = $inTime;
                $attendance->out_time = $outTime;
                $attendance->staying_hour = $timeDiff;
                $attendance->save();
        }
        else{
          $attendance->present = (string)$request->get('status');
          $attendance->in_time = '1970-01-01 00:00:00';
          $attendance->out_time = '1970-01-01 00:00:00';
          $attendance->staying_hour = '00:00:00';
          $attendance->save();
        }

        return [
            'success' => true,
            'message' => 'Status updated.'
        ];

    }

    /**
     * Upload file for add attendance
     * @return mixed
     */
    public function createFromFile(Request $request)
    {
        if ($request->isMethod('post')) {

            //validate form
            $messages = [
                'file.max' => 'The :attribute size must be under 1mb.',
            ];
            $rules = [
                'file' => 'mimetypes:text/plain|max:1024',

            ];

            $this->validate($request, $rules, $messages);

            $clientFileName = $request->file('file')->getClientOriginalName();

            // again check for file extention manually
            $ext = strtolower($request->file('file')->getClientOriginalExtension());
            if ($ext != 'txt') {
                return redirect()->back()->with('error', 'File must be a .txt file');
            }

            try {
                $storagepath = $request->file('file')->store('student-attendance');
                $fileName = basename($storagepath);

                $fullPath = storage_path('app/') . $storagepath;

                //check file content
                $linecount = 0;
                $isValidFormat = 0;
                $handle = fopen($fullPath, "r");
                while (!feof($handle)) {
                    $line = fgets($handle, 4096);
                    $linecount = $linecount + substr_count($line, PHP_EOL);

                    if ($linecount == 1) {
                        $isValidFormat = AppHelper::isLineValid($line);
                        if (!$isValidFormat) {
                            break;
                        }
                    }
                }
                fclose($handle);

                if (!$linecount) {
                    throw new Exception("File is empty.");
                }

                if (!$isValidFormat) {
                    throw new Exception("File content format is not valid.");
                }

                AttendanceFileQueue::create([
                    'file_name' => $fileName,
                    'client_file_name' => $clientFileName,
                    'file_format' => $isValidFormat,
                    'total_rows' => 0,
                    'imported_rows' => 0,
                    'attendance_type' => 1,
                ]);


                // now start the command to proccess data
                $command = "php " . base_path() . "/artisan attendance:seedStudent";

                $process = new Process($command);
                $process->start();

                // debug code
//            $process->wait();
//            echo $process->getOutput();
//            echo $process->getErrorOutput();

            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }

            return redirect()->back();
        }

        $isProcessingFile = false;
        $pendingFile = AttendanceFileQueue::where('attendance_type', 1)
            ->where('is_imported', '=', 0)
            ->orderBy('created_at', 'DESC')
            ->count();

        if ($pendingFile) {
            $isProcessingFile = true;

        }

        $queueFireUrl = route('student_attendance_seeder', ['code' => 'hr799']);
        return view('backend.attendance.student.upload', compact(
            'isProcessingFile',
            'queueFireUrl'
        ));
    }

    /**
     * Uploaded file status
     * @param Request $request
     * @return array
     */
    public function fileQueueStatus(Request $request)
    {
        $pendingFile = AttendanceFileQueue::where('attendance_type', 1)->orderBy('created_at', 'DESC')
            ->first();

        if (empty($pendingFile)) {
            return [
                'msg' => 'No file in queue to process. Reload the page.',
                'success' => true
            ];
            //nothing to do
        }

        if ($pendingFile->is_imported === 1) {
            return [
                'msg' => 'Attendance data processing complete. You can check the log.',
                'success' => true
            ];
        } else {
            if ($pendingFile->is_imported === -1) {
                return [
                    'msg' => 'Something went wrong to import data, check log file.',
                    'success' => false,
                    'status' => $pendingFile->is_imported
                ];
            } else {
                $status = $pendingFile->imported_rows . '  attendance has been imported out of ' . $pendingFile->total_rows;
                if ($pendingFile->imported_rows >= $pendingFile->total_rows) {
                    $status = "attendance has been imported. Now sending notification for absent students";
                }
                return [
                    'msg' => $status,
                    'success' => false,
                    'status' => $pendingFile->is_imported
                ];
            }
        }
    }

    /**
     * get session wise attendance card
     * @param Request $request
     * @return array
     */
    public function getSessionAttendanceCard(Request $request) {
        $class_id = $request->post('class_id', 0);
        $section = $request->post('section', 0);
        $attendance_date = $request->post('attendance_date', 0);
        $get_current_time = Carbon::now();
        $current_time = $get_current_time->toTimeString();
        $current_session = AppHelper::getCurrentSession();
        $current_session_id = (!empty($current_session['session_no'])) ? $current_session['session_no'] : '';
        $acYear = AppHelper::getAcademicYear();
        $getAllSessions = AppHelper::getAppSettings('attendance_sessions');
        $attendance_data = array();
        $response = 0;
        if(!empty($getAllSessions)) {
            $attendance_data[$class_id] = new stdClass();
            $attendance_data[$class_id]->{$section} = new stdClass();
            foreach($getAllSessions as $session) {
                $session_id = (!empty($session['session_no'])) ? $session['session_no'] :'' ;
                $from = (!empty($session['from'])) ? $session['from'] :'' ;
                $to = (!empty($session['to'])) ? $session['to'] :'' ;
                $attendance_data[$class_id]->{$section}->{$session_id} = new stdClass();

                //Now get the student count
                $attendance_data[$class_id]->{$section}->{$session_id}->students = Registration::where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('section_id', $section)
                    ->where('status', AppHelper::ACTIVE)
                    ->count();
                //now fetch attendance data
                if ($class_id && $section && $acYear && strlen($attendance_date) >= 10 && $session_id) {
                    $response = 1;
                    $att_date = Carbon::createFromFormat('d/m/Y', $attendance_date)->toDateString();
                    $attendance_data[$class_id]->{$section}->{$session_id}->present = Registration::where('academic_year_id', $acYear)
                        ->where('class_id', $class_id)
                        ->where('section_id', $section)
                        ->where('status', AppHelper::ACTIVE)
                        ->whereHas('attendanceSingleDay', function ($query) use ($att_date, $class_id, $acYear,$session_id) {
                            $query->select('id')
                                ->where('academic_year_id', $acYear)
                                ->where('class_id', $class_id)
                                ->where('present', AppHelper::ACTIVE)
                                ->whereDate('attendance_date', $att_date)
                                ->where('session', $session_id);
                        })
                        ->select('id')
                        ->count();
                    $attendance_data[$class_id]->{$section}->{$session_id}->recorded = $this->getAttendanceByFilters($class_id,
                        $section, $acYear, $attendance_date, true,$session_id);
                    $attendance_data[$class_id]->{$section}->{$session_id}->from = $from;
                    $attendance_data[$class_id]->{$section}->{$session_id}->to = $to;
                }

            }
        }
        $view = view('backend.attendance.student.session', compact('class_id', 'section', 'attendance_date','current_time','attendance_data'))->render();
         return response()->json(['html' => $view,'response'=>$response]);
    }

    /**
     * get session wise attendance card
     * @param Request $request
     * @return array
     */
    public function getSubjectAttendanceCard(Request $request) {
        $class_id = $request->post('class_id', 0);
        $section = $request->post('section', 0);
        $attendance_date = $request->post('attendance_date', 0);
        $get_current_time = Carbon::now();
        $current_time = $get_current_time->toTimeString();
        $getAllSubjects = AppHelper::getSubjectByClass($class_id);
        $acYear = AppHelper::getAcademicYear();
        $attendance_data = array();
        $response = 0;
        if(!empty($getAllSubjects)) {
            $attendance_data[$class_id] = new stdClass();
            $attendance_data[$class_id]->{$section} = new stdClass();
            foreach($getAllSubjects as $subject) {
                $subject_id = (!empty($subject['id'])) ? $subject['id'] :'' ;   
                $subject_name = (!empty($subject['name'])) ? $subject['name'] :'' ;
                $attendance_data[$class_id]->{$section}->{$subject_id} = new stdClass();
                $attendance_data[$class_id]->{$section}->{$subject_id}->subject_name = $subject_name;
                //Now get the student count
                $attendance_data[$class_id]->{$section}->{$subject_id}->students = Registration::where('academic_year_id', $acYear)
                    ->where('class_id', $class_id)
                    ->where('section_id', $section)
                    ->where('status', AppHelper::ACTIVE)
                    ->count();
                //now fetch attendance data
                if ($class_id && $section && $acYear && strlen($attendance_date) >= 10 && $subject_id) {
                    $response = 1;
                    $att_date = Carbon::createFromFormat('d/m/Y', $attendance_date)->toDateString();
                    $attendance_data[$class_id]->{$section}->{$subject_id}->present = Registration::where('academic_year_id', $acYear)
                        ->where('class_id', $class_id)
                        ->where('section_id', $section)
                        ->where('status', AppHelper::ACTIVE)
                        ->whereHas('attendanceSingleDay', function ($query) use ($att_date, $class_id, $acYear,$subject_id) {
                            $query->select('id')
                                ->where('academic_year_id', $acYear)
                                ->where('class_id', $class_id)
                                ->where('present', AppHelper::ACTIVE)
                                ->whereDate('attendance_date', $att_date)
                                ->where('subject', $subject_id);
                        })
                        ->select('id')
                        ->count();
                    $attendance_data[$class_id]->{$section}->{$subject_id}->recorded = $this->getAttendanceByFilters($class_id,
                        $section, $acYear, $attendance_date, true,null,$subject_id);
                }
                
            }
        }
        //echo '<pre>'; print_r($attendance_data);die;
        $view = view('backend.attendance.student.subject', compact('class_id', 'section', 'attendance_date','current_time','attendance_data'))->render();
         return response()->json(['html' => $view,'response'=>$response]);
    }

    /**
     * get session wise attendance card
     * @param Request $request
     * @return array
     */
    public function getTimeDifference(Request $request) {
        $in_time = $request->post('inTimeSuject', 0);
        $out_time = $request->post('outTimeSuject', 0);
        $from = Carbon::parse($in_time);
        $to = Carbon::parse($out_time);
        $diff_in_sec = $to->diffInSeconds($from);
        $total_hours =  gmdate('H:i', $diff_in_sec);
        return $total_hours;
    }
}
