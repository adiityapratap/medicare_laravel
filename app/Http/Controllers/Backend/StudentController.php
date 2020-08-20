<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Support\Facades\Auth;
use Response;
use App\Http\Imports\StudentImport;
use App\AcademicYear;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Registration;
use App\Section;
use App\Student;
use App\Subject;
use App\Template;
use App\User;
use App\UserRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Excel;
use PDF;
use App\Jobs\PreAdmissionInterview;
use App\Jobs\PromoteStudents;

class StudentController extends Controller
{
    public function searchStudent(Request $request)
    {
        $query = $request->query->get('search', '');
        $page = $request->query->get('page', 1);
        $year = $request->query->get('year', null);
        $results = AppHelper::getStudentsByName($query, $page, $year);

        return Response::json($results);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        $academic_years = [];
        if (AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        $iclass = null;
        $students = [];
        $sections = [];

        // get query parameter for filter the fetch
        $class_id = $request->query->get('class', 0);
        $section_id = $request->query->get('section', 0);
        $acYear = $request->query->get('academic_year', 0);

        $studentID = $request->query->get('studentID', 0);

        // print_r($class_id);die;

        $classInfo = null;
        $sectionInfo = null;
        if ($class_id) {

            if (AppHelper::getInstituteCategory() != 'college') {
                // now check is academic year set or not
                $settings = AppHelper::getAppSettings();
                if (!isset($settings['academic_year']) || (int)($settings['academic_year']) < 1) {
                    return redirect()->route('student.index')
                        ->with("error", 'Academic year not set yet! Please go to settings and set it.')
                        ->withInput();
                }
                $acYear = $settings['academic_year'];
            }


            if ($request->query->get('format', 0)) {
                $studentsQuery = Registration::where('class_id', $class_id)
                    ->where('academic_year_id', $acYear)
                    ->section($section_id)
                    ->with('student')
                    ->orderBy('student_id', 'asc')
                    ->get()->toArray();

                $students = array();
                foreach ($studentsQuery as $key => $data) {
                    $students[$key]['student_id'] = $data['student_id'];
                    $students[$key]['name'] = $data['student']['name'];
                }
                return $students;
            } else {
                //get student
                $students = Registration::where('class_id', $class_id)
                    ->where('academic_year_id', $acYear)
                    ->section($section_id)
                    ->with('student')
                    ->orderBy('student_id', 'asc')
                    ->get();
            }


            // print_r($students);die;
            //if section is mention then full this class section list
            if ($section_id) {
                $sections = Section::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $class_id)
                    ->pluck('name', 'id');

            }

            $iclass = $class_id;

        }

        if ($studentID) {
            $students = Registration::where('student_id', $studentID)
                ->with('student')
                ->orderBy('student_id', 'asc')
                ->get();
        }

        return view('backend.student.list',
            compact('students', 'classes', 'iclass', 'sections', 'section_id', 'academic_years', 'acYear'));

    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id = 0)
    {
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')
            ->pluck('name', 'id');
 
        $student = null;
        $gender = 1;
        $religion = 2;
        $bloodGroup = 1;
        $needtrans = 0;
        $zone = '';
        $nationality = 'Indian';
        $group = 'None';
        $shift = 'Day';
        $regiInfo = null;
        $sections = [];
        $iclass = null;
        $section = null;
        $acYear = null;
        $esubject = null;
        $csubject = null;
        $electiveSubjects = [];
        $coreSubjects = [];
        $academic_years = [];
        $iclass = 0;

        if(!empty($id)) {
            $student = Student::where('id', $id)->first();
            if(!empty($student->id)) {
                $gender = $student->getOriginal('gender');
                $religion = $student->getOriginal('religion');
                $bloodGroup = $student->getOriginal('blood_group');
                $needtrans = $student->getOriginal('need_transport');
                $zone = $student->getOriginal('transport_zone');
                $nationality = ($student->nationality != "Bangladeshi") ? "Other" : "";
                $iclass = $student->class_id;
                $sections = Section::where('class_id', $iclass)->where('status', AppHelper::ACTIVE)->pluck('name', 'id');
            }
        }

        // check for institute type and set gender default value
        $settings = AppHelper::getAppSettings();
        if (isset($settings['institute_type']) && intval($settings['institute_type']) == 2) {
            $gender = 2;
        }

        if (AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }


        return view('backend.student.add', compact(
            'regiInfo',
            'student',
            'gender',
            'religion',
            'bloodGroup',
            'needtrans',
            'zone',
            'nationality',
            'classes',
            'sections',
            'group',
            'shift',
            'iclass',
            'section',
            'academic_years',
            'acYear',
            'electiveSubjects',
            'coreSubjects',
            'esubject',
            'csubject',
            'iclass'
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
            'photo.max' => 'The :attribute size must be under 200kb.',
            'photo.dimensions' => 'The :attribute dimensions min 150 X 150.',
        ];
        $rules = [
            'name' => 'required|min:5|max:255',
            'photo' => 'mimes:jpeg,jpg,png|max:200|dimensions:min_width=150,min_height=150',
            'dob' => 'nullable|min:10|max:10',
            'pob' => 'nullable|min:5|max:255',
            'gender' => 'required|integer',
            'religion' => 'nullable|integer',
            'caste' => 'nullable|min:5|max:255',
            'castecategory' => 'nullable|min:2|max:255',
            'blood_group' => 'nullable|integer',
            'nationality' => 'nullable|max:50',
            'nationalid' => 'nullable|min:5|max:255',
            'phone_no' => 'nullable|max:15',
            'monther_tongue' => 'nullable|min:3|max:255',
            'need_transport' => 'nullable|integer',
            'transport_zone' => 'nullable|integer',
            'extra_activity' => 'nullable|max:15',
            'note' => 'nullable|max:500',
            'father_name' => 'nullable|max:255',
            'father_phone_no' => 'nullable|max:15',
            'mother_name' => 'nullable|max:255',
            'mother_phone_no' => 'nullable|max:15',
            'guardian' => 'nullable|max:255',
            'guardian_phone_no' => 'nullable|max:15',
            'present_address' => 'nullable|max:500',
            'permanent_address' => 'nullable|max:500',
            'card_no' => 'nullable|min:4|max:50|unique:registrations,card_no',
            'email' => 'nullable|max:255|unique:students,email',
            'class_id' => 'required|integer',
            'section_id' => 'required|integer',
            'shift' => 'nullable|max:15',
            'roll_no' => 'nullable|integer',
            'board_regi_no' => 'nullable|max:50',
            'fourth_subject' => 'nullable|integer',
            'house' => 'nullable|max:100',

        ];
        //if it college then need another 2 feilds
        if (AppHelper::getInstituteCategory() == 'college') {
            $rules['academic_year'] = 'required|integer';
            $rules['alt_fourth_subject'] = 'nullable|integer';
        }


        $createUser = true;

        $this->validate($request, $rules);


        if (AppHelper::getInstituteCategory() != 'college') {
            // now check is academic year set or not
            $settings = AppHelper::getAppSettings();
            if (!isset($settings['academic_year']) || (int)($settings['academic_year']) < 1) {
                return redirect()->route('student.create')
                    ->with("error", 'Academic year not set yet! Please go to settings and set it.')
                    ->withInput();
            }

            $acYearId = $settings['academic_year'];
        } else {
            $acYearId = $request->get('academic_year');
        }

        $data = $request->all();

        if ($data['nationality'] == 'Other') {
            $data['nationality'] = $data['nationality_other'];
        }

        $data['photo'] = null;
        /*$imgStorePath = "public/student/".$request->get('class_id',0);
        if($request->hasFile('photo')) {
            $storagepath = $request->file('photo')->store($imgStorePath);
            $fileName = basename($storagepath);
            $data['photo'] = $fileName;
        }
        else{
            $data['photo'] = $request->get('oldPhoto','');
        }*/


        DB::beginTransaction();
        try {
            //now create user
            if ($createUser) {
                $uname = AppHelper::generateUserName($data['name']);
                $user = User::create(
                    [
                        'name' => $data['name'],
                        'username' => $uname,
                        'email' => $data['email'],
                        'password' => bcrypt($uname),
                        'remember_token' => null,
                    ]
                );
                //now assign the user to role
                UserRole::create(
                    [
                        'user_id' => $user->id,
                        'role_id' => AppHelper::USER_STUDENT
                    ]
                );
                $data['user_id'] = $user->id;
            }
            // now save employee
            if(!empty($data['student_id'])) {
                $student = Student::find($data['student_id']);
                $student->fill($data);
                $student->class_id = NULL;
                $student->save();
            } else {
                $student = Student::create($data);
            }

            if ($request->hasFile('photo')) {
                $student->addMedia($request->file('photo'))->toMediaCollection(config('app.name') . '/students/', 's3');
            }

            $classInfo = IClass::find($data['class_id']);
            $academicYearInfo = AcademicYear::find($acYearId);
            $regiNo = $academicYearInfo->start_date->format('y') . (string)$classInfo->numeric_value;

            $totalStudent = Registration::where('academic_year_id', $academicYearInfo->id)
                ->where('class_id', $classInfo->id)->withTrashed()->count();
            $regiNo .= str_pad(++$totalStudent, 3, '0', STR_PAD_LEFT);


            $registrationData = [
                'regi_no' => $regiNo,
                'student_id' => $student->id,
                'class_id' => $data['class_id'],
                'section_id' => $data['section_id'],
                'academic_year_id' => $academicYearInfo->id,
                'roll_no' => $data['roll_no'],
                'shift' => $data['shift'],
                'card_no' => $data['card_no'],
                'board_regi_no' => $data['board_regi_no'],
                'fourth_subject' => $data['fourth_subject'] ?? 0,
                'alt_fourth_subject' => $data['alt_fourth_subject'] ?? 0,
                'house' => $data['house'] ?? ''
            ];

            Registration::create($registrationData);

            // now commit the database
            DB::commit();
            $request->session()->flash('message', "Student registration number is " . $regiNo);

            //now notify the admins about this record
            $msg = $data['name'] . " student added by " . auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            return redirect()->route('student.create')->with('success',
                'Student added! Username and Password is <b>' . $uname . '</b>');


        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
            return redirect()->route('student.create')->with("error", $message);
        }

        return redirect()->route('student.create');


    }


    /**
     * Display the specified resource.
     *
     * @param \App\Item $item
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        // if print id card of this student then
        // Do here
        if ($request->query->get('print_idcard', 0)) {

            $templateId = AppHelper::getAppSettings('student_idcard_template');
            $templateConfig = Template::where('id', $templateId)->where('type', 3)->where('role_id',
                AppHelper::USER_STUDENT)->first();

            if (!$templateConfig) {
                return redirect()->route('administrator.template.idcard.index')->with('error', 'Template not found!');
            }

            $templateConfig = json_decode($templateConfig->content);

            $format = "format_";
            if ($templateConfig->format_id == 2) {
                $format .= "two";
            } else {
                if ($templateConfig->format_id == 3) {
                    $format .= "three";
                } else {
                    $format .= "one";
                }
            }

            //get institute information
            $instituteInfo = AppHelper::getAppSettings('institute_settings');


            $students = Registration::where('id', $id)
                ->where('status', AppHelper::ACTIVE)
                ->with([
                    'student' => function ($query) {
                        $query->select('name', 'blood_group', 'id', 'photo');
                    }
                ])
                ->with([
                    'class' => function ($query) {
                        $query->select('name', 'group', 'id');
                    }
                ])
                ->select('id', 'roll_no', 'regi_no', 'student_id', 'class_id', 'house', 'academic_year_id')
                ->orderBy('roll_no', 'asc')
                ->get();

            if (!$students) {
                abort(404);
            }


            $acYearInfo = AcademicYear::where('id', $students[0]->academic_year_id)->first();
            $session = $acYearInfo->title;
            $validity = $acYearInfo->end_date->format('Y');

            if ($templateConfig->format_id == 3) {
                $validity = $acYearInfo->end_date->format('F Y');
            }


            $totalStudent = count($students);

            $side = 'both';
            return view('backend.report.student.idcard.' . $format, compact(
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
        if (!$student) {
            abort(404);
        }
        $username = '';
        $fourthSubject = '';
        $altfourthSubject = '';

        if ($student->fourth_subject) {
            $subjectInfo = Subject::where('id', $student->fourth_subject)->select('name')->first();
            $fourthSubject = $subjectInfo->name;
        }

        if ($student->alt_fourth_subject) {
            $subjectInfo = Subject::where('id', $student->alt_fourth_subject)->select('name')->first();
            $altfourthSubject = $subjectInfo->name;
        }

        if ($student->student->user_id) {
            $user = User::find($student->student->user_id);
            $username = $user->username;
        }

        $student->student->photo = AppHelper::getS3URL('student', $student->student->id);

        return view('backend.student.view', compact('student', 'username', 'fourthSubject', 'altfourthSubject'));
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Item $item
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $regiInfo = Registration::find($id);
        if (!$regiInfo) {
            abort(404);
        }
        $student = Student::find($regiInfo->student_id);
        if (!$student) {
            abort(404);
        }
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->pluck('name', 'id');
        $sections = Section::where('class_id', $regiInfo->class_id)->where('status', AppHelper::ACTIVE)
            ->pluck('name', 'id');

        $isCollege = (AppHelper::getInstituteCategory() == 'college');
        $subjectType = $isCollege ? 0 : 2;
        $electiveSubjects = Subject::select('id', 'name')->where('class_id', $regiInfo->class_id)
            ->sType($subjectType)->where('status', AppHelper::ACTIVE)->orderBy('name', 'asc')->pluck('name', 'id');
        $coreSubjects = null;
        if ($isCollege) {
            $coreSubjects = Subject::select('id', 'name')->where('class_id', $regiInfo->class_id)
                ->sType(1)->where('status', AppHelper::ACTIVE)->orderBy('name', 'asc')->pluck('name', 'id');
        }

        $gender = $student->getOriginal('gender');
        $religion = $student->getOriginal('religion');
        $bloodGroup = $student->getOriginal('blood_group');
        $needtrans = $student->getOriginal('need_transport');
        $zone = $student->getOriginal('transport_zone');
        $nationality = ($student->nationality != "Bangladeshi") ? "Other" : "";
        $shift = $regiInfo->shift;

        $section = $regiInfo->section_id;
        $iclass = $regiInfo->class_id;
        $esubject = $regiInfo->fourth_subject;
        $csubject = $regiInfo->alt_fourth_subject;

        $users = [];
        if (!$student->user_id) {
            $users = User::doesnthave('employee')
                ->doesnthave('student')
                ->whereHas('role', function ($query) {
                    $query->where('role_id', AppHelper::USER_STUDENT);
                })
                ->pluck('name', 'id');
        }


        return view('backend.student.add', compact(
            'regiInfo',
            'student',
            'gender',
            'religion',
            'bloodGroup',
            'needtrans',
            'zone',
            'nationality',
            'classes',
            'sections',
            'shift',
            'iclass',
            'section',
            'electiveSubjects',
            'coreSubjects',
            'esubject',
            'csubject',
            'users'
        ));

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
        $regiInfo = Registration::find($id);
        if (!$regiInfo) {
            abort(404);
        }
        $student = Student::find($regiInfo->student_id);
        if (!$student) {
            abort(404);
        }

        //validate form
        $messages = [
            'photo.max' => 'The :attribute size must be under 200kb.',
            'photo.dimensions' => 'The :attribute dimensions min 150 X 150.',
        ];
        $rules = [
            'name' => 'required|min:5|max:255',
            'photo' => 'mimes:jpeg,jpg,png|max:200|dimensions:min_width=150,min_height=150',
            'dob' => 'nullable|min:10|max:10',
            'pob' => 'nullable|min:5|max:255',
            'gender' => 'required|integer',
            'religion' => 'nullable|integer',
            'caste' => 'nullable|min:5|max:255',
            'castecategory' => 'nullable|min:2|max:255',
            'blood_group' => 'nullable|integer',
            'nationality' => 'nullable|max:50',
            'nationalid' => 'nullable|min:5|max:255',
            'phone_no' => 'nullable|max:15',
            'monther_tongue' => 'nullable|min:3|max:255',
            'need_transport' => 'nullable|integer',
            'transport_zone' => 'nullable|integer',
            'extra_activity' => 'nullable|max:15',
            'note' => 'nullable|max:500',
            'father_name' => 'nullable|max:255',
            'father_phone_no' => 'nullable|max:15',
            'mother_name' => 'nullable|max:255',
            'mother_phone_no' => 'nullable|max:15',
            'guardian' => 'nullable|max:255',
            'guardian_phone_no' => 'nullable|max:15',
            'present_address' => 'nullable|max:500',
            'permanent_address' => 'nullable|max:500',
            'card_no' => 'nullable|min:4|max:50|unique:registrations,card_no,' . $regiInfo->id,
            'email' => 'nullable|email|max:255|unique:students,email,' . $student->id . '|email|unique:users,email,' . $student->user_id,
//            'class_id' => 'required|integer',
            'section_id' => 'required|integer',
            'shift' => 'nullable|max:15',
            'roll_no' => 'nullable|integer',
            'board_regi_no' => 'nullable|max:50',
            'fourth_subject' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'house' => 'nullable|max:100',

        ];

        //if it college then need another 1 feilds
        if (AppHelper::getInstituteCategory() == 'college') {
            $rules['alt_fourth_subject'] = 'nullable|integer';
        }


        $this->validate($request, $rules);


        if (AppHelper::getInstituteCategory() != 'college') {
            // now check is academic year set or not
            $settings = AppHelper::getAppSettings();
            if (!isset($settings['academic_year']) || (int)($settings['academic_year']) < 1) {
                return redirect()->back()
                    ->with("error", 'Academic year not set yet! Please go to settings and set it.');

            }
        }

        $data = $request->all();

        if ($data['nationality'] == 'Other') {
            $data['nationality'] = $data['nationality_other'];
        }

        if ($request->hasFile('photo')) {
            $oldFile = $student->getFirstMedia(config('app.name') . '/students/');
            if (!empty($oldFile)) {
                $oldFile->delete();
            }
            $student->addMedia($request->file('photo'))->toMediaCollection(config('app.name') . '/students/', 's3');
        }
        $data['photo'] = null;
        /*$imgStorePath = "public/student/".$regiInfo->class_id;
        if($request->hasFile('photo')) {
            $storagepath = $request->file('photo')->store($imgStorePath);
            $fileName = basename($storagepath);
            $data['photo'] = $fileName;

            //if file change then delete old one
            $oldFile = $request->get('oldPhoto','');
            if( $oldFile != ''){
                $file_path = $imgStorePath.'/'.$oldFile;
                Storage::delete($file_path);
            }
        }
        else{
            $data['photo'] = $request->get('oldPhoto','');
        }*/


        $registrationData = [
//            'class_id' => $data['class_id'],
            'section_id' => $data['section_id'],
            'roll_no' => $data['roll_no'],
            'shift' => $data['shift'],
            'card_no' => $data['card_no'],
            'board_regi_no' => $data['board_regi_no'],
            'fourth_subject' => $data['fourth_subject'] ?? 0,
            'alt_fourth_subject' => $data['alt_fourth_subject'] ?? 0,
            'house' => $data['house'] ?? ''
        ];

        // now check if student academic information changed, if so then log it
        $isChanged = false;
        $logData = [];
        $timeNow = Carbon::now();
        if ($regiInfo->section_id != $data['section_id']) {
            $isChanged = true;
            $logData[] = [
                'student_id' => $regiInfo->student_id,
                'academic_year_id' => $regiInfo->academic_year_id,
                'meta_key' => 'section',
                'meta_value' => $regiInfo->section_id,
                'created_at' => $timeNow,

            ];
        }
        if ($regiInfo->roll_no != $data['roll_no']) {
            $isChanged = true;
            $logData[] = [
                'student_id' => $regiInfo->student_id,
                'academic_year_id' => $regiInfo->academic_year_id,
                'meta_key' => 'roll no',
                'meta_value' => $regiInfo->roll_no,
                'created_at' => $timeNow,

            ];
        }


        if ($regiInfo->shift != $data['shift']) {
            $isChanged = true;
            $logData[] = [
                'student_id' => $regiInfo->student_id,
                'academic_year_id' => $regiInfo->academic_year_id,
                'meta_key' => 'shift',
                'meta_value' => $regiInfo->shift,
                'created_at' => $timeNow,

            ];
        }

        if ($regiInfo->card_no != $data['card_no']) {
            $isChanged = true;
            $logData[] = [
                'student_id' => $regiInfo->student_id,
                'academic_year_id' => $regiInfo->academic_year_id,
                'meta_key' => 'card no',
                'meta_value' => $regiInfo->card_no,
                'created_at' => $timeNow,

            ];
        }
        if ($regiInfo->board_regi_no != $data['board_regi_no']) {
            $isChanged = true;
            $logData[] = [
                'student_id' => $regiInfo->student_id,
                'academic_year_id' => $regiInfo->academic_year_id,
                'meta_key' => 'board regi no',
                'meta_value' => $regiInfo->board_regi_no,
                'created_at' => $timeNow,

            ];
        }

        if ($regiInfo->fourth_subject != $data['fourth_subject']) {
            $isChanged = true;
            $logData[] = [
                'student_id' => $regiInfo->student_id,
                'academic_year_id' => $regiInfo->academic_year_id,
                'meta_key' => 'fourth subject',
                'meta_value' => $regiInfo->fourth_subject,
                'created_at' => $timeNow,

            ];
        }

        //if it college then need another 1 feilds
        if (AppHelper::getInstituteCategory() == 'college') {
            if ($regiInfo->alt_fourth_subject != $data['alt_fourth_subject']) {
                $isChanged = true;
                $logData[] = [
                    'student_id' => $regiInfo->student_id,
                    'academic_year_id' => $regiInfo->academic_year_id,
                    'meta_key' => 'alt fourth subject',
                    'meta_value' => $regiInfo->alt_fourth_subject,
                    'created_at' => $timeNow,

                ];
            }
        }

        $message = 'Something went wrong!';
        DB::beginTransaction();
        try {

            // save registration data
            $regiInfo->fill($registrationData);
            $regiInfo->save();

            //
            if (!$student->user_id && $request->get('user_id', 0)) {
                $data['user_id'] = $request->get('user_id');
            }


            // now save student
            $student->fill($data);
            $student->save();

            //if have changes then insert log
            if ($isChanged) {
                DB::table('student_info_log')->insert($logData);
            }
            // now commit the database
            DB::commit();

            return redirect()->route('student.index', [
                'class' => $regiInfo->class_id,
                'section' => $regiInfo->section_id,
                'academic_year' => $regiInfo->academic_year_id
            ])->with('success', 'Student updated!');


        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
//            dd($message);
        }

        return redirect()->route('student.edit', $regiInfo->id)->with("error", $message);;


    }


    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Item $item
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $registration = Registration::find($id);
        if (!$registration) {
            abort(404);
        }
        $student = Student::find($registration->student_id);
        if (!$student) {
            abort(404);
        }

