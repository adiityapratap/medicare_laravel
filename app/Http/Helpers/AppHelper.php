<?php
namespace App\Http\Helpers;

use App\Employee;
use App\Student;
use App\Event;
use App\FeeSetup;
use App\Jobs\ProcessSms;
use App\Notifications\UserActivity;
use App\Permission;
use App\Registration;
use App\AcademicYear;
use App\SiteMeta;
use App\Template;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\AppMeta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Picqer\Barcode\BarcodeGeneratorPNG;
use App\HomeworkSubmission;
use App\FeeCol;
use App\Subject;
use App\StudentAttendance;

class AppHelper
{

    const weekDays = [
        0 => "Sunday",
        1 => "Monday",
        2 => "Tuesday",
        3 => "Wednesday",
        4 => "Thursday",
        5 => "Friday",
        6 => "Saturday",
    ];

    const LANGUEAGES = [
        'en' => 'English',
        'hi' => 'Hindi',
    ];
    const USER_ADMIN = 1;
    const USER_PRINCIPAL = 2;
    const USER_TEACHER = 3;
    const USER_STUDENT = 4;
    const USER_PARENTS = 5;
    const USER_ACCOUNTANT = 6;
    const USER_LIBRARIAN = 7;
    const USER_RECEPTIONIST = 8;
    const ACTIVE = '1';
    const INACTIVE = '0';
    const EMP_TEACHER = AppHelper::USER_TEACHER;
    const EMP_SHIFTS = [
        1 => 'Day',
        2 => 'Night'
    ];
    const GENDER = [
        1 => 'Male',
        2 => 'Female'
    ];
    const RELIGION = [
        1 => 'Islam',
        2 => 'Hindu',
        3 => 'Cristian',
        4 => 'Buddhist',
        5 => 'Other',
    ];

    const BLOOD_GROUP = [
        1 => 'A+',
        2 => 'O+',
        3 => 'B+',
        4 => 'AB+',
        5 => 'A-',
        6 => 'O-',
        7 => 'B-',
        8 => 'AB-',
    ];

    const NEED_TRANSPORT = [
        0 => 'No',
        1 => 'Yes',
    ];

    const FEE_TYPES = [
        1 => 'School',
        2 => 'Trust',
        3 => 'Transportation',
        4 => 'Other',
        5 => 'Monthly',
        6 => 'School Extra'
    ];

    const LATEFEETYPES = ['fixed' => 'Fixed', 'daily' => 'Daily'];
    const INSTALLMENTTYPES = ['fixed' => 'Fixed', 'perc' => 'Perc.(%)'];
    const TRANSPORT = 3;
    const TRUSTID = 2;
    const OTHERID = 4;
    const MONTHLYID = 5;
    const FEERECIPT_INTER = 'academic';

    const PAYMENT_METHOD = [
        1 => 'Cash',
        2 => 'Cheque',
        3 => 'Demand Draft'
    ];

    const SUBJECT_TYPE = [
        1 => 'Core',
        2 => 'Electives'
    ];

    const ATTENDANCE_TYPE = [
        0 => 'Absent',
        1 => 'Present'
    ];

    const TEMPLATE_MODULES = [
        1 => 'Attendance',
        2 => 'Exam Result',
        3 => 'Pre Admission',
        4 => 'Students Promotion'
    ];

    const TEMPLATE_TYPE = [
        1 => 'SMS',
        2 => 'EMAIL',
        3 => 'ID CARD',
        4 => 'Study Certificate',
        5 => 'Marksheet',
        6 => 'Admit Card'
    ];

    const TEMPLATE_USERS = [
        AppHelper::USER_TEACHER => "Employee",
        AppHelper::USER_STUDENT => "Student",
        AppHelper::USER_PARENTS => "Parents",
        0 => "System Users"
    ];

    const SMS_GATEWAY_LIST = [
        1 => 'Bulk SMS Route',
        2 => 'IT Solutionbd',
        3 => 'Zaman IT',
        4 => 'MIM SMS',
        5 => 'Twilio',
        6 => 'Doze Host',
        7 => 'MSG 91',
        8 => 'Text 160',
        9 => 'Log Locally',
    ];

    const VOICE_GATEWAY_LIST = [
        1 => 'SMS Gateway Hub'
    ];

    const LEAVE_TYPES = [
        1 => 'Casual leave (CL)',
        2 => 'Sick leave (SL)',
        3 => 'Undefined leave (UL)'
    ];

    const MARKS_DISTRIBUTION_TYPES = [
        1 => "Written",
        2 => "MCQ",
        3 => "SBA",
        4 => "Attendance",
        5 => "Assignment",
        6 => "Lab Report",
        7 => "Practical",
    ];

    const GRADE_TYPES = [
        1 => 'A+',
        2 => 'A',
        3 => 'A-',
        4 => 'B',
        5 => 'C',
        6 => 'D',
        7 => 'F',
    ];
    const PASSING_RULES = [1 => 'Over All', 2 => 'Individual', 3 => 'Over All & Individual' ];

	public static function totalFee($class_id, $type, $student){
		$query = FeeSetup::select((DB::RAW('IFNULL(sum(fee),0) as feeamount')))		
		->whereHas('class', function($query) use ($class_id){
			$query->where('class_id','=',$class_id);
		})
		->whereDoesntHave('excludedFees', function($query) use ($student){
			$query->where('student_id','=',$student->id);
		})
		->where('type',$type);
		if($type == AppHelper::TRANSPORT) {
			$query->where('zone', '=', $student->transport_zone);	
		}
		$fee = $query->first();
		return $fee->feeamount;
    }
    
