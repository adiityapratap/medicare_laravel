<?php

namespace App\Http\Controllers\Backend;

use \stdClass;
use \DateTime;
use App\AcademicYear;
use App\AppMeta;
use App\Http\Helpers\AppHelper;
use App\Registration;
use App\Template;
use App\Bus;
use App\BusZones;
use App\BusAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Exception;
use Log;

class BusAttendanceController extends Controller
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
        if($request->ajax() && $request->query->get('student_id', 0)){
                $id = $request->query->get('student_id', 0);
                $attendances = BusAttendance::where('registration_id', $id)
                    ->select('attendance_date', 'present','registration_id')
                    ->orderBy('attendance_date', 'asc')
                    ->get();
                return response()->json($attendances);

        }

        // get query parameter for filter the fetch
        $bus_id = $request->query->get('bus',0);
        $zone_id = $request->query->get('zone',0);
        $acYear = $request->query->get('academic_year',0);
        $attendance_date = $request->query->get('attendance_date',date('d/m/Y'));
        //if its college then have to get those academic years
        $academic_years = [];
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }
        else{

            $acYear = $request->query->get('academic_year',AppHelper::getAcademicYear());
        }



        //if its a ajax request that means come from attendance add exists checker
        if($request->ajax()){
            $attendances = $this->getAttendanceByFilters($bus_id, $zone_id, $acYear, $attendance_date, true);
            return response()->json($attendances);
        }


        $buses = Bus::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');
        $zones = [];

        //now fetch attendance data
        $attendances = [];
        if($bus_id && $acYear && strlen($attendance_date) >= 10) {
            $att_date = Carbon::createFromFormat('d/m/Y',$attendance_date)->toDateString();
            $attendances = Registration::where('academic_year_id', $acYear)
                ->where('status', AppHelper::ACTIVE)
                ->whereHas('student', function($query) use($zone_id) {
                    $query->where('transport_zone', $zone_id);
                })
                ->with(['student' => function ($query) {
                    $query->select('name','id');
                }])
                ->with(['busAttendanceSingleDay' => function ($query) use($att_date, $bus_id, $acYear) {
                    $query->select('id','present','registration_id','in_time')
                        ->where('academic_year_id', $acYear)
                        ->where('bus_id', $bus_id)
                        ->whereDate('attendance_date', $att_date);
                }])
                ->whereHas('busAttendance' , function ($query) use($att_date, $bus_id, $acYear) {
                    $query->select('id','registration_id')
                        ->where('academic_year_id', $acYear)
                        ->where('bus_id', $bus_id)
                        ->whereDate('attendance_date', $att_date);
                })
                ->select('id','regi_no','roll_no','student_id')
                ->orderBy('roll_no','asc')
                ->get();

            $zones = AppHelper::getAppSettings('fee_trans_zones');
        }

        return view('backend.attendance.bus.list', compact(
            'academic_years',
            'buses',
            'zones',
            'acYear',
            'bus_id',
            'zone_id',
            'attendance_date',
            'attendances'
        ));

    }



    public function attendenceSummary() {
        //if its college then have to get those academic years
        $academic_years = [];
        $today = new DateTime('now');
        $attendance_date = date_format($today, 'd/m/Y');
        // if(AppHelper::getInstituteCategory() == 'college') {
        //     $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        // }else{
        $acYear = AppHelper::getAcademicYear();
        // }
        // Build class list
        $buslist = [];
        $buses = Bus::where('status', AppHelper::ACTIVE)
            ->with(['zones' => function($query) {
                $query->select('*')->orderBy('zone');
             }])
            ->orderBy('order','asc')
            ->get();
        $zone_list = AppHelper::getAppSettings('fee_trans_zones');
        
        foreach($buses as $bus) {
            $bus_id = $bus->id;
            foreach($bus->zones as $att_zone) {
                $zone_id = $att_zone->zone;
                $zone = $zone_list[$zone_id];
                if(!isset($buslist[$bus_id])) {
                    $buslist[$bus_id] = new stdClass();
                }
                $buslist[$bus_id]->{$zone_id} = new stdClass();
                $buslist[$bus_id]->{$zone_id}->busname = $bus->name;
                $buslist[$bus_id]->{$zone_id}->zone = $zone;
                $buslist[$bus_id]->{$zone_id}->htmlclass = filter_var($zone, FILTER_SANITIZE_NUMBER_INT);

                //Now get the student count
                $buslist[$bus_id]->{$zone_id}->students = Registration::where('academic_year_id', $acYear)
                    ->whereHas('student', function($query) use($zone_id) {
                        $query->where('transport_zone', $zone_id);
                    })
                    ->where('status', AppHelper::ACTIVE)
                    ->count();

                //now fetch attendance data
                if($bus_id && $zone_id && $acYear && strlen($attendance_date) >= 10) {
                    $att_date = Carbon::createFromFormat('d/m/Y',$attendance_date)->toDateString();
                    $buslist[$bus_id]->{$zone_id}->present = Registration::where('academic_year_id', $acYear)
                         ->where('status', AppHelper::ACTIVE)
                         ->whereHas('student', function($query) use($zone_id) {
                            $query->where('transport_zone', $zone_id);
                         })
                         ->whereHas('busAttendanceSingleDay', function($query) use($att_date, $bus_id, $acYear) {
                            $query->select('id')
                                ->where('academic_year_id', $acYear)
                                ->where('bus_id', $bus_id)
                                ->where('present', AppHelper::ACTIVE)
                                ->whereDate('attendance_date', $att_date);
                         })
                         ->select('id')
                         ->count();
                    $buslist[$bus_id]->{$zone_id}->recorded = $this->getAttendanceByFilters($bus_id, $zone_id, $acYear, $attendance_date, true);
                }
            }
        }
        
        return view('backend.attendance.bus.summary', compact(
            'buslist',
            'attendance_date'
        ));
    }


    private function getAttendanceByFilters($bus_id, $zone_id, $acYear, $attendance_date, $isCount = false) {
        $att_date = Carbon::createFromFormat('d/m/Y',$attendance_date)->toDateString();
       return $attendances = Registration::where('academic_year_id', $acYear)
            ->where('status', AppHelper::ACTIVE)
            ->whereHas('student', function($query) use($zone_id) {
               $query->where('transport_zone', $zone_id);
               $query->select('name','id');
            })
            ->whereHas('busAttendance' , function ($query) use($att_date, $bus_id, $acYear) {
                $query->select('id','registration_id')
                    ->where('academic_year_id', $acYear)
                    ->where('bus_id', $bus_id)
                    ->whereDate('attendance_date', $att_date);
            })
            ->select('id','regi_no','roll_no','student_id')
            ->orderBy('roll_no','asc')
            ->CountOrGet($isCount);
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
        $bus_name = '';
        $zone_name = '';
        $academic_years = [];
        $attendance_date = date('d/m/Y');
        $acYear = null;
        $bus_id = null;
        $zone_id = null;
        $metas = null;
        $zones = AppHelper::getAppSettings('fee_trans_zones');

        if(AppHelper::getInstituteCategory() == 'college') {
            $acYear = $request->get('academic_year_id', 0);
        }
        else{
            $acYear = AppHelper::getAcademicYear();
        }

        if ($request->isMethod('get')) {
            $bus_id = $request->query->get('bus',0);
            $zone_id = $request->query->get('zone',0);
            $attendance_date = $request->query->get('attendance_date', '');
        }
        if ($request->isMethod('post')) {
            $bus_id = $request->get('bus_id',0);
            $zone_id = $request->get('zone_id',0);
            $attendance_date = $request->get('attendance_date','');
        }
        if($bus_id && $zone_id && $acYear && strlen($attendance_date) >= 10) {
            $attendances = $this->getAttendanceByFilters($bus_id, $zone_id, $acYear, $attendance_date, true);
            if($attendances){
                return redirect()->route('busrecord.create')->with("error","Attendance already exists!");
            }

            $students = Registration::whereHas('info', function($query) use($zone_id) {
                $query->where('transport_zone', $zone_id);
             })->with(['info'=> function($query) use($zone_id) {
                $query->where('transport_zone', $zone_id);
                $query->select('*')
                ->orderBy('name', 'asc');
             }])
                ->where('academic_year_id', $acYear)
				->select( 'regi_no', 'roll_no', 'id','student_id')
                ->get()
				->sortBy(function($studentInfo, $key) {
				  return $studentInfo->info->name;
                });
                
                $settings = AppMeta::select('meta_key','meta_value')->get();

                $metas = [];
                foreach ($settings as $setting){
                    $metas[$setting->meta_key] = $setting->meta_value;
                }
                if(isset($metas['shift_data'])) {
                    $shiftData = json_decode($metas['shift_data'], true);
                    $formatedShiftData = [];
                    foreach ($shiftData as $shift => $times){
                        $formatedShiftData[$shift] = [
                            'start' => Carbon::parse($times['start'])->format('h:i a'),
                            'end' => Carbon::parse($times['end'])->format('h:i a')
                        ];
                    }

                    $metas['shift_data'] = $formatedShiftData;
                }

            $busInfo = Bus::where('status', AppHelper::ACTIVE)
                ->where('id', $bus_id)
                ->first();
            $bus_name = $busInfo->name;
            $zone_name = $zones[$zone_id];


            if(AppHelper::getInstituteCategory() == 'college') {
                $acYearInfo = AcademicYear::where('status', '1')->where('id', $acYear)->first();
                $academic_year = $acYearInfo->title;
            }
        }

        $buses = Bus::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

        //if its college then have to get those academic years
        if(AppHelper::getInstituteCategory() == 'college') {
            $academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
        }

        // return $students;
        // print('<br/>');exit;
        return view('backend.attendance.bus.add', compact(
            'academic_years',
            'buses',
            'zones',
            'students',
            'metas',
            'bus_name',
            'academic_year',
            'zone_name',
            'attendance_date',
            'bus_id',
            'zone_id',
            'acYear'
        ));

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //validate form
        $messages = [
            'registrationIds.required' => 'This zone has no students!',
            'inTime.required' => 'In time missing!',
        ];
        $rules = [
            'bus_id' => 'required|integer',
            'zone_id' => 'required|integer',
            'attendance_date' => 'required|min:10|max:11',
            'registrationIds' => 'required|array',
            'inTime' => 'required|array',
        ];
        //if it college then need another 2 feilds
        if(AppHelper::getInstituteCategory() == 'college') {
            $rules['academic_year'] = 'required|integer';
        }

        $this->validate($request, $rules, $messages);


        //check attendance exists or not
        $bus_id = $request->get('bus_id',0);
        $zone_id = $request->get('zone_id',0);
        $attendance_date = $request->get('attendance_date','');
        if(AppHelper::getInstituteCategory() == 'college') {
            $acYear =  $request->query->get('academic_year',0);
        }
        else{

            $acYear = AppHelper::getAcademicYear();
        }
        $attendances = $this->getAttendanceByFilters($bus_id, $zone_id, $acYear, $attendance_date, true);

        if($attendances){
            return redirect()->route('student_attendance.create')->with("error","Attendance already exists!");
        }


        //process the insert data
        $students = $request->get('registrationIds');
        $attendance_date = Carbon::createFromFormat('d/m/Y', $request->get('attendance_date'))->format('Y-m-d');
        $dateTimeNow = Carbon::now(env('APP_TIMEZONE','Asia/Dhaka'));
        $inTimes = $request->get('inTime');
        $atstatus = $request->get('atstatus');

        $attendances = [];
        $absentIds = [];
        $parseError = false;

        foreach ($students as $student){

            $inTime = Carbon::createFromFormat('d/m/Y h:i a', $request->get('attendance_date').' '.$inTimes[$student]);
            
            $isPresent = !in_array($student, $atstatus) ? "0" : "1";
            $status = 1;

            $attendances[] = [
                "academic_year_id" => $acYear,
                "bus_id" => $bus_id,
                "registration_id" => $student,
                "attendance_date" => $attendance_date,
                "in_time" => $inTime,
                "status" => $status,
                "present"   => $isPresent,
                "created_at" => $dateTimeNow,
                "created_by" => auth()->user()->id,
            ];

            if(!$isPresent){
                $absentIds[] = $student;
            }
        }

        if($parseError){
            return redirect()->route('employee_attendance.create')->with("error",$message);
        }

//        dd($attendances, $absentIds);

        DB::beginTransaction();
        try {

            BusAttendance::insert($attendances);
            DB::commit();
        }
        catch(\Exception $e){
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
            return redirect()->route('student_attendance.create')->with("error",$message);
        }


        $message = "Attendance saved successfully.";
        //check if notification need to send?
        //todo: need uncomment these code on client deploy
    //    $sendNotification = AppHelper::getAppSettings('student_attendance_notification');
    //    if($sendNotification != "0") {
    //        if($sendNotification == "1"){
    //            //then send sms notification

    //            //get sms gateway information
    //            $gateway = AppMeta::where('id', AppHelper::getAppSettings('student_attendance_gateway'))->first();
    //            if(!$gateway){
    //                redirect()->route('student_attendance.create')->with("warning",$message." Attendance SMS Gateway not setup!");
    //            }

    //            //get sms template information
    //            $template = Template::where('id', AppHelper::getAppSettings('student_attendance_template'))->first();
    //            if(!$template){
    //                redirect()->route('student_attendance.create')->with("warning",$message." But SMS template not setup!");
    //            }

    //            $res = AppHelper::sendAbsentNotificationForStudentViaSMS($absentIds, $attendance_date);

    //        }
    //    }

        //push job to queue
        //todo: need comment these code on client deploy
        // PushStudentAbsentJob::dispatch($absentIds, $attendance_date);


        return redirect()->route('attendance.bus_summary')->with("success",$message);
    }


    /**
     * status change
     * @return mixed
     */
    public function changeStatus(Request $request, $id=0)
    {
        $attendance =  BusAttendance::findOrFail($id);

        if(!$attendance){
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }
        if($request->get('status') == 1)
        {
            $end = $metas['shift_data']['Morning']['end'];
            $start = $metas['shift_data']['Morning']['start'];
            $date = $attendance->attendance_date;
            $inTime = Carbon::createFromFormat('d/m/Y h:i a', $date.' '.$start);

            $attendance->present = (string)$request->get('status');
            $attendance->in_time = $inTime;
            $attendance->save();
        }
        else{
          $attendance->present = (string)$request->get('status');
          $attendance->in_time = '1970-01-01 00:00:00';
          $attendance->save();
        }

        return [
            'success' => true,
            'message' => 'Status updated.'
        ];

    }

    public function buszones(Request $request) {
        // check for ajax request here
        if($request->ajax()){
            $bus_id = $request->query->get('bus', 0);
            $zonelist = [];
            $buses = Bus::where('status', AppHelper::ACTIVE)
                ->where('id', $bus_id)
                ->with(['zones' => function($query) {
                    $query->select('*')->orderBy('zone');
                }])
                ->orderBy('order','asc')
                ->get()
                ->first();

            $zone_list = AppHelper::getAppSettings('fee_trans_zones');
            foreach($buses->zones as $zone) {
                $z=[];
                $z['id'] = $zone->id;
                $z['text'] = $zone_list[$zone->id];
                array_push($zonelist, $z);
            }
            return $zonelist;
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\BusAttendance  $busAttendance
     * @return \Illuminate\Http\Response
     */
    public function show(BusAttendance $busAttendance)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\BusAttendance  $busAttendance
     * @return \Illuminate\Http\Response
     */
    public function edit(BusAttendance $busAttendance)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\BusAttendance  $busAttendance
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, BusAttendance $busAttendance)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\BusAttendance  $busAttendance
     * @return \Illuminate\Http\Response
     */
    public function destroy(BusAttendance $busAttendance)
    {
        //
    }
}