        $message = 'Something went wrong!';
        DB::beginTransaction();
        try {

            /*$studentPhoto = $student->getFirstMedia(config('app.name').'/students/');
            if(!empty($studentPhoto)) {
                $studentPhoto->delete();
            }*/

            $registration->delete();
            $student->delete();
            if ($student->user_id) {
                $user = User::find($student->user_id);
                $user->delete();
            }
            DB::commit();


            //now notify the admins about this record
            $msg = $student->name . " student deleted by " . auth()->user()->name;
            $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            // Notification end

            return redirect()->route('student.index')->with('success', 'Student deleted.');

        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
        }
        return redirect()->route('student.index')->with('error', $message);

    }

    /**
     * status change
     * @return mixed
     */
    public function changeStatus(Request $request, $id = 0)
    {

        $registration = Registration::find($id);
        if (!$registration) {
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }
        $student = Student::find($registration->student_id);
        if (!$student) {
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }

        $student->status = (string)$request->get('status');
        $registration->status = (string)$request->get('status');
        if ($student->user_id) {
            $user = User::find($student->user_id);
            $user->status = (string)$request->get('status');
        }

        $message = 'Something went wrong!';
        DB::beginTransaction();
        try {

            $registration->save();
            $student->save();
            if ($student->user_id) {
                $user->save();
            }
            DB::commit();

            return [
                'success' => true,
                'message' => 'Status updated.'
            ];


        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
        }

        return [
            'success' => false,
            'message' => $message
        ];


    }

    /**
     * Get student list by filters
     */
    public function studentListByFitler(Request $request)
    {
        $classId = $request->query->get('class', 0);
        $sectionId = $request->query->get('section', 0);
        $acYear = $request->query->get('academic_year', 0);

        if (AppHelper::getInstituteCategory() != 'college') {
            $acYear = AppHelper::getAcademicYear();
        }

        $students = Registration::where('academic_year_id', $acYear)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('status', AppHelper::ACTIVE)
            ->with([
                'student' => function ($query) {
                    $query->select('name', 'id');
                }
            ])
            ->select('id', 'roll_no', 'student_id')
            ->orderBy('roll_no', 'asc')
            ->get();

        return response()->json($students);

    }

    /**
     * Upload file for student
     * @return mixed
     */
    // Newly Added
    public function createFromFile(Request $request)
    {
        if ($request->isMethod('post')) {
            ini_set('max_execution_time', 1800);
            $request->validate([
                'import_file' => 'required'
            ]);

            $acYearId = '';
            if (AppHelper::getInstituteCategory() != 'college') {
                $settings = AppHelper::getAppSettings();
                if (!isset($settings['academic_year']) || (int)($settings['academic_year']) < 1) {
                    return back()
                        ->with("error", 'Academic year not set yet! Please go to settings and set it.')
                        ->withInput();
                }
                $acYearId = $settings['academic_year'];
            }

            try {
                // $path = $request->file('import_file')->getRealPath();
                Excel::import(new StudentImport, $request->file('import_file'));
                return redirect()->route('student.index')->with('success', $_SESSION['importmessage']);
            } catch (\Exception $e) {
                $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
                return back()->with("error", $message);
            }
            // $studentCount = 0;
            // if($data->count()){
            //     foreach ($data as $key => $value) {
            //         $studentCount   =   $studentCount + 1;
            //         DB::beginTransaction();
            //         try {
            //             //now create user
            //             $username = AppHelper::generateUserName($value->name);
            //             $user = User::create(
            //                 [
            //                     'name' => $value->name,
            //                     'username' => $username,
            //                     'email' => $value->email,
            //                     'password' => bcrypt($username),
            //                     'remember_token' => null,
            //                 ]
            //             );
            //             //now assign the user to role
            //             UserRole::create(
            //                 [
            //                     'user_id' => $user->id,
            //                     'role_id' => AppHelper::USER_STUDENT
            //                 ]
            //             );

            //             $arr = [
            //                 'name' =>  $value->name,
            //                 'dob' =>  $value->dob ? date('d/m/Y', strtotime($value->dob)) : '',
            //                 'gender' =>  $value->gender,
            //                 'religion' =>  $value->religion,
            //                 'pob' => $value->pob,
            //                 'caste' => $value->caste,
            //                 'castecategory' => $value->castecategory,
            //                 'nationalid' => $value->nationalid,
            //                 'monther_tongue' => $value->monther_tongue,
            //                 'need_transport' => strval($value->need_transport)?strval($value->need_transport):'0',
            //                 'transport_zone' => $value->transport_zone,
            //                 'blood_group' =>  $value->blood_group,
            //                 'nationality' =>  $value->nationality,
            //                 'photo' =>  $value->photo,
            //                 'email' =>  $value->email,
            //                 'phone_no' =>  $value->phone_no,
            //                 'extra_activity' =>  $value->extra_activity,
            //                 'note' =>  $value->note,
            //                 'father_name' =>  $value->father_name,
            //                 'father_phone_no' =>  $value->father_phone_no,
            //                 'mother_name' =>  $value->mother_name,
            //                 'mother_phone_no' =>  $value->mother_phone_no,
            //                 'guardian' =>  $value->guardian,
            //                 'guardian_phone_no' =>  $value->guardian_phone_no,
            //                 'present_address' =>  $value->present_address ? $value->present_address : 'NA',
            //                 'permanent_address' =>  $value->permanent_address ? $value->permanent_address : 'NA',
            //                 'status' =>  "1"
            //             ];

            //             $arr['user_id'] = $user->id;

            //             // now save student
            //             $student = Student::create($arr);

            //             $classInfo = IClass::find($value->class_id);

            //             $academicYearInfo = AcademicYear::find($acYearId);

            //             $regiNo = $academicYearInfo->start_date->format('y') . (string)$classInfo->numeric_value;

            //             $totalStudent = Registration::where('academic_year_id', $academicYearInfo->id)
            //                 ->where('class_id', $classInfo->id)->withTrashed()->count();
            //             $regiNo .= str_pad(++$totalStudent,3,'0',STR_PAD_LEFT);


            //             $registrationData = [
            //                 'regi_no' => $regiNo,
            //                 'student_id' => $student->id,
            //                 'class_id' => $value->class_id,
            //                 'section_id' => $value->section_id,
            //                 'academic_year_id' => $academicYearInfo->id,
            //                 'roll_no' => $value->roll_no,
            //                 'shift' => $value->shift,
            //                 'card_no' => $value->card_no,
            //                 'board_regi_no' => $value->board_regi_no,
            //                 'fourth_subject' => $value->fourth_subject ??  0,
            //                 'alt_fourth_subject' => $value->alt_fourth_subject ??  0,
            //                 'house' => $value->house ??  ''
            //             ];

            //             Registration::create($registrationData);

            //             // now commit the database
            //             DB::commit();
            //             // $request->session()->flash('message', "Student registration number is ".$regiNo);

            //             //now notify the admins about this record
            //             $msg = $value->name." student added by ".auth()->user()->name;
            //             $nothing = AppHelper::sendNotificationToAdmins('info', $msg);
            //             // Notification end

            //             // return back()->with('success', 'Student added!');
            //         }

            //         catch(\Exception $e){
            //             DB::rollback();
            //             $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
            //             return back()->with("error",$message);
            //         }   
            //     }
            // } else {
            //     return back()->with('error', 'The CSV file was empty.');
            // }
            // return redirect()->route('student.index')->with('success', 'File Uploaded Successfully! '. $studentCount. 'Student(s) added!');
        }
        $isProcessingFile = false;
        return view('backend.student.upload', compact('isProcessingFile'));
    }

    /*
     * Study Certificate
     */
    public function studyCertificate(Request $request)
    {
        $print = 0;
        if ($request->isMethod('post')) {
            $data['name'] = $request->input('student_name');
            $data['father_name'] = $request->input('parent_name');
            $data['dob'] = $request->input('student_dob');
            $data['permanent_address'] = $request->input('student_address');
            Student::where('id', $request->input('studentID'))->update($data);
            $print = 1;
        }

        $id = $request->get('studentID');

        $templateId = AppHelper::getAppSettings('study_certificate_template');
        $templateBG = AppHelper::getAppSettings('study_certificate_template_BG');
        $templateConfig = Template::where('id', $templateId)->where('type', 4)->first();

        if (!$templateConfig) {
            return redirect()->route('administrator.template.studyCertificate.index')->with('error',
                'Template not found!');
        }

        //get institute information
        $instituteInfo = AppHelper::getAppSettings('institute_settings');

        $templateConfig = json_decode($templateConfig->content);
        $templateConfig->logo = (!empty($templateConfig->logo)) ? 'data:image/png;base64,' . $templateConfig->logo : (($print) ? public_path('storage/logo/' . $instituteInfo['logo']) : asset('storage/logo/' . $instituteInfo['logo']));
        $templateConfig->signature = (!empty($templateConfig->signature)) ? 'data:image/png;base64,' . $templateConfig->signature : '';

        $students = Registration::where('id', $id)
            ->where('status', AppHelper::ACTIVE)
            ->with([
                'student' => function ($query) {
                    $query->select('name', 'dob', 'id', 'photo', 'father_name', 'gender', 'permanent_address');
                }
            ])
            ->with([
                'class' => function ($query) {
                    $query->select('name', 'group', 'id');
                }
            ])
            ->select('id', 'roll_no', 'regi_no', 'student_id', 'class_id', 'house', 'academic_year_id')
            ->orderBy('roll_no', 'asc')
            ->first();

        if (!$students) {
            abort(404);
        }

        $acYearInfo = AcademicYear::where('id', $students->academic_year_id)->first();
        $session = $acYearInfo->title;


        if ($print) {
            $pdf = PDF::loadView('backend.report.student.studyCertificate',
                compact('templateConfig', 'instituteInfo', 'students', 'session', 'templateBG', 'print'));
            return $pdf->stream('study-certificate.pdf');
        } else {
            $view = view('backend.report.student.studyCertificate',
                compact('templateConfig', 'instituteInfo', 'students', 'session', 'templateBG', 'print'))->render();
            return response()->json(['html' => $view]);
        }

        // return ($print)?$view:response()->json(['html' => $view]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function profileEdit()
    {
        $userRoleId = session('user_role_id', 0);

        // For student
        if ($userRoleId != AppHelper::USER_STUDENT) {
            abort(404);
        }

        $id = Auth::user()->student->register->id;

        $regiInfo = Registration::find($id);
        if (!$regiInfo) {
            abort(404);
        }
        $student = Student::find($regiInfo->student_id);
        if (!$student) {
            abort(404);
        }

        $gender = $student->getOriginal('gender');
        $religion = $student->getOriginal('religion');
        $bloodGroup = $student->getOriginal('blood_group');
        $needtrans = $student->getOriginal('need_transport');
        $zone = $student->getOriginal('transport_zone');
        $nationality = ($student->nationality != "Bangladeshi") ? "Other" : "";

        $users = [];
        if (!$student->user_id) {
            $users = User::doesnthave('employee')
                ->doesnthave('student')
                ->whereHas('role', function ($query) {
                    $query->where('role_id', AppHelper::USER_STUDENT);
                })
                ->pluck('name', 'id');
        }

        return view('backend.student.edit-profile', compact(
            'regiInfo',
            'student',
            'gender',
            'religion',
            'bloodGroup',
            'needtrans',
            'zone',
            'nationality',
            'users'
        ));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function profileUpdate(Request $request)
    {
        $userRoleId = session('user_role_id', 0);

        // For student
        if ($userRoleId != AppHelper::USER_STUDENT) {
            abort(404);
        }

        $id = Auth::user()->student->register->id;

        $regiInfo = Registration::find($id);
        if (!$regiInfo) {
            abort(404);
        }
        $student = Student::find($regiInfo->student_id);
        if (!$student) {
            abort(404);
        }

        //validate form
        $messages = [
            'photo.max' => 'The :attribute size must be under 200kb.',
            'photo.dimensions' => 'The :attribute dimensions min 150 X 150.',
        ];
        $rules = [
            'name' => 'required|min:5|max:255',
            'photo' => 'mimes:jpeg,jpg,png|max:200|dimensions:min_width=150,min_height=150',
            'dob' => 'nullable|min:10|max:10',
            'pob' => 'nullable|min:5|max:255',
            'gender' => 'required|integer',
            'religion' => 'nullable|integer',
            'caste' => 'nullable|min:5|max:255',
            'castecategory' => 'nullable|min:2|max:255',
            'blood_group' => 'nullable|integer',
            'nationality' => 'nullable|max:50',
            'nationalid' => 'nullable|min:5|max:255',
            'phone_no' => 'nullable|max:15',
            'monther_tongue' => 'nullable|min:3|max:255',
            'need_transport' => 'nullable|integer',
            'transport_zone' => 'nullable|integer',
            'extra_activity' => 'nullable|max:15',
            'note' => 'nullable|max:500',
            'father_name' => 'nullable|max:255',
            'father_phone_no' => 'nullable|max:15',
            'mother_name' => 'nullable|max:255',
            'mother_phone_no' => 'nullable|max:15',
            'guardian' => 'nullable|max:255',
            'guardian_phone_no' => 'nullable|max:15',
            'present_address' => 'nullable|max:500',
            'permanent_address' => 'nullable|max:500',
            'card_no' => 'nullable|min:4|max:50|unique:registrations,card_no,' . $regiInfo->id,
            'email' => 'nullable|email|max:255|unique:students,email,' . $student->id . '|email|unique:users,email,' . $student->user_id,
        ];


        $this->validate($request, $rules);

        $data = $request->all();

        if ($data['nationality'] == 'Other') {
            $data['nationality'] = $data['nationality_other'];
        }

        if ($request->hasFile('photo')) {
            $oldFile = $student->getFirstMedia(config('app.name') . '/students/');
            if (!empty($oldFile)) {
                $oldFile->delete();
            }
            $student->addMedia($request->file('photo'))->toMediaCollection(config('app.name') . '/students/', 's3');
        }
        $data['photo'] = null;


        $message = 'Something went wrong!';

        try {
            //
            if (!$student->user_id && $request->get('user_id', 0)) {
                $data['user_id'] = $request->get('user_id');
            }


            // now save student
            $student->fill($data);
            $student->save();

            return redirect()->route('profile')->with('success', 'Profile updated!');

        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
        }

        return redirect()->route('profile')->with("error", $message);
    }

    /*
     * Pre students listing
     */
    public function preStudents(Request $request)
    {
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')
            ->pluck('name', 'id');
        $iclass = null;
        $class_id = $request->query->get('class_id', 0);
        $interview_date = $request->query->get('interview_date');
        $students = Student::select('students.*', 'i_classes.name AS className')->where('user_id', NULL)->leftJoin('i_classes', 'i_classes.id', '=', 'students.class_id');
        if ($class_id) {
            $students = $students->where('class_id', $class_id);
            $iclass = $class_id;
        }
        if ($interview_date) {
            $interviewDateTime = Carbon::createFromFormat('d/m/Y', $interview_date)->toDateString();
            $students = $students->whereDate('interview_datetime', date('Y-m-d', strtotime($interviewDateTime)));
            $interview_date = $interview_date;
        } else {
            $interview_date = date('d/m/Y');
        }
        $students = $students->get();
        return view('backend.student.list-pre-students', compact('students', 'classes', 'iclass', 'interview_date'));
    }

    /*
     * Send interview time to pre student
     */
    public function setInterview(Request $request) {
        $rules = [
            'studentIDs' => 'required',
            'datetime' => 'required',
        ];
        $this->validate($request, $rules);
        $data = $request->all();
        $studentIDs = $data['studentIDs'];
        $interviewDate = Carbon::createFromFormat('d/m/Y H:i a', $data['datetime']);
        Student::whereIn('id', explode(',', $studentIDs))->update(['interview_datetime' => date('Y-m-d H:i:s', strtotime($interviewDate))]);
        PreAdmissionInterview::dispatch($studentIDs, $interviewDate);
        return redirect()->route('student.preStudents')->with("success", 'Notifications sent to students.');
    }

    /*
     * Students listing for Promotion
     */
    public function promotion(Request $request)
    {
        $teacherClasses = array();
        if(auth()->user()->role['role_id'] == AppHelper::USER_TEACHER) {
            $teacherID = DB::table('employees')->select('id')->where('user_id', auth()->user()->id)->first();
            $teacherClasses = AppHelper::getTeacherClasses($teacherID->id);
            $classes = IClass::where('status', AppHelper::ACTIVE)->whereIn('id', $teacherClasses)->orderBy('order', 'asc')->pluck('name', 'id');
        } else {
            $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->pluck('name', 'id');
        }

        $iclass = null;
        $students = [];
        $sections = [];
        $acYear = AppHelper::getAcademicYear();
        $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');

        $class_id = $request->query->get('class', 0);
        $section_id = $request->query->get('section', 0);
        $studentID = $request->query->get('studentID', 0);
        if ($class_id) {
            $studentQuery = Registration::where('class_id', $class_id)->section($section_id)->with('student')->with('result:registration_id,total_marks,grade');
            if(!empty($teacherClasses)) {
                $studentQuery->whereIn('class_id', $teacherClasses);
            }
            $students = $studentQuery->orderBy('student_id', 'asc')->get();

            if ($section_id) {
                $sections = Section::where('status', AppHelper::ACTIVE)->where('class_id', $class_id)->pluck('name', 'id');
            }
            $iclass = $class_id;
        }
        if ($studentID) {
            $studentQuery = Registration::where('student_id', $studentID)->with('student')->with('result:registration_id,total_marks,grade');
            if(!empty($teacherClasses)) {
                $studentQuery->whereIn('class_id', $teacherClasses);
            }
            $students = $studentQuery->orderBy('student_id', 'asc')->get();
        }
        return view('backend.student.promotion', compact('students', 'classes', 'iclass', 'sections', 'section_id', 'acYear', 'academic_years'));
    }

    /*
     * Promote Students
     */
    public function promoteStudents(Request $request) {
        $rules = [
            'studentIDs' => 'required',
            'academic_year_id' => 'required',
            'class_id' => 'required',
            'sectionID' => 'required',
        ];
        $this->validate($request, $rules);
        $data = $request->all();
        $studentIDs = $data['studentIDs'];
        try {
            Registration::whereIn('student_id', explode(',', $studentIDs))->update(['academic_year_id' => $data['academic_year_id'], 'class_id' => $data['class_id'], 'section_id' => $data['sectionID']]);
            if(!empty($data['notify'])) {//Notify Students
                PromoteStudents::dispatch($studentIDs);
            }
            return redirect()->route('student.promotion')->with("success", 'Student(s) promoted successfully.');
        } catch (\Exception $e) {
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
            return redirect()->route('student.promotion')->with("error", $message);
        }
    }

}