	public static function getCollectedFeeList($academic_year, $sDate, $eDate, $types=array(), $class_id, $section_id, $feeitems=array(), $paginate=TRUE, $itemised=FALSE){
		$sids = array();
        $query = DB::table('students AS s');
        if($itemised){
            $query->select('s.name', 
                DB::raw('CONCAT(c.name , " ", sc.name) as class'),
                'fc.type', 's.id as student_id', 's.need_transport', 
                'c.id as class_id', 'sc.id as section_id',  'fc.fee_item as fee_item',
                DB::RAW('IFNULL(sum(fc.paidAmount),0) - IFNULL(fc.discount,0) as paidTotal'), 
                DB::RAW('IFNULL(fc.discount,0) as discount'), 'c.order');
        }else{
            $query->select('s.name', 
                DB::raw('CONCAT(c.name , " ", sc.name) as class'),
                'fc.type', 's.id as student_id', 's.need_transport', 
                'c.id as class_id', 'sc.id as section_id',
                DB::RAW('IFNULL(sum(fc.paidAmount),0) - IFNULL(fc.discount,0) as paidTotal'), 
                DB::RAW('IFNULL(fc.discount,0) as discount'), 'c.order');
        }
		$query->leftJoin('fee_collection As fc', function($join) {
				$join->on('s.id', '=', 'fc.student_id');
			})
			->join('registrations AS r', 's.id', '=', 'r.student_id')
			->join('i_classes AS c', 'c.id', '=', 'r.class_id')
            ->join('sections AS sc', 'sc.id', '=', 'r.section_id')
            ->where('fc.academic_year', '=', $academic_year);
            if(!empty($feeitems)){
				$query->whereIn('fc.fee_item', $feeitems);
            }
			if($sDate && $eDate){
				$query->whereDate('fc.payDate', '>=', date('Y-m-d H:i:s', strtotime($sDate)))
				->whereDate('fc.payDate', '<=', date('Y-m-d H:i:s', strtotime($eDate)));
			}
			if($class_id){
				$query->where('c.id', '=', $class_id);
			}
			if($section_id){
				$query->where('sc.id', '=', $section_id);
			}
			if(!empty($types)){
				$query->whereIn('fc.type', $types);
			}
        $query->whereNull('s.deleted_by');
        if($itemised){
            $query->groupBy(
                's.name', 'fc.type', 'c.name', 'sc.name', 's.id', 'c.id', 'sc.id', 'c.order', 's.need_transport', 'fc.fee_item', 'fc.discount'
            );
        }else{
            $query->groupBy(
                's.name', 'fc.type', 'c.name', 'sc.name', 's.id', 'c.id', 'sc.id', 'c.order', 's.need_transport', 'fc.discount'
            );
        }
        $query->orderBy('c.order');
                
        if($paginate) {
            $result =  $query->paginate(25);
        }else{
            $result =  $query->get();
        }

        if(!$itemised){
            $result->transform(function ($col) {
                $student = Student::where('id', $col->student_id)->get()->first();
                $totalfee = AppHelper::totalFee($col->class_id, $col->type, $student);
                $due = intval($totalfee) - (intval($col->paidTotal) + intval($col->discount));
                $col->payable = $totalfee;
                $col->due = $due;
                return $col;
            });
        }

		return $result;
	}

    public static function generateUserName($fullname=NULL, $student=TRUE){
        $i = 0;
        do {
            if(!$student) {
                $splitname = explode(" ", $fullname);
                $name = $splitname[0];
                $surname = count($splitname) > 1 ? $splitname[1] : '';
                $ex = ($i == 0) ? '' : $i;
                //to produce username mtutumlu for Murat Tutumlu
                $uname = strtolower(substr($name , 0, 3) . str_replace(array(' ', '.'), '', $surname)) . $ex;
            }else{
                $acYearId = '';
                $acYears = [];
                if(AppHelper::getInstituteCategory() != 'college') {
                    $settings = AppHelper::getAppSettings();
                    $acYearId = $settings['academic_year'];
                }
                $lastcount = User::withTrashed()->count();
                $settings = AppMeta::where('meta_key', 'institute_settings')->select('meta_key','meta_value')->first();
                $info = null;
                if($settings) {
                    $info = json_decode($settings->meta_value);
                }
                $inst_code = $info->short_name;
                $acYears = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
                $year = explode("-", $acYears[$acYearId]);
                $suffix = $year[0];
                $uname = $inst_code .''. $suffix .''. ($lastcount + $i);
            }
            $i++;
            $ucount = User::withTrashed()->where('username', $uname)->get();
            $ucount = $ucount->count();
        }while ($ucount > 0);

        return $uname;
    }

	/**
	 * Helper function to find match in an array of associative array
	 * @param array $array - Array to find match
	 * @param any $key - Key of the associative array
	 * @param any $val - Value to find
	 * @return Boolean
	 */
	public static function findMatch($array, $key, $val) {
		foreach ($array as $item)
			if (isset($item[$key]) && $item[$key] == $val)
				return true;
		return false;
	}
	/**
	 * Get specifc student
	 * @param id - Student ID
	 * @return Student
	 */
	public static function getStudentByID($id)	{
		$student= Student::select('*')->where('id', $id)->get()->first();
		return $student;
	}

    /**
     * Get students by name
     * @param q - Query string
     * @return array
     */
    public static function getStudentsByName($q, $page, $year) {
        $pagelimit = 20;
        $limit = $page * $pagelimit;
        $offset = $limit - $pagelimit; 
        $query = Student::select('id', 'name', 'father_name', 'mother_name', 'guardian')
            ->where('name', 'like', '%' . $q . '%')
            ->whereHas('registration', function($query) use ($year){
                if($year) {
                    $query->where('academic_year_id', $year);
                }
            })
            ->with(['registration' => function($query){
                $query->select("*")
				->with(['class' => function($query) {
					$query->select("id", "name");
				}])
				->with(['section' => function($query) {
					$query->select("id", "name");
				}]);
            }]);
        $studentcount = $query->count();
        $students = $query->offset($offset)->limit($limit)->get();
        
        $result = new \stdClass();
        $pagination = new \stdClass();
        $pagination->more = $studentcount > $limit;
        $result->results = $students;
        $result->pagination = $pagination;
        return $result;
    }
    /**
     * Get institution category for app settings
     * school or college
     * @return mixed
     */
    public static function getInstituteCategory() {

        $iCategory = env('INSTITUTE_CATEGORY', 'school');
        if($iCategory != 'school' && $iCategory != 'college'){
            $iCategory = 'school';
        }

        return $iCategory;
    }

    public static function getAcademicYear() {
        $settings = AppHelper::getAppSettings();
        return isset($settings['academic_year']) ? intval($settings['academic_year']) : 0;
    }

    /**
     * @return string
     */

    public static function getUserSessionHash()
    {
        /**
         * Get file sha1 hash for copyright protection check
         */
        $path = base_path() . '/resources/views/backend/partial/footer.blade.php';
        $contents = file_get_contents($path);
        $c_sha1 = sha1($contents);
        return substr($c_sha1, 0, 7);
    }

    public static function getShortName($phrase)
    {
        /**
         * Acronyms generator of a phrase
         */
        return preg_replace('~\b(\w)|.~', '$1', $phrase);
    }

    public static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function getJwtAssertion($private_key_file)
    {

        $json_file = file_get_contents($private_key_file);
        $info = json_decode($json_file);
        $private_key = $info->{'private_key'};

        //{Base64url encoded JSON header}
        $jwtHeader = self::base64url_encode(json_encode(array(
            "alg" => "RS256",
            "typ" => "JWT"
        )));

        //{Base64url encoded JSON claim set}
        $now = time();
        $jwtClaim = self::base64url_encode(json_encode(array(
            "iss" => $info->{'client_email'},
            "scope" => "https://www.googleapis.com/auth/analytics.readonly",
            "aud" => "https://www.googleapis.com/oauth2/v4/token",
            "exp" => $now + 3600,
            "iat" => $now
        )));

        $data = $jwtHeader.".".$jwtClaim;

        // Signature
        $Sig = '';
        openssl_sign($data,$Sig,$private_key,'SHA256');
        $jwtSign = self::base64url_encode($Sig);

        //{Base64url encoded JSON header}.{Base64url encoded JSON claim set}.{Base64url encoded signature}
        $jwtAssertion = $data.".".$jwtSign;
        return $jwtAssertion;
    }

    public static function getGoogleAccessToken($private_key_file)
    {

        $result = [
            'success' => false,
            'message' => '',
            'token' => null
        ];

        if (Cache::has('google_token')) {
            $result['token'] = Cache::get('google_token');
            $result['success'] = true;
            return $result;
        }

        if(!file_exists($private_key_file)){
            $result['message'] = 'Google json key file missing!';
            return $result;

        }

        $jwtAssertion = self::getJwtAssertion($private_key_file);

        try {

            $client = new Client([
                'base_uri' => 'https://www.googleapis.com',
            ]);
            $payload = [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwtAssertion
            ];

            $response = $client->request('POST', 'oauth2/v4/token', [
                'form_params' => $payload
            ]);

            $data = json_decode($response->getBody());
            $result['token'] = $data->access_token;
            $result['success'] = true;

            $expiresAt = now()->addMinutes(58);
            Cache::put('google_token', $result['token'], $expiresAt);

        } catch (RequestException $e) {
            $result['message'] = $e->getMessage();
        }


        return $result;

    }

    /**
     *
     *    Input any number in Bengali and the following function will return the English number.
     *
     */

    public static function en2bnNumber ($number)
    {
        $replace_array= array("১", "২", "৩", "৪", "৫", "৬", "৭", "৮", "৯", "০");
        $search_array= array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0");
        $en_number = str_replace($search_array, $replace_array, $number);

        return $en_number;
    }
    /**
     *
     *    Translate number according to application locale
     *
     */
    public static function translateNumber($text)
    {
        $locale = App::getLocale();
        if($locale == "bn"){
            $transText = '';
            foreach (str_split($text) as $letter){
                $transText .= self::en2bnNumber($letter);
            }
            return $transText;
        }
        return $text;
    }

    /**
     *
     *    Application settings fetch
     *
     */
    public static function getAppSettings($key=null){
        $appSettings = null;
        if (Cache::has('app_settings')) {
            $appSettings = Cache::get('app_settings');
        }
        else{
            $settings = AppMeta::select('meta_key','meta_value')->get();

            $metas = [];
            foreach ($settings as $setting){
                $metas[$setting->meta_key] = $setting->meta_value;
            }
            if(isset($metas['institute_settings'])){
                $metas['institute_settings'] = json_decode($metas['institute_settings'], true);
            }
            if(isset($metas['fee_reciept_prefix'])){
                $metas['fee_reciept_prefix'] = json_decode($metas['fee_reciept_prefix'], true);
            }
            if(isset($metas['fee_trans_zones'])){
                $metas['fee_trans_zones'] = json_decode($metas['fee_trans_zones'], true);
            }
            if(isset($metas['attendance_sessions'])){
                $metas['attendance_sessions'] = json_decode($metas['attendance_sessions'], true);
            }
            $appSettings = $metas;
            Cache::forever('app_settings', $metas);

        }

        if($key){
            return $appSettings[$key] ?? 0;
        }

        return $appSettings;
    }

    /*
    get current session data
    */
    public static function getCurrentSession(){ 
        $currentSession = null;
        $attendance_type = AppHelper::getAppSettings('attendance_type');
        if(!empty($attendance_type) && $attendance_type == 'session_attendance') {
            $attendance_sessions = AppHelper::getAppSettings('attendance_sessions');
            $get_current_time = Carbon::now();
            $current_time = $get_current_time->toTimeString();
            foreach($attendance_sessions as $value){
                $from = !empty($value['from']) ? \Carbon\Carbon::parse($value['from'])->format('H:i:m') : '0';
                $to = !empty($value['to']) ? \Carbon\Carbon::parse($value['to'])->format('H:i:m') : '0';
                if($from <= $current_time && $to >= $current_time) {
                    return $value;
                }
            }
        }
        return $attendance_type;
    } 

    /*
    get total present count for a day
    */
    public static function getotalPresentCount($date,$registration_id,$attendance_type='subject'){
        $count= null;
        $date = Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        $count = StudentAttendance::where(array('attendance_date'=>$date,'registration_id'=>$registration_id,'present'=>'1'))->where($attendance_type,'!=',null)->count();
        return $count;
    } 
    /**
     *
     *    site meta data settings fetch
     *
     */
    public static function getSiteMetas(){
        $siteMetas = null;
        if (Cache::has('site_metas')) {
            $siteMetas = Cache::get('site_metas');
        }
        else{

            $settings = SiteMeta::whereIn(
                'meta_key', [
                    'contact_address',
                    'contact_phone',
                    'contact_email',
                    'ga_tracking_id',
                ]
            )->get();

            $metas = [];
            foreach ($settings as $setting){
                $metas[$setting->meta_key] = $setting->meta_value;
            }
            $siteMetas = $metas;
            Cache::forever('site_metas', $metas);

        }

        return $siteMetas;
    }

    /**
     *
     *    Website settings fetch
     *
     */
    public static function getWebsiteSettings(){
        $webSettings = null;
        if (Cache::has('website_settings')) {
            $webSettings = Cache::get('website_settings');
        }
        else{
            $webSettings = SiteMeta::where('meta_key','settings')->first();
            Cache::forever('website_settings', $webSettings);

        }

        return $webSettings;
    }

    /**
     *
     *   up comming event fetch
     *
     */
    public static function getUpcommingEvent(){
        $event = null;
        if (Cache::has('upcomming_event')) {
            $event = Cache::get('upcomming_event');
        }
        else{
            $event = Event::whereDate('event_time','>=', date('Y-m-d'))->orderBy('event_time','asc')->take(1)->first();
            Cache::forever('upcomming_event', $event);

        }

        return $event;
    }

    /**
     *
     *   check is frontend website enabled
     *
     */
    public static function isFrontendEnabled(){
        // get app settings
        $appSettings = AppHelper::getAppSettings();
        if (isset($appSettings['frontend_website']) && $appSettings['frontend_website'] == '1') {
            return true;
        }

        return false;
    }

    /**
     * Create triggers
     * This function only used on shared hosting deployment
     */
    public static function createTriggers(){

        // class history table trigger
        DB::unprepared("DROP TRIGGER IF EXISTS i_class__ai;");
        DB::unprepared("DROP TRIGGER IF EXISTS i_class__au;");
        //create after insert trigger
        DB::unprepared("CREATE TRIGGER i_class__ai AFTER INSERT ON i_classes FOR EACH ROW
    INSERT INTO i_class_history SELECT 'insert', NULL, d.* 
    FROM i_classes AS d WHERE d.id = NEW.id;");
        DB::unprepared("CREATE TRIGGER i_class__au AFTER UPDATE ON i_classes FOR EACH ROW
    INSERT INTO i_class_history SELECT 'update', NULL, d.*
    FROM i_classes AS d WHERE d.id = NEW.id;");

        // section history table trigger
        DB::unprepared("DROP TRIGGER IF EXISTS section__ai;");
        DB::unprepared("DROP TRIGGER IF EXISTS section__au;");
        //create after insert trigger
        DB::unprepared("CREATE TRIGGER section__ai AFTER INSERT ON sections FOR EACH ROW
    INSERT INTO section_history SELECT 'insert', NULL, d.* 
    FROM sections AS d WHERE d.id = NEW.id;");
        DB::unprepared("CREATE TRIGGER section__au AFTER UPDATE ON sections FOR EACH ROW
    INSERT INTO section_history SELECT 'update', NULL, d.*
    FROM sections AS d WHERE d.id = NEW.id;");

        //subject history table trigger
        DB::unprepared("DROP TRIGGER IF EXISTS subject_ai;");
        DB::unprepared("DROP TRIGGER IF EXISTS subject_au;");
        //create after insert trigger
        DB::unprepared("CREATE TRIGGER subject_ai AFTER INSERT ON subjects FOR EACH ROW
    INSERT INTO subject_history SELECT 'insert', NULL, d.* 
    FROM subjects AS d WHERE d.id = NEW.id;");
        DB::unprepared("CREATE TRIGGER subject_au AFTER UPDATE ON subjects FOR EACH ROW
    INSERT INTO subject_history SELECT 'update', NULL, d.*
    FROM subjects AS d WHERE d.id = NEW.id;");
    }



    /**
     *
     *    Application Permission
     *
     */
    public static function getPermissions(){

        if (Cache::has('app_permissions')) {
            $permissions = Cache::get('app_permissions');
        }
        else{
            try{

                $permissions = Permission::get();
                Cache::forever('app_permissions', $permissions);

            } catch (\Illuminate\Database\QueryException $e) {
                $permissions = collect();
            }
        }

        return $permissions;
    }

    /**
     *
     *    Application users By group
     *
     */
    public static function getUsersByGroup($groupId){

        try{

            $users = User::rightJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
                ->where('user_roles.role_id', $groupId)
                ->select('users.id')
                ->get();

        } catch (\Illuminate\Database\QueryException $e) {
            $users = collect();
        }


        return $users;
    }

    /**
     *
     *    Send notification to users
     *
     */
    public static function sendNotificationToUsers($users, $type, $message){
        Notification::send($users, new UserActivity($type, $message));

        return true;
    }

    /**
     *
     *    Send notification to Admin users
     *
     */
    public static function sendNotificationToAdmins($type, $message){
        $admins = AppHelper::getUsersByGroup(AppHelper::USER_ADMIN);
        return AppHelper::sendNotificationToUsers($admins, $type, $message);
    }

    /**
     *  Send notification to student via sms
     * @param $students
     * @param $date
     * @return bool
     */
    public static function sendFeePaymentForStudentViaSMS($student_id, $type, $paid, $balance, $date) {

        $gateway = AppMeta::where('id', AppHelper::getAppSettings('fee_payment_gateway'))->first();
        $gateway = json_decode($gateway->meta_value);

        //compile message
        $template = Template::where('id', AppHelper::getAppSettings('fee_payment_template'))->first();

        //pull student
        $student = Registration::where('student_id', $student_id)
            ->where('status', AppHelper::ACTIVE)
            ->with(['class' => function($query) {
                $query->select('name','id');
            }])
            ->with(['section' => function($query) {
                $query->select('name','id');
            }])
            ->with('student')
            ->select('id','regi_no','roll_no','student_id','class_id','section_id')
            ->first();
        
        $keywords['paid_amount'] = strval($paid);
        $keywords['fee_type'] = $type;
        $keywords['balance'] = strval($balance);
        $keywords['regi_no'] = $student->regi_no;
        $keywords['roll_no'] = $student->roll_no;
        $keywords['class'] = $student->class->name;
        $keywords['section'] = $student->section->name;
        $studentArray = $student->toArray();
        $keywords = array_merge($keywords ,$studentArray['student']);
        $keywords['date'] = $date;

        $message = $template->content;
        foreach ($keywords as $key => $value) {
            if(is_string($value)){
                        $message = str_replace('{{' . $key . '}}', $value, $message);
            }
        }

        $cellNumber = AppHelper::validateIndianCellNo($studentArray['student']['father_phone_no']);
        if(!$cellNumber) {
            $cellNumber = AppHelper::validateIndianCellNo($studentArray['student']['mother_phone_no']);
            if(!$cellNumber) {
                $cellNumber = AppHelper::validateIndianCellNo($studentArray['student']['guardian_phone_no']);
                if(!$cellNumber) {
                    $cellNumber = AppHelper::validateIndianCellNo($studentArray['student']['phone_no']);
                }
            }
        }

        $users = User::where('id', $student->student->user_id)
                ->select('id')
                ->get();
        AppHelper::sendNotificationToUsers($users, 'info', $message);
        if($cellNumber){
            //send sms via helper
            $smsHelper = new SmsHelper($gateway);
            $res = $smsHelper->sendSms(array($cellNumber), $message);

        }
        else{
            Log::channel('smsLog')->error("Invalid Cell No! ".$studentArray['student']['father_phone_no']);
        }

        return true;
    }

    /**
     *  Send notification to student via sms
     * @param $students
     * @param $date
     * @return bool
     */
    public static function sendAbsentNotificationForStudentViaSMS($studentIds, $date) {

        $attendance_date = date('d/m/Y', strtotime($date));
        $gateway = AppMeta::where('id', AppHelper::getAppSettings('student_attendance_gateway'))->first();
        $gateway = json_decode($gateway->meta_value);

        //pull students
        $students = Registration::whereIn('id', $studentIds)
            ->where('status', AppHelper::ACTIVE)
            ->with(['class' => function($query) {
                $query->select('name','id');
            }])
            ->with(['section' => function($query) {
                $query->select('name','id');
            }])
            ->with('student')
            ->select('id','regi_no','roll_no','student_id','class_id','section_id')
            ->get();

        //compile message
        $template = Template::where('id', AppHelper::getAppSettings('student_attendance_template'))->first();

        foreach ($students as $student){
            $keywords['regi_no'] = $student->regi_no;
            $keywords['roll_no'] = $student->roll_no;
            $keywords['class'] = $student->class->name;
            $keywords['section'] = $student->section->name;
            $studentArray = $student->toArray();
            $keywords = array_merge($keywords ,$studentArray['student']);
            $keywords['date'] = $attendance_date;

            $message = $template->content;
            foreach ($keywords as $key => $value) {
				if(is_string($value)){
					$message = str_replace('{{' . $key . '}}', $value, $message);
				}
            }

            $cellNumber = AppHelper::validateIndianCellNo($studentArray['student']['father_phone_no']);
            if(!$cellNumber) {
                $cellNumber = AppHelper::validateIndianCellNo($studentArray['student']['mother_phone_no']);
                if(!$cellNumber) {
                   $cellNumber = AppHelper::validateIndianCellNo($studentArray['student']['guardian_phone_no']);
                   if(!$cellNumber) {
                      $cellNumber = AppHelper::validateIndianCellNo($studentArray['student']['phone_no']);
                   }
           	}
	    }

            if($cellNumber){

                //send sms via helper
                $smsHelper = new SmsHelper($gateway);
                $res = $smsHelper->sendSms(array($cellNumber), $message);

            }
            else{
                Log::channel('smsLog')->error("Invalid Cell No! ".$studentArray['student']['father_phone_no']);
            }
        }

        return true;
    }

    /**
     *  Send notification to employee via sms
     * @param $students
     * @param $date
     * @return bool
     */
    public static function sendAbsentNotificationForEmployeeViaSMS($employeeIds, $date) {

        $attendance_date = date('d/m/Y', strtotime($date));
        $gateway = AppMeta::where('id', AppHelper::getAppSettings('employee_attendance_gateway'))->first();
        $gateway = json_decode($gateway->meta_value);

        //pull employee
        $employees = Employee::whereIn('id', $employeeIds)
            ->where('status', AppHelper::ACTIVE)
            ->with('user')
            ->select('id','name','designation','dob','gender','religion','email','phone_no','address','joining_date','user_id')
            ->get();

        //compile message
        $template = Template::where('id', AppHelper::getAppSettings('employee_attendance_template'))->first();

        foreach ($employees as $employee){

            $keywords = $employee->toArray();
            $keywords['date'] = $attendance_date;
            $keywords['username'] = $keywords['user']['username'];
            unset($keywords['user']);

            $message = $template->content;
            foreach ($keywords as $key => $value) {
                $message = str_replace('{{' . $key . '}}', $value, $message);
            }

            $cellNumber = AppHelper::validateIndianCellNo($employee->phone_no);

            if($cellNumber){

                //send sms via helper
                $smsHelper = new SmsHelper($gateway);
                $res = $smsHelper->sendSms(array($cellNumber), $message);

            }
            else{
                Log::channel('smsLog')->error("Invalid Cell No! ".$employee->phone_no);
            }
        }

        return true;
    }


    /**
     * @param $number
     * @return bool|mixed|string
     */
    public static function validateIndianCellNo($number) {
        if (preg_match('/^[0-9]{10}+$/', $number)) {
            return $number;
        }

        return false;
    }

    /**
     * @param $number
     * @return bool|mixed|string
     */
    public static function validateCellNo($number) {
        if (preg_match('/^[0-9]{10}+$/', $number)) {
            return '91'.$number;
        } elseif(preg_match('/^[0-9]{12}+$/', $number)) {
            $countryCode = substr($number, 0, 2);
            if($countryCode == '91') {
                return $number;
            }
        }

        return false;
    }


    public static function isLineValid($lineContent) {
        // remove utf8 bom identify characters
        //clear invalid UTF8 characters
        $lineContent  = iconv("UTF-8","ISO-8859-1//IGNORE",$lineContent);

        if(!strlen($lineContent)){
            return 0;
        }


        $lineSplits = explode(':', $lineContent);
        if(count($lineSplits) >= 4){
            return 1;
        }


        $lineSplits = preg_split("/\s+/", $lineContent);
        if(count($lineSplits)){
            return 2;
        }

        return 0;


    }

    public static  function parseRow($lineContent, $fileFormat){
        // remove utf8 bom identify characters
        //clear invalid UTF8 characters
        $lineContent  = iconv("UTF-8","ISO-8859-1//IGNORE",$lineContent);

        if(!strlen($lineContent)){
            return [];
        }

        $data = [];
        if($fileFormat === 1){
            $lineSplits = explode(':', $lineContent);
            $id = trim(ltrim($lineSplits[1], '0'));
            //only for student id , remove teacher ids
            if(strlen($id) > 2){
                $data = [
                    'date' => $lineSplits[2],
                    'id' => $id,
                    'time' => trim($lineSplits[3]),
                ];
            }

        }

        if($fileFormat === 2){
            $lineSplits = preg_split("/\s+/", $lineContent);
            $id = trim($lineSplits[0]);
            //only for student id , remove teacher ids
            if(strlen($id) > 2){
                $aDate = str_replace('-','',$lineSplits[1]);
                $aTime = str_replace(':','',$lineSplits[2]);
                $data = [
                    'date' => $aDate,
                    'id' => $id,
                    'time' => $aTime,
                ];
            }
        }

        return $data;

    }

    public static  function parseRowForEmployee($lineContent, $fileFormat){
        // remove utf8 bom identify characters
        //clear invalid UTF8 characters
        $lineContent  = iconv("UTF-8","ISO-8859-1//IGNORE",$lineContent);

        if(!strlen($lineContent)){
            return [];
        }

        $data = [];
        if($fileFormat === 1){
            $lineSplits = explode(':', $lineContent);
            $id = trim(ltrim($lineSplits[1], '0'));
            //only for employee id , remove student ids
            if(strlen($id) == 2){
                $data = [
                    'date' => $lineSplits[2],
                    'time' => trim($lineSplits[3]),
                    'id' => str_pad($id, 10, "0", STR_PAD_LEFT),
                ];
            }

        }

        if($fileFormat === 2){
            $lineSplits = preg_split("/\s+/", $lineContent);
            $id = trim($lineSplits[0]);
            //only for employee id , remove student ids
            if(strlen($id) == 2){
                $aDate = str_replace('-','',$lineSplits[1]);
                $aTime = str_replace(':','',$lineSplits[2]);

                $data = [
                    'date' => $aDate,
                    'time' => $aTime,
                    'id' => str_pad($id, 10, "0", STR_PAD_LEFT)
                ];
            }
        }

        return $data;

    }


    public static function getIdcardBarCode($code) {
        $generator = new BarcodeGeneratorPNG();
        $imageString = 'data:image/png;base64,' . base64_encode($generator->getBarcode($code, $generator::TYPE_CODE_128,2,25));

        return $imageString;

    }

    /**
     * @param Carbon $start_date
     * @param Carbon $end_date
     * @param bool $checkWeekends
     * @param array $weekendDays
     * @return array
     */
    public static function generateDateRangeForReport(Carbon $start_date, Carbon $end_date, $checkWeekends=false, $weekendDays=[], $exludeWeekends=false)
    {


        $dates = [];
        for($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {
            if($checkWeekends){
                $weekend = 0;
                if(in_array($date->dayOfWeek, $weekendDays)){
                    $weekend = 1;
                }

                if($exludeWeekends){
                    if(!$weekend){
                        $dates[$date->format('Y-m-d')] = intval($date->format('d'));
                    }
                    continue;
                }

                $dates[$date->format('Y-m-d')] = [
                    'day' => intval($date->format('d')),
                    'weekend' => $weekend
                ];

            }
            else{
                $dates[$date->format('Y-m-d')] = intval($date->format('d'));
            }

        }

        return $dates;
    }


    /**
     * Process student entry marks and
     * calculate grade point
     *
     * @param $examRule collection
     * @param $gradingRules array
     * @param $distributeMarksRules array
     * @param $strudnetMarks array
     */
    public static function processMarksAndCalculateResult($examRule, $gradingRules, $distributeMarksRules, $studentMarks) {
        $totalMarks = 0;
        $isFail = false;
        $isInvalid = false;
        $message = "";

        foreach ($studentMarks as $type => $marks){
            $marks = floatval($marks);
            $totalMarks += $marks;

            // AppHelper::PASSING_RULES
            if(in_array($examRule->passing_rule, [2,3])){
                if($marks > $distributeMarksRules[$type]['total_marks']){
                    $isInvalid = true;
                    $message = AppHelper::MARKS_DISTRIBUTION_TYPES[$type]. " marks is too high from exam rules marks distribution!";
                    break;
                }

                if($marks < $distributeMarksRules[$type]['pass_marks']){
                    $isFail = true;
                }
            }
        }

        //fraction number make ceiling
        $totalMarks = ceil($totalMarks);

        // AppHelper::PASSING_RULES
        if(in_array($examRule->passing_rule, [1,3])){
            if($totalMarks < $examRule->over_all_pass){
                $isFail = true;
            }
        }

        if($isFail){
            $grade = 'F';
            $point = 0.00;

            return [$isInvalid, $message, $totalMarks, $grade, $point];
        }

        [$grade, $point] = AppHelper::findGradePointFromMarks($gradingRules, $totalMarks);

        return [$isInvalid, $message, $totalMarks, $grade, $point];

    }

    public static function findGradePointFromMarks($gradingRules, $marks) {
        $grade = 'F';
        $point = 0.00;
        foreach ($gradingRules as $rule){
            if ($marks >= $rule->marks_from && $marks <= $rule->marks_upto){
                $grade = AppHelper::GRADE_TYPES[$rule->grade];
                $point = $rule->point;
                break;
            }
        }
        return [$grade, $point];
    }

    public static function findGradeFromPoint($point, $gradingRules) {
        $grade = 'F';

        foreach ($gradingRules as $rule){
            if($point >= floatval($rule->point)){
                $grade = AppHelper::GRADE_TYPES[$rule->grade];
                break;
            }
        }

        return $grade;

    }

    public static function isAndInCombine($subject_id, $rules){
        $isCombine = false;
        foreach ($rules as $subject => $data){
            if($subject == $subject_id && $data['combine_subject_id']){
                $isCombine = true;
                break;
            }

            if($data['combine_subject_id'] == $subject_id){
                $isCombine = true;
                break;
            }
        }

        return $isCombine;
    }

    public static function processCombineSubjectMarks($subjectMarks, $pairSubjectMarks, $subjectRule, $pairSubjectRule){
        $pairFail = false;

        $combineTotalMarks = ($subjectMarks->total_marks + $pairSubjectMarks->total_marks);

        if($subjectRule['total_exam_marks'] == $pairSubjectRule['total_exam_marks']){
            //dividing factor
            $totalMarks = ($combineTotalMarks/2);
        }
        else{
            //if both subject exam marks not same then it must be 2:1 ratio
            //Like: subject marks 100 pair subject marks 50
            $totalMarks = ($combineTotalMarks/ 1.5);
        }

        //fraction number make ceiling
        $totalMarks = ceil($totalMarks);

        $passingRule = $subjectRule['passing_rule'];
        // AppHelper::PASSING_RULES
        if(in_array($passingRule, [1,3])){
            if($totalMarks < $subjectRule['over_all_pass']){
                $pairFail = true;
            }
        }

        //if any subject absent then its fail
        if($subjectMarks->present == 0 || $pairSubjectMarks->present == 0){
            $pairFail = true;
        }

        // AppHelper::PASSING_RULES
        if(!$pairFail && in_array($passingRule, [2,3])){

            //acquire marks
            $combineDistributedMarks = [];
            foreach (json_decode($subjectMarks->marks) as $key => $distMarks){
                $combineDistributedMarks[$key] = floatval($distMarks);

            }

            foreach (json_decode($pairSubjectMarks->marks) as $key => $distMarks){
                $combineDistributedMarks[$key] += floatval($distMarks);

            }


            //passing rules marks
            $combineDistributeMarks = [];
            foreach ($subjectRule['marks_distribution'] as $distMarks){
                $combineDistributeMarks[$distMarks->type] = floatval($distMarks->pass_marks);
            }

            foreach ($pairSubjectRule['marks_distribution'] as $key => $distMarks){
                $combineDistributeMarks[$distMarks->type] += floatval($distMarks->pass_marks);

            }

            //now check for pass
            foreach ($combineDistributeMarks as $key => $value){
                if($combineDistributedMarks[$key] < $value){
                    $pairFail = true;
                }
            }

        }


        return [$pairFail, $combineTotalMarks, $totalMarks];

    }

    /**
     * @param $number integer
     * @return string
     */
    public static function convertNumberToNumberRankingWord($number) {
        $rankWord = 'TH';

        if($number == 1){
            $rankWord = "ST";
        }
        else if($number == 2) {
            $rankWord = "ND";
        }else if($number == 3) {
            $rankWord = "RD";
        }

        return strval($number).$rankWord;

    }
        /*
     * Homework Submission Button
     */
    public static function getHomeworkSubmissionStatus($homeworkID) {
        $studentID = DB::table('students')->select('id')->where('user_id', auth()->user()->id)->first();
        $homeworkSubmission = DB::table('homework_submissions')->select('status')->where('student_id', '=', $studentID->id)->where('homework_id', '=', $homeworkID)->first();
        if(!empty($homeworkSubmission)) {
            if($homeworkSubmission->status == 'pending') {
                $disabledBtn = true;
                $buttonText = 'Submit';
            } elseif($homeworkSubmission->status == 'incomplete') {
                $disabledBtn = false;
                $buttonText = 'Resubmit';
            } elseif($homeworkSubmission->status == 'complete') {
                $disabledBtn = true;
                $buttonText = 'Complete';
            }
            $submissionStatus = $homeworkSubmission->status;
        } else {
            $disabledBtn = false;
            $buttonText = 'Submit';
            $submissionStatus = 'Submission Pending';
        }
        return array('disabled' => $disabledBtn, 'buttonText' => $buttonText, 'submissionStatus' => $submissionStatus);
    }

    /*
     * Homework Notifications for Teachers/Students
     */
    public static function notifyUsers($userIDs, $message, $type = 'info') {
        $users = User::select('users.id')->whereIn('id', explode(',', $userIDs))->get();
        $nothing = AppHelper::sendNotificationToUsers($users, $type, $message);
    }

    /*
     * Return Teacher specific classes, sections, subjects
     */
    public static function getTeacherClasses($teacherID) {
        $teacherClasses = DB::table('subjects')->selectRaw('GROUP_CONCAT(class_id) as classIDs')->where('teacher_id', $teacherID)->first();
        $teacherClasses = explode(',', $teacherClasses->classIDs);
        return $teacherClasses;
    }
    public static function getTeacherSubjects($teacherID) {
        $teacherSubjects = DB::table('subjects')->selectRaw('GROUP_CONCAT(id) as subjectIDs')->where('teacher_id', $teacherID)->first();
        $teacherSubjects = explode(',', $teacherSubjects->subjectIDs);
        return $teacherSubjects;
    }

    /*
    return subeject specific classes
    */
    public static function getSubjectByClass($class_id) {
        $subjects = Subject::select('subjects.id','name')->where('class_id', $class_id)->get()->toArray();
        return (!empty($subjects)) ? $subjects : [];
    }
    /*
    return subeject by id
    */
    public static function getSubjectById($sub_id) {
        $subjects = Subject::select('name')->where('id', $sub_id)->first();
        return (!empty($subjects)) ? $subjects : [];
    }

    /*
     * Return homework submission attachment url
     */
    public static function getAttachmentURL($homeworkSubmissionID) {
        $homeworkSubmission = HomeworkSubmission::find($homeworkSubmissionID);
        return $homeworkSubmission->getFirstMediaUrl(config('app.name').'/homework_submissions/');
    }

    /*
     * Return S3 URL for model
     */
    public static function getS3URL($modelType, $id) {
        if($modelType == 'student') {
            $model = Student::find($id);
            $path = config('app.name').'/students/';
        } elseif($modelType == 'employee') {
            $model = Employee::find($id);
            $path = config('app.name').'/employee/';
        } elseif($modelType == 'employee_signature') {
            $model = Employee::find($id);
            $path = config('app.name').'/employee/signature/';
        }

        return $model->getFirstMediaUrl($path);
    }

    /*
     * Check Pre Admission Availability:: Frontend
     */
    public static function checkPreAdmission() {
        $return = true;
        $pre_admission_start_date = AppMeta::where('meta_key', 'pre_admission_start_date')->select('meta_value')->first();
        $pre_admission_end_date = AppMeta::where('meta_key', 'pre_admission_end_date')->select('meta_value')->first();
        if(empty($pre_admission_start_date->meta_value) || empty($pre_admission_end_date->meta_value)) {
            $return = false;
        } else {
            $pre_admission_start_date = $pre_admission_start_date->meta_value;
            $pre_admission_end_date = $pre_admission_end_date->meta_value;
            $currentDate = date('Y-m-d');
            if($pre_admission_start_date > $currentDate || $pre_admission_end_date < $currentDate) {
                $return = false;
            }
        }
        return $return;
    }

    /*
     * Return Fee Data
     */
    public static function feeTypeTotal($class, $sid, $year=NULL) {
        $query = FeeSetup::select('type', (DB::RAW('IFNULL(sum(fee),0) as feeamount')))
        ->whereHas('class', function($query) use ($class){
            $query->where('class_id','=',$class);
        })
        ->whereDoesntHave('excludedFees', function($query) use ($sid){
            $query->where('student_id','=',$sid);
        })
        ->groupBy('type')
        ->pluck('feeamount', 'type');
        $fee = $query->all();

        $student = AppHelper::getStudentByID($sid);
        if($student->transport_zone) {
            $query= FeeSetup::select((DB::RAW('IFNULL(sum(fee),0) as feeamount')))
                ->where('type','=', AppHelper::TRANSPORT)
                ->whereHas('class', function($query) use ($class){
                    $query->where('class_id','=',$class);
                })
                ->where('zone', '=', $student->transport_zone);
                $trans = $query->first();
                $fee[AppHelper::TRANSPORT] = $trans->feeamount;
        }else{
            $fee[AppHelper::TRANSPORT] = 0.00;
        }

        $default_academic = AppHelper::getAcademicYear();
        $year = $year ? $year : $default_academic;
        
        $paid = FeeCol::select('type', (DB::RAW('IFNULL(sum(paidAmount),0) as paidamount')))
        ->where('class_id',$class)
        ->where('student_id', $sid)
        ->where('academic_year', $year)
        ->groupBy('type')
        ->pluck('paidamount', 'type')->all();

        $paid[AppHelper::MONTHLYID] = 'NA';

        return ['fee' => $fee, 'paid' => $paid];
    }

    public static function getAllStorageDrives() {
        $is_allowed_s3fileupload = AppHelper::getAppSettings('allow_s3upload');
        $disks = [
            'local' => [
                'name' => 'Local',
                'enabled' => TRUE
            ],
            'gdrive' => [
                'name' => 'Google Drive',
                'enabled' => TRUE
            ],
            's3' => [
                'name' => 'S3',
                'enabled' => $is_allowed_s3fileupload ? TRUE : FALSE
            ]
        ];
        return $disks;
    }

}
