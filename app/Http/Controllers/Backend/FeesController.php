<?php
namespace App\Http\Controllers\Backend;

use \stdClass;
use PDF;
use Log;
use Excel;
use App\Http\Helpers\AppHelper;
use App\AppMeta;
use App\AcademicYear;
use App\IClass;
use App\Section;
use App\Registration;
use App\FeeSetup;
use App\FeeClass;
use App\FeeInstallments;
use App\ExcludedFees;
use App\FeeCol;
use App\FeeCollectionMeta;
use App\FeeHistory;
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
use Illuminate\Support\Facades\Input;
use App\Http\Exports\FeeCollectionExport;
use App\Http\Exports\FeeCollectionItemisedExport;
use App\Rules\Installments;

class FeesController extends Controller {
	/**
	 * Helper function to sort the array of objects
	 */
	private static function compFee($a, $b){
		return ($a->fee < $b->fee) ? -1 : 1;
	}
	/**
	 * Create a new fee item
	 * @param Request $request
	 */
	public function feeSetup(Request $request){
		$class_ids = Input::get('class_id');
		$type = Input::get('type');
		$title = Input::get('title');
		$fee = Input::get('fee');
		$zone = Input::get('zone');
		$Latefee = Input::get('Latefee');
		$description = Input::get('description');
		$installments = Input::get('installments', 1);
		$duedate = Input::get('duedate', []);
		$latefeetype = Input::get('latefeetype', []);
		$insttype = Input::get('insttype', []);
		$instamount = Input::get('instamount', []);
		$instlatefee = Input::get('instlatefee', []);

		if($request->isMethod('post')){
			$rules=[
				'class_id' => 'required',
				'type' => 'required',
				'fee' => 'required|numeric',
				'title' => 'required',
				'duedate.*' => 'required|date_format:d/m/Y',
				'instamount'=>['required' , new Installments($installments, $insttype, $instamount, $fee)] ,
			];

			// print_r($duedate);exit;
			$this->validate($request, $rules, []);
			
			$fee_classes = [];
			$fee_inst=[];

			DB::beginTransaction();
			try {		
				$fid = FeeSetup::insertGetId([
					'type' => $type,
					'title' => $title,
					'fee' =>$fee,
					'zone' => $zone,
					'Latefee' => $Latefee,
					'installments' => $installments,
					'description' => $description
				]);
				
				foreach($class_ids as $c) {
					$fee_classes[] = array(
						'class_id' => $c,
						'fee_item' => $fid
					);
				}
				FeeClass::insert($fee_classes);

				for($i=0;$i<$installments; $i++) {
					$due = Carbon::createFromFormat('d/m/Y', $duedate[$i])->endOfDay();
					$fee_inst[] = array(
						'due_date' => $due,
						'latefee' => $instlatefee[$i],
						'lftype' => $latefeetype[$i],
						'inst_type' => $insttype[$i],
						'inst_fee' => $instamount[$i],
						'feeitem' => $fid
					);
				}
				FeeInstallments::insert($fee_inst);
				DB::commit();
			}
			catch(\Exception $e){
				DB::rollback();
				throw new \Exception($e->getMessage());
			}
			return redirect()->route('fees.index')->with('success', 'Fee Save Succesfully!');
		}
		$fee_detail = null;
		$classes = IClass::select('name','id')->where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->get();
		$fee_types = AppHelper::FEE_TYPES;
		return view('backend.fees.feesSetup',compact('classes','fee_detail', 'fee_types', 'installments', 'duedate', 'latefeetype', 'insttype', 'instlatefee', 'instamount'));
	}

	/**
	* Display the specified resource.
	*
	* @param  int  $id
	* @return Response
	*/
	public function getList(Request $request) {
		if($request->isMethod('post')) {
			$rules=[
				'class' => 'required'
			];
			$validator = \Validator::make(Input::all(), $rules);
	
			if ($validator->fails())
			{
				return Redirect::to('/fees/list')->withErrors($validator);
			}
			else {
				$class_id = Input::get('class');
				$fees = FeeSetup::select("*")->with(['class' =>  function($query){
					$query->select('*');
				}])->get();
				$classes = IClass::where('name','numeric_value')->where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->pluck('name','numeric_value');
				$formdata = new formfoo;
				$formdata->class=Input::get('class');
				return view('backend.fees.feeList',compact('classes','formdata','fees'));
	
			}
		}
		$fees = FeeSetup::with(['class' =>  function($query){
			$query->select('*')->with(['class' => function ($query) {
				$query->select('id', 'name');
			}]);
		}])->select('*')->get()->toArray();
		// echo "<pre>";print_r($fees);die;
		$fee_types = AppHelper::FEE_TYPES;
		return view('backend.fees.feeList',compact('fees','fee_types'));
	}


	/**
	* Show the form for editing the specified fee item.
	*
	* @param  int  $id
	* @return Response
	*/
	public function feeUpdate(Request $request, $id){
		$class_ids = Input::get('class_id');
		$type = Input::get('type');
		$title = Input::get('title');
		$fee = Input::get('fee');
		$zone = Input::get('zone');
		$Latefee = Input::get('Latefee');
		$description = Input::get('description');
		$installments = Input::get('installments', 1);
		$duedate = Input::get('duedate', []);
		$latefeetype = Input::get('latefeetype', []);
		$insttype = Input::get('insttype', []);
		$instamount = Input::get('instamount', []);
		$instlatefee = Input::get('instlatefee', []);
		$instid = Input::get('instid', []);

		if($request->isMethod('post')){
			$rules=[
				'class_id' => 'required',
				'type' => 'required',
				'fee' => 'required|numeric',
				'title' => 'required',
				'duedate.*' => 'required|date_format:d/m/Y',
				'instamount'=>['required' , new Installments($installments, $insttype, $instamount, $fee)] ,
			];

			// print_r($instid);exit;
			$this->validate($request, $rules, []);

			DB::beginTransaction();
			try{
				FeeSetup::where('id', $id)
				->update([
					'type' => $type,
					'title' => $title,
					'fee' =>$fee,
					'zone' => $zone,
					'installments' => $installments,
					'Latefee' => $Latefee,
					'description' => $description
				]);
				
				foreach($class_ids as $c) {
					$fee_class = array(
						'class_id' => $c,
						'fee_item' => $id
					);
					FeeClass::updateOrCreate($fee_class, $fee_class);
				}

				foreach($instid as $i => $inst) {
					$due = Carbon::createFromFormat('d/m/Y', $duedate[$i])->endOfDay();
					$updateId = isset($instid[$i]) ? ['id' => $instid[$i]] : ['id' => ''];
					$fee_inst = array(
						'due_date' => $due,
						'latefee' => $instlatefee[$i],
						'lftype' => $latefeetype[$i],
						'inst_type' => $insttype[$i],
						'inst_fee' => $instamount[$i],
						'feeitem' => $id
					);
					FeeInstallments::updateOrCreate($updateId, $fee_inst);
				}
				DB::commit();
			}catch(\Exception $e){
				DB::rollback();
				Log::error($e);
				$message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
				return redirect()->route('fees.feeUpdate', ['id' => $id])->with('error', $message);
			}
			return redirect()->route('fees.index')->with('success', 'Fee Updated Succesfully!');
		}
		$classes = IClass::select('name','id')->where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->get();
		$fee = FeeSetup::where('id', $id)->with(['class' =>  function($query){
			$query->select('*');
		}])->with(['feeInstallments' =>  function($query){
			$query->select('*');
		}])->select('*')->first();
		// return $fee;
		// echo "<pre>";print_r($fee);die;
		$fee_types = AppHelper::FEE_TYPES;
		return view('backend.fees.feeEdit',compact('fee','classes','fee_types'));
	}


	/**
	* Remove the specified resource from storage.
	*
	
	* @param  int  $id
	* @return Response
	*/
	public function feeDelete($id){
		$fee = FeeSetup::find($id);
		$fee->delete();
		return redirect()->route('fees.index')->with('success', 'Fee Deleted Succesfully!');
	}

	public function feeCollection(Request $request)	{
		if($request->isMethod('post')) {
			return $this->postCollection($request);
		}
		$classes = IClass::select('id','name')->where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->get();
		$fee_types = AppHelper::FEE_TYPES;
		$paytype = AppHelper::PAYMENT_METHOD;

		$academic = AppMeta::where('meta_key', 'academic_year')->select('meta_value')->first();
		$academic_year = $academic->meta_value;
		$academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
		return view('backend.fees.feeCollection',compact('classes','fee_types', 'paytype', 'academic_years', 'academic_year'));
	}

	public function postCollection($request){//validate form
        $messages = [
            'type.required' => 'Payment type need to be mentioned!',
            'paidamount.required' => 'Paid amount must not be zero!',
			'student.required' => 'Mention the student who is making the payment!',
			'paytype' => 'You mush select a payment type'
        ];
		$rules=[
			'class' => 'required',
			'student' => 'required',
			'type' => 'required',
			'paidamount' => 'required',
			'discount' => 'required',
			'currenttotal' => 'required',
			'paytype' => 'required',
			'academic_year' => 'required'
		];
		$this->validate($request, $rules, $messages);
		
		try {
			$feeIDs = Input::get('gridFeeID');
			$class_id=Input::get('class');
			$student_id=Input::get('student');
			$type=Input::get('type');
			$payableAmount=Input::get('currenttotal');
			$paidAmount=Input::get('paidamount');
			$dueAmount=Input::get('dueamount');
			$discount = Input::get('discount');
			$latefee = Input::get('latefee');
			$invoicedto = Input::get('invoicedto');
			$payDate=Input::get('collection_date');
			$month=Input::get('month');
			$deletedFee=Input::get('deletedFee', []);
			// $payDate = isset($payDate) ? date('Y-m-d H:i:s', strtotime($payDate)) : date('Y-m-d H:i:s', time());
			$payDate = isset($payDate) ? Carbon::createFromFormat('d/m/Y', $payDate) : Carbon::now();
			$totalpay = $paidAmount + $discount;
			
			$paytype = Input::get('paytype');
			$issuedate = Input::get('issuedate');
			$bank = Input::get('bank');
			$reference = Input::get('reference');

			$feeAmounts = Input::get('gridFeeAmount');
			$feeLateAmounts = Input::get('gridLateFeeAmount');
			$feeTotalAmounts = Input::get('gridTotal');
			$feeMonths = Input::get('gridMonth');
			$academic_year = Input::get('academic_year', NULL);

			$message = $this->storePaidFee($feeIDs, NULL, $class_id, $student_id, $type, $discount,
				$latefee, $invoicedto, $payDate, $totalpay, $paytype, $issuedate, $bank, $reference, 
				$feeMonths, $dueAmount, $month, $deletedFee, $academic_year);
			return redirect()->back()->with("success", $message);
		}catch(\Exception $e){
			Log::error($e);
			$message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
			return redirect()->route('feescollection.create')->with("error",$message);
		}
	}


    /**
     * Upload file for student
     * @return mixed
     */
    // Newly Added
    public function createFromFile(Request $request) {
		try{
			if ($request->isMethod('post')) {
				$request->validate([
					'import_file' => 'required'
				]);
		
				$path = $request->file('import_file')->getRealPath();
				$data = Excel::import($path)->get();
				$messages = [];
				if($data->count()){
					foreach ($data as $key => $value) {
						try {			
							$cardno = $value->uid;	
							$type = $value->type;
							
							$stdinfo = Registration::select("*")->where('card_no', $cardno)
							->with('class')
							->with('student')
							->first();
							if($stdinfo){
								$class_id = $stdinfo->class->id;
								$query = FeeSetup::select('id')								
								->with(['class' => function($query) use ($class_id){
									$query->where('class_id','=',$class_id);
								}])->where('type', $type);
								
								if($type == AppHelper::TRANSPORT) {
									$query->where('zone', '=', $stdinfo->student->transport_zone);	
								}
								$feeIDs = $query->pluck('id')->all();
								$student_id = $stdinfo->student->id;
		
								$paidAmount = $value->paidamount;
								$discount = $value->discount;
								$latefee = 0;
								$invoicedto = $value->invoicedto;
								$invoiceno = $value->invoiceno;
								$payDate = $value->date;
								$payDate = isset($payDate) ? date('Y-m-d H:i:s', strtotime($payDate)) : date('Y-m-d H:i:s', time());
								$totalpay = $paidAmount + $discount;
		
								$paytype = $value->paytype;
								$issuedate = $value->issuedate;
								$bank = $value->bank;
								$reference = $value->reference;
		
								$messages[] = $this->storePaidFee($feeIDs, $invoiceno, $class_id, $student_id, $type, $discount,
									$latefee, $invoicedto, $payDate, $totalpay, $paytype, $issuedate, $bank, $reference, NULL, NULL, 0);
							}		
						}
						catch(\Exception $e){
							Log::error($e);
							$message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
							return back()->with("error",$message);
						}   
					}
						
					return redirect()->back()->with("success", implode("\n", $messages));
				}
				return redirect()->back()->with('success', 'File Uploaded Successfully! '. $studentCount. 'Student(s) added!');
			}
			$isProcessingFile = false;
			return view('backend.fees.upload', compact('isProcessingFile'));
		}catch(\Exception $e){
			$message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
			return redirect()->back()->with("error",$message);
		}
    }

	public function feelistByClassAndType($class_id, $type, $sid)	{
		$query= FeeSetup::select('id','title', 'fee','Latefee', 'installments')
		->where('type','=',$type)
		->whereHas('class', function($query) use ($class_id){
			$query->where('class_id','=',$class_id);
		})
		->whereDoesntHave('excludedFees', function($query) use ($sid){
			$query->where('student_id','=',$sid);
		})
		->with('feeInstallments');		
		if($type == AppHelper::TRANSPORT) {
			$student = AppHelper::getStudentByID($sid);
			$query->where('zone', '=', $student->transport_zone);	
		}
		$fees = $query->get();
		return $fees;
	}

	public function feeTypeTotal(Request $request, $class, $sid) {
        $year = $request->query->get('year', 1);
		$feeStats = AppHelper::feeTypeTotal($class, $sid, $year);
		return $feeStats;
	}

	public function getFeeInfo($id)
	{
		$fee= FeeSetup::select('fee','Latefee')->where('id','=',$id)->get();
		return $fee;
	}

	public function getDue(Request $request, $class_id, $student_id, $type, $month){
        $year = $request->query->get('year', 1);
		$query = FeeCol::select('fee_item', (DB::RAW('IFNULL(sum(paidAmount),0) as paidamount')))
			->where('class_id',$class_id)
			->where('student_id',$student_id)
			->where('academic_year', $year)
			->where('type',$type);
		if($month && $month != '0') {
			$query->where('month', $month);
		}
		$due = $query->groupBy('fee_item')
			->pluck('paidamount', 'fee_item')->all();
		return $due;
	}

	public function feeCollectionList(Request $request) {
		$academic = AppMeta::where('meta_key', 'academic_year')->select('meta_value')->first();
		$academic_year = $academic->meta_value;
		$academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');

		if($request->isMethod('post')) {
			$classes = IClass::select('id','name')->where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->get();
			$student = new stdClass();
			$student->class=Input::get('class');
			$student->section=Input::get('section');
			$student->student=Input::get('student');
			$academic_year = Input::get('academic_year');
			$fees=DB::Table('fee_collection')
			->select(DB::RAW("billNo,payableAmount,paidAmount,dueAmount,DATE_FORMAT(payDate,'%D %M,%Y') AS date"))
			->where('class_id',Input::get('class'))
			->where('student_id',Input::get('student'))
			->where('academic_year', $academic_year)
			->whereNull('deleted_at')
			->get();

			$totals = FeeCol::select(DB::RAW('IFNULL(sum(payableAmount),0) as payTotal,IFNULL(sum(paidAmount),0) as paiTotal,(IFNULL(sum(payableAmount),0)- IFNULL(sum(paidAmount),0)) as dueAmount'))
			->where('class_id',Input::get('class'))
			->where('student_id',Input::get('student'))
			->where('academic_year', $academic_year)
			->whereNull('deleted_at')
			->first();
			// echo "<pre>";print_R($fees);die;
			return view('backend.fees.feeviewstd',compact('classes','student','fees','totals', 'academic_years', 'academic_year'));
		}
		$classes = IClass::select('id','name')->where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')->get();
		$fees = array();
		return view('backend.fees.feeviewstd',compact('classes','fees', 'academic_years', 'academic_year'));
	}
	
	public function feeCollectionDelete($billNo)
	{
		try {
			DB::beginTransaction();
			$collection = FeeCol::select('id')->where('billNo', 'like', '%'. $billNo .'%')->get();
			FeeCol::destroy($collection->toArray());
			$collection = FeeHistory::select('id')->where('billNo', 'like', '%'. $billNo .'%')->get();
			FeeHistory::destroy($collection->toArray());
			DB::commit();
			return redirect()->back()->with("success","Payment deltails for ".$billNo." deleted succesfull!");
		}
		catch(\Exception $e)
		{
			DB::rollback();
			Log::error($e);
			return redirect()->back()->withErrors( $e->getMessage())->withInput();
		}

	}
	public function reportstd($student_id)	{
		$datas=DB::Table('fee_collection')
		->select(DB::RAW("payableAmount,paidAmount,dueAmount,DATE_FORMAT(payDate,'%D %M,%Y') AS date"))
		->where('student_id',$student_id)
		->get();
		$totals = FeeCol::select(DB::RAW('IFNULL(sum(payableAmount),0) as payTotal,IFNULL(sum(paidAmount),0) as paiTotal,(IFNULL(sum(payableAmount),0)- IFNULL(sum(paidAmount),0)) as dueamount'))
		->where('student_id',$student_id)
		->first();
		$stdinfo = Registration::where('id', $student_id)
            ->with('student')
            ->with('class')
            ->with('section')
            ->with('acYear')
			->first();
			
		// $institute = AppHelper::getAppSettings('institute_settings');
		$institute = new \stdClass();
		$rdata =array('payTotal'=>$totals->payTotal,'paiTotal'=>$totals->paiTotal,'dueAmount'=>$totals->dueamount);
		$pdf = PDF::loadView('backend.fees.feestdreportprint',compact('datas','rdata','stdinfo','institute'));
		return $pdf->stream('student-Payments.pdf');
	}

	public function feeReportList(Request $request)
	{
		$currentDate = date("Y-m-d");

		$class_id = '';
		$section_id = '';
		$type = '';
		$sDate = '';
		$eDate = '';
		$datas = array();
		$rdata = array();
		$institute = array();
		$classes = IClass::select('name','id')->orderBy('order', 'asc')->pluck('name','id');
		$fee_types = AppHelper::FEE_TYPES;
				
		$academic = AppMeta::where('meta_key', 'academic_year')->select('meta_value')->first();
		$academic_year = $academic->meta_value;
		$academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');

		if ($request->isMethod('post')) {
			$rules=[
				'type' => 'required',
				'from_date' => 'required|date_format:d/m/Y',
				'to_date' => 'required|date_format:d/m/Y|after_or_equal:from_date'
			];
	
			$validator = \Validator::make(Input::all(), $rules);
	
			
			if ($validator->fails())
			{
				return redirect()->back()->withErrors($validator);
			} else {
				$sDate = Carbon::createFromFormat('d/m/Y', Input::get('from_date'));
				$eDate = Carbon::createFromFormat('d/m/Y', Input::get('to_date'));
				$type = Input::get('type');
				$class_id = Input::get('class_id');
				$section_id = Input::get('section_id');
				$academic_year = Input::get('academic_year', $academic_year);


				$registrationQuery = DB::table('registrations');

				if (!empty($class_id)) {
					$registrationQuery->where('class_id', '=', $class_id);
				}

				if (!empty($section_id)) {
					$registrationQuery->where('section_id', '=', $section_id);
				}

				$stdRegistrationInfo = $registrationQuery->get()->toArray();

				$stdinfo = array();
				$registrationDetails = array();
				foreach($stdRegistrationInfo as $value){
					$stdinfo[] = $value->id;
					$registrationDetails[$value->id] = array(
						'id' => $value->id,
						'regi_no' => $value->regi_no,
						'roll_no' => $value->roll_no,
						'class_id' => $value->class_id,
						'section_id' => $value->section_id,
						'student_id' => $value->student_id
					);
				}

				// echo "<pre>";print_r($registrationDetails);die;
				// echo "<pre>";print_r($stdinfo);die;
				
				$datas= FeeCol::select('*')
				->whereIn('student_id', $stdinfo)
				->where('academic_year', $academic_year)
				->whereBetween('payDate',[$sDate, $eDate])
				->with(['student' => function ($query) {
					$query->select('id', 'name');
				}])
				->get()->toArray();
				
				$institute = AppHelper::getAppSettings('institute_settings');
				// echo "<pre>";print_r($datas);die;
				$sections = Section::where('status', AppHelper::ACTIVE)
                    ->pluck('name', 'id');
				$rdata =array('sDate'=>$this->getAppdate($sDate),'eDate'=>$this->getAppdate($eDate));
				return view('backend.fees.feesreport', compact('classes', 'fee_types', 'currentDate', 'sDate', 'eDate', 'datas', 'rdata', 'institute', 'type', 'class_id', 'section_id', 'sections', 'registrationDetails', 'academic_year', 'academic_years'));
				// $pdf = PDF::loadView('backend.fees.feesreportprint',compact('datas','rdata','institute'));
				// return $pdf->stream('fee-collection-report.pdf');
			}
		}
		return view('backend.fees.feesreport', compact('classes', 'fee_types', 'currentDate', 'sDate', 'eDate', 'datas', 'rdata', 'institute', 'type', 'class_id', 'section_id', 'academic_year', 'academic_years'));
	}

	public function feeCollectionReport(Request $request)
	{
		$currentDate = date("Y-m-d");
				
		$academic = AppMeta::where('meta_key', 'academic_year')->select('meta_value')->first();
		$academic_year = $academic->meta_value;
		$academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
		
		$routeName = $request->route()->getName();
		$pmonth = '';
		if($routeName != 'feescollectonreport' && $routeName != 'feescollectonexport'){
			$pmonth = intval(date('m', time())) . "";
		} else {
			if ($request->isMethod('post')) {
				$sDate = Carbon::createFromFormat('d/m/Y', Input::get('from_date'));
				$eDate = Carbon::createFromFormat('d/m/Y', Input::get('to_date'));
			}
		}
		
		$inputType = Input::get('type', array());
		$class_id = Input::get('class_id', '');
		$section_id = Input::get('section_id', '');
		$sDate = Input::get('from_date');
		$eDate = Input::get('to_date');
		$academic_year = Input::get('academic_year', $academic_year);
		$month = Input::get('month', $pmonth);
		$datas = array();
		$rdata = array();
		$institute = array();
		$feeitems = array();
		$classes = IClass::select('name','id')->orderBy('order', 'asc')->pluck('name','id');
		$fee_types = AppHelper::FEE_TYPES;
		$months = ['' => 'Select a month'];
		foreach (range(1, 12) as $m){
			$months[$m] = date('F', mktime(0, 0, 0, $m));
		}

		//validate form
		$rules = array();
		if($sDate || $eDate){
			$rules = [
				'from_date' => 'required|date_format:d/m/Y',
				'to_date' => 'required|date_format:d/m/Y|after_or_equal:from_date'
			];
		}

		$this->validate($request, $rules);

		$selectedtypes = array();
		if(!empty($inputType)){
			foreach($inputType as $t){
				if($t){
					$selectedtypes[$t] = AppHelper::FEE_TYPES[$t];
				}
			}
		}else{
			$selectedtypes = AppHelper::FEE_TYPES;
		}
		if($month) {
			$m = Carbon::createFromFormat('m', $month);
			$sDate = $m->startOfMonth()->toDateString();
			$eDate = $m->endOfMonth()->toDateString();
		}

		$report = $this->prepareReportData($academic_year, $sDate, $eDate, $inputType, $selectedtypes, $class_id, $section_id, $feeitems);
		
		// return $data;
				
		return view('backend.fees.report.summary', compact('classes', 'fee_types', 'inputType', 'selectedtypes', 'class_id', 'section_id', 'currentDate', 'sDate', 'eDate', 'month', 'report', 'feeitems', 'routeName', 'months', 'academic_year', 'academic_years'));
	}

	public function feeCollectionReportExport(Request $request){
		$month = Input::get('month', '');
		$sDate = null;
		$eDate = null;
		$feeitems = array();

		$routeName = $request->route()->getName();
		if($routeName == 'feescollectonreport' && $routeName == 'feescollectonexport'){
			if ($request->isMethod('post')) {
				$sDate = Carbon::createFromFormat('d/m/Y', Input::get('from_date'));
				$eDate = Carbon::createFromFormat('d/m/Y', Input::get('to_date'));
			}
		}
		//validate form
		$rules = array();
		if($sDate || $eDate){
			$rules = [
				'from_date' => 'required|date',
				'to_date' => 'required|date|after_or_equal:from_date'
			];
		}

		$this->validate($request, $rules);
		if($month) {
			$m = Carbon::createFromFormat('m', $month);
			$sDate = $m->startOfMonth()->toDateString();
			$eDate = $m->endOfMonth()->toDateString();
		}

		$inputType = Input::get('type');
		$class_id = Input::get('class_id');
		$section_id = Input::get('section_id');
		$academic_year = Input::get('academic_year', NULL);

		return (new FeeCollectionExport($academic_year, $sDate, $eDate, $inputType, $class_id, $section_id, $feeitems))->download('feecollection_summary.xlsx');
	}

	public function feeCollectionItemisedReport(Request $request)
	{
		$currentDate = date("Y-m-d");
		$routeName = $request->route()->getName();
		$pmonth = '';
		if($routeName != 'feescollectonitemisedreport' && $routeName != 'feescollectonitemisedexport'){
			$pmonth = intval(date('m', time())) . "";
		}

		$academic = AppMeta::where('meta_key', 'academic_year')->select('meta_value')->first();
		$academic_year = $academic->meta_value;
		$academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');

		$inputType = Input::get('type', '');
		$class_id = Input::get('class_id', '');
		$section_id = Input::get('section_id', '');
		$sDate = Input::get('from_date');
		$eDate = Input::get('to_date');
		$academic_year = Input::get('academic_year', $academic_year);
		$month = Input::get('month', $pmonth);
		$datas = array(); $data = array();$result = array(); $selectedItems = array();
		$rdata = array();
		$institute = array();
		$feeitems = Input::get('fee_item', array());
		$classes = IClass::select('name','id')->orderBy('order', 'asc')->pluck('name','id');
		$fee_types = AppHelper::FEE_TYPES;
		$months = ['' => 'Select a month'];

		if ($request->isMethod('post')) {
			$sDate = Carbon::createFromFormat('d/m/Y', $sDate);
			$eDate = Carbon::createFromFormat('d/m/Y', $eDate);
		}

		foreach (range(1, 12) as $m){
			$months[$m] = date('F', mktime(0, 0, 0, $m));
		}

		//validate form
		$rules = array();
		if($sDate || $eDate){
			$rules = [
				'from_date' => 'required|date_format:d/m/Y',
				'to_date' => 'required|date_format:d/m/Y|after_or_equal:from_date'
			];
		}

		$this->validate($request, $rules);

		if($month) {
			$m = Carbon::createFromFormat('m', $month);
			$sDate = $m->startOfMonth()->toDateString();
			$eDate = $m->endOfMonth()->toDateString();
		}

		if(!empty($feeitems)){
			$selectedItems = FeeSetup::whereIn('id', $feeitems)->get()->all();

			$result = AppHelper::getCollectedFeeList($academic_year, $sDate, $eDate, array(), $class_id, $section_id, $feeitems, TRUE, TRUE);

			$data = $result->getCollection()->groupBy('class')->transform(function($item, $k) {
				return $item->groupBy('name')->transform(function($item, $k) {
					return $item->groupBy('fee_item');
				});
			});

			foreach($data as $c) {
				foreach($c as $s) {
					$dataobject = reset($s);
					$dataobject = reset($dataobject);
					$student = Student::where('id', $dataobject[0]->student_id)->get()->first();
					$total = 0;
					$paid = 0;
					$due = 0;
					$discount = 0;

					foreach($selectedItems as $fee) {
						if(isset($s[$fee->id])) {
							$payment = $s[$fee->id][0];
							$payment->payable = intval($fee->fee);
							$payment->due = $payment->payable - (intval($payment->paidTotal) + intval($payment->discount));
						}else{
							$payment = new stdClass();
							$payment->payable = intval($fee->fee);
							$payment->paidTotal = 0;
							$payment->discount = 0;
							$payment->due = $payment->payable;
							$s[$fee->id] = array($payment);
						}
						$total += intval($payment->payable);
						$paid += intval($payment->paidTotal);
						$due += intval($payment->due);
						$discount += intval($payment->discount);
					}
					$payment = new stdClass();
					$payment->payable = $total;
					$payment->paidTotal = $paid;
					$payment->discount = $discount;
					$payment->due = $due;
					$s['total'] = array($payment);
				}
			}
			
		}
		
		$report = ['result' => $result, 'data' => $data];
		
		// return $report;
				
		return view('backend.fees.report.itemised', compact('classes', 'fee_types', 'inputType', 'months', 'month', 'selectedItems', 'class_id', 'section_id', 'currentDate', 'sDate', 'eDate', 'report', 'feeitems', 'routeName', 'academic_year', 'academic_years'));
	}

	public function feeCollectionItemisedReportExport(Request $request){
		$sDate = Input::get('from_date', '');
		$eDate = Input::get('to_date', '');
		$month = Input::get('month', '');
		$feeitems = array();

		//validate form
		$rules = array();
		if($sDate || $eDate){
			$rules = [
				'from_date' => 'required|date',
				'to_date' => 'required|date|after_or_equal:from_date'
			];
		}

		$this->validate($request, $rules);
		if($month) {
			$m = Carbon::createFromFormat('m', $month);
			$sDate = $m->startOfMonth()->toDateString();
			$eDate = $m->endOfMonth()->toDateString();
		}

		$class_id = Input::get('class_id');
		$section_id = Input::get('section_id');
		$feeitems = Input::get('fee_item');
		$academic_year = Input::get('academic_year', NULL);

		return (new FeeCollectionItemisedExport($academic_year, $sDate, $eDate, array(), $class_id, $section_id, $feeitems))->download('feecollection_itemised_report.xlsx');
	}

	private function prepareReportData($academic_year, $sDate, $eDate, $inputType, $selectedtypes, $class_id, $section_id, $feeitems){
		$result = AppHelper::getCollectedFeeList($academic_year, $sDate, $eDate, $inputType, $class_id, $section_id, $feeitems);

		$data = $result->getCollection()->groupBy('class')->transform(function($item, $k) {
			return $item->groupBy('name')->transform(function($item, $k) {
				return $item->groupBy('type');
			});
		});

		foreach($data as $c) {
			foreach($c as $s) {
				$dataobject = reset($s);
				$dataobject = reset($dataobject);
				$student = Student::where('id', $dataobject[0]->student_id)->get()->first();
				$total = 0;
				$paid = 0;
				$due = 0;
				$discount = 0;
				foreach($selectedtypes as $type => $name) {
					if(isset($s[$type])) {
						$payment = $s[$type][0];
					}else{
						$payment = new stdClass();
						$payment->payable = AppHelper::totalFee($dataobject[0]->class_id, $type, $student);
						$payment->paidTotal = 0;
						$payment->discount = 0;
						$payment->due = $payment->payable;
						$s[$type] = array($payment);
					}
					$total += intval($payment->payable);
					$paid += intval($payment->paidTotal);
					$due += intval($payment->due);
					$discount += intval($payment->discount);
				}
				$payment = new stdClass();
				$payment->payable = $total;
				$payment->paidTotal = $paid;
				$payment->discount = $discount;
				$payment->due = $due;
				$s['total'] = array($payment);
			}
		}
		return ['result' => $result, 'data' => $data];
	}
	
	public function feePrintReport($sDate, $eDate)
	{
		$datas= FeeCol::select(DB::RAW('IFNULL(sum(payableAmount),0) as payTotal,IFNULL(sum(paidAmount),0) as paiTotal,(IFNULL(sum(payableAmount),0)- IFNULL(sum(paidAmount),0)) as dueamount'))
		->whereDate('created_at', '>=', date($sDate))
		->whereDate('created_at', '<=', date($eDate))
		->first();
		$institute=Institute::select('*')->first();
		$rdata =array('sDate'=>$this->getAppdate($sDate),'eDate'=>$this->getAppdate($eDate));
		$pdf = PDF::loadView('app.feesreportprint',compact('datas','rdata','institute'));
		return $pdf->stream('fee-collection-report.pdf');
	}

	public function billDetails($billNo)
	{
		$billDetails = FeeHistory::select("*")
		->where('billNo',$billNo)
		->get();
		return $billDetails;
	}

	public function printBillDetails($billNo)
	{
		$billDetails = FeeCol::select("*")
		->where('billNo',$billNo)
		->with(['feeItem' => function($query) {
			$query->select("*");
		}])
		->with(['Payment' => function($query) {
			$query->select("*");
		}])
		->with(['Student' => function($query) {
			$query->select("*");
			$query->with(['registration' => function($query) {
				$query->select("*")
				->with(['class' => function($query) {
					$query->select("*");
				}])
				->with(['section' => function($query) {
					$query->select("*");
				}]);
			}]);
		}])
		->get();
		$class_id = $billDetails[0]->Student->registration[0]->class->id;
		$student_id = $billDetails[0]->Student->id;
		$type = $billDetails[0]->type;
		$totalfee = AppHelper::totalFee($class_id, $type, $billDetails[0]->Student);
		$paidtotal = $this->totalPaid($class_id, $student_id, $type, $billDetails[0]->payDate, $billDetails[0]->academic_year);
		$late_fee = floatval($billDetails[0]->latefee);
		$balance = floatval($totalfee) - floatval($paidtotal);
		$invoicedto = "";
		$paid = 0;
		foreach($billDetails as $fee)	{
			$paid += floatval($fee->paidAmount);
		}
		$paid = $paid - intval($billDetails[0]->discount);
		if(isset($billDetails[0]->Payment->invoicedto) && ($billDetails[0]->Payment->invoicedto != NULL || $billDetails[0]->Payment->invoicedto != "")){
			$invoicedto = $billDetails[0]->Payment->invoicedto;
		}elseif($billDetails[0]->Student->father_name != NULL || $billDetails[0]->Student->father_name != ""){
			$invoicedto = $billDetails[0]->Student->father_name;
		}elseif($billDetails[0]->Student->mother_name != NULL || $billDetails[0]->Student->mother_name != ""){
			$invoicedto = $billDetails[0]->Student->mother_name;
		}

		$feelist = $this->getFeeListByClassAndType($class_id, $type, $student_id);
		
		$settings = AppMeta::where('meta_key', 'institute_settings')->select('meta_key','meta_value')->first();
        $info = null;
        if($settings) {
            $info = json_decode($settings->meta_value);
		}
		// $academic = AppMeta::where('meta_key', 'academic_year')->select('meta_value')->first();
		$academic_years = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');

		
		$invoiceMeta = new stdClass();
		$invoiceMeta->invoicedto = $invoicedto;
		$invoiceMeta->totalfee = $totalfee;
		$invoiceMeta->balance = $balance;
		$invoiceMeta->paid = $paid + $late_fee;
		$invoiceMeta->organization = $info->name;
		$invoiceMeta->address = $info->address;
		$invoiceMeta->website_link = $info->website_link;
		$invoiceMeta->email = $info->email;
		$invoiceMeta->phone_no = $info->phone_no;
		$invoiceMeta->academic = $academic_years[$billDetails[0]->academic_year];
		$invoiceMeta->inwords = $this->numberTowords($paid + $late_fee);
		$invoiceMeta->hideOutstandingAmount = intval(AppHelper::getAppSettings('hide_outstanding_amount'));
		$logo = asset('storage/logo/'.$info->logo);
		$invoiceMeta->logo = $logo;
		if($type == AppHelper::OTHERID) {
			$invoiceMeta->title = "PAYMENT RECEIPT";
			$name = AppMeta::where('meta_key', 'otherorgname')->select('meta_value')->first();
			$addr = AppMeta::where('meta_key', 'otherorgaddr')->select('meta_value')->first();
			$invoiceMeta->organization = $name->meta_value;
			$invoiceMeta->address = $addr->meta_value;
			if(!intval(AppHelper::getAppSettings('show_school_logo'))) {
				$invoiceMeta->logo = '';
			}
		}else{
			if($type == AppHelper::TRANSPORT) {
				$invoiceMeta->title = 'TRANSPORTATION RECEIPT';
			}elseif($type == AppHelper::TRUSTID) {
				$invoiceMeta->title = "PAYMENT RECEIPT";
				$name = AppMeta::where('meta_key', 'trustname')->select('meta_value')->first();
				$addr = AppMeta::where('meta_key', 'trustaddress')->select('meta_value')->first();
				$invoiceMeta->organization = $name->meta_value;
				$invoiceMeta->address = $addr->meta_value;
			}else{
				$invoiceMeta->title = 'FEE RECEIPT';
			}
		}
		$pdf = PDF::loadView('backend.fees.invoice.index',compact('billDetails','invoiceMeta', 'feelist'));
		return $pdf->stream('Invoice-'. $billNo . '.pdf');
		
		// return [$billDetails, $invoiceMeta];
		// return view('backend.fees.invoice.index',compact('billDetails','invoiceMeta', 'feelist'));
	}

	private function storePaidFee($feeIDs, $invoiceno, $class_id, $student_id, $type, $discount,
		$latefee, $invoicedto, $payDate, $totalpay, $paytype, $issuedate, $bank, $reference, $feeMonths, 
		$dueAmount, $month, $deletedFee, $academic_year=NULL){			
		$zone = 0;

		$acYearId = '';
		$acYears = [];
		$excluded = [];

		$settings = AppHelper::getAppSettings();
		$acYearId = $settings['academic_year'];

		$academic_year = $academic_year ? $academic_year : $acYearId;

		$counter = count($feeIDs);
		if($counter > 0) {
			$fees = $this->getFeeMultiple($feeIDs);
			$fee_maps = [];
			$current_payment = [];
			$fee_collection = [];
			$paidtotal = $this->paidTotal($feeIDs, $student_id, $month, $academic_year);
			$deducted = 0;
			$deducted_now = 0;
			$due = 0;

			usort($fees, 'static::compFee');

			if(!$invoiceno){
				$fee_prefix = json_decode(AppMeta::where('meta_key', 'fee_reciept_prefix')->select('meta_value')->first()->meta_value);
				$fee_prefix_default = AppMeta::where('meta_key', 'default_reciept_prefix')->select('meta_value')->first();
				$prefix = property_exists($fee_prefix, $type) ? $fee_prefix->{$type} : $fee_prefix_default->meta_value;
				
				if(intval(AppHelper::getAppSettings('use_academic_prefix'))) {
					$acYears = AcademicYear::where('status', '1')->orderBy('id', 'desc')->pluck('title', 'id');
					$year = explode("-", $acYears[$acYearId]);
					$prefix .= "-" . $year[0];
				}elseif(AppHelper::FEERECIPT_INTER){
					$prefix .= "-" . AppHelper::FEERECIPT_INTER;
				}
				$billNo = $this->getLastFeeNumber($prefix);
				if($billNo < 9) {
					$billId = $prefix . '-00' . ($billNo + 1);
				} else if ($billNo < 100) {
					$billId = $prefix . '-0' . ($billNo + 1);
				} else {
					$billId = $prefix. '-' . ($billNo + 1);
				}
			}else{
				$billId = $invoiceno;
			}
			
			$timeStampNow = Carbon::now(env('APP_TIMEZONE', 'Asia/Kolkata'));
			$userId = auth()->user()->id;
			
			DB::beginTransaction();
			try {		
				$pid = FeeCollectionMeta::insertGetId([
					'type' => $paytype,
					'reference' => $reference,
					'date' =>$issuedate,
					'bank' => $bank,
					'invoicedto' => $invoicedto
				]);
				DB::commit();
			}
			catch(\Exception $e){
				DB::rollback();
				throw new \Exception($e->getMessage());
			}

			$totalpay = $totalpay - floatval($latefee);
			foreach($fees as $k => $f){
				if(($paidtotal + $totalpay) > 0 && (($paidtotal + $totalpay) - ($deducted + $deducted_now)) > 0){
					$late_fee = $latefee;
					$remaining = 0;
					// Todo calculate latefee in backend
					// if($latefee) {
					// 	$late_fee = $f->Latefee;
					// }
					// $totalFee = $f->fee + $late_fee;
					$totalFee = $f->fee;
					$remaining = ($paidtotal - $deducted) > $totalFee ? 0 : $totalFee - ($paidtotal - $deducted);
					$fee_maps[$f->id] = $remaining;
					$remaining_now = ($remaining - ($totalpay - $deducted_now)) < 0 ? 0 : $remaining - ($totalpay - $deducted_now);
					$current_payment[$f->id] = $remaining - $remaining_now;
					$deducted_now += $current_payment[$f->id];
					$paid = $totalFee - $fee_maps[$f->id];
					$deducted += $paid;

					$fee_collection[] = array(
						"billNo" => $billId,
						"class_id" => $class_id,
						"student_id" => $student_id,
						"fee_item" => $f->id,
						"payment" => $pid,
						"type" => $type,
						"latefee" => $late_fee,
						"discount" => $discount,
						"payableAmount" => $remaining,
						"paidAmount" => $current_payment[$f->id],
						"dueAmount" => $remaining_now,
						"payDate" => $payDate,
						"month" => $month,
						"academic_year" => $academic_year,
                        "created_at" => $timeStampNow,
                        "created_by" => $userId,
                        "updated_at" =>  $timeStampNow,  # new \Datetime()
                        'updated_by' => $userId,
					);
					// if((($paidtotal + $totalpay) - ($deducted + $deducted_now)) == 0) {
					// 	break;
					// }
				}else{
					$fee_collection[] = array(
						"billNo" => $billId,
						"class_id" => $class_id,
						"student_id" => $student_id,
						"fee_item" => $f->id,
						"payment" => $pid,
						"type" => $type,
						"latefee" => 0,
						"discount" => $discount,
						"payableAmount" => $f->fee,
						"paidAmount" => 0,
						"dueAmount" => $f->fee,
						"payDate" => $payDate,
						"month" => $month,
						"academic_year" => $academic_year,
                        "created_at" => $timeStampNow,
                        "created_by" => $userId,
                        "updated_at" =>  $timeStampNow,  # new \Datetime()
                        'updated_by' => $userId,
					);
				}
			}

			foreach($deletedFee as $feeEx) {
				$excluded[] = array(
					'feeitem' => $feeEx,
					'student_id' => $student_id
				);
			}

			// print_r($fee_collection);exit;
			DB::beginTransaction();
			try {
	
				FeeCol::insert($fee_collection);
				FeeHistory::insert($fee_collection);
				if(!empty($excluded)){
					ExcludedFees::insert($excluded);
				}
				DB::commit();
				$sendNotification = AppHelper::getAppSettings('fee_payment_notification');
				if($sendNotification != "0") {
					if($sendNotification == "1"){
						$totalpay = $totalpay - $discount;
						AppHelper::sendFeePaymentForStudentViaSMS($student_id, AppHelper::FEE_TYPES[$type], $totalpay, $dueAmount, $payDate);
					}
				}
				return "Fee payment recorded!. You can print the <strong><a href=\"/fees/details/".$billId."/print\" target=\"_blank\">invoice here.</a></strong>";
			}
			catch(\Exception $e){
				DB::rollback();
				throw new \Exception($e->getMessage());
			}
		}
		else {
			$messages = $validator->errors();
			$messages->add('Validator!', 'Please add atlest one fee!!!');
		}
	}

	private function getFeeMultiple($ids)	{
		$fees= FeeSetup::select('id', 'Latefee', 'fee')->wherein('id', $ids)->get()->all();
		return $fees;
	}

	private function paidTotal($ids, $student_id, $month, $academic_year){
		$query = FeeCol::select((DB::RAW('IFNULL(sum(paidAmount),0) as paidamount')))
		->wherein('fee_item', $ids)
        ->where('academic_year', $academic_year)
		->where('student_id', $student_id);
		if($month && $month != "0"){
			$query->where('month', $month);
		}
		$paid = $query->first();

		return floatval($paid->paidamount);
	}

	private function getLastFeeNumber($prefix){
		$bill = FeeCol::select('billNo')
			->where('billNo', 'like', '%' . $prefix . '%')
			->orderBy('id', 'DESC')
			// ->orderByRaw('UNIX_TIMESTAMP(payDate) DESC')
			->first();
		$bill_no = [];
		if(isset($bill)){
			$bill_no = explode("-", $bill->billNo);
		}
		if(AppHelper::FEERECIPT_INTER){
			$num = count($bill_no) > 1 ? $bill_no[2] : 0;
		}else{
			$num = count($bill_no) > 1 ? $bill_no[1] : 0;
		}
		return $num;
	}

	private function totalPaid($class_id, $student_id, $type, $date, $year){
		$paid = FeeCol::select((DB::RAW('IFNULL(sum(paidAmount),0) as paidamount')))
		->where('class_id',$class_id)
		->where('student_id',$student_id)
        ->where('academic_year', $year)
		->where('payDate', '<=', date($date))
		->where('type',$type)
		->pluck('paidamount')->sum();
		return $paid;
	}
	private function  parseAppDate($datestr)
	{
		$date = explode('/', $datestr);
		return $date[2].'-'.$date[1].'-'.$date[0];
	}
	private function  getAppdate($datestr)
	{
		$date = explode('-', $datestr);
		return $date[2].'/'.$date[1].'/'.$date[0];
	}

	private function base64_encode_image ($file=string) {
		if ($file) {
			$imgbinary = file_get_contents($file);
			$file_info = new finfo(FILEINFO_MIME_TYPE);
			$mime_type = $file_info->buffer($imgbinary);
			return 'data:' . $mime_type . ';base64,' . base64_encode($imgbinary);
		}
	}

	private function getFeeListByClassAndType($class_id, $type, $sid)	{
		$query= FeeSetup::select('id', 'fee')
		->where('type','=',$type)
		->whereHas('class', function($query) use ($class_id){
			$query->where('class_id','=',$class_id);
		});		
		if($type == AppHelper::TRANSPORT) {
			$student = AppHelper::getStudentByID($sid);
			$query->where('zone', '=', $student->transport_zone);	
		}
		$fees = $query->pluck('fee', 'id')->all();
		return $fees;
	}
	/**
	 * Number to Words.
	 */
	private function numberTowords($number){ 
		$no = round($number);
		$point = round($number - $no, 2) * 100;
		$hundred = null;
		$digits_1 = strlen($no);
		$i = 0;
		$str = array();
		$words = array('0' => '', '1' => 'one', '2' => 'two',
		'3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
		'7' => 'seven', '8' => 'eight', '9' => 'nine',
		'10' => 'ten', '11' => 'eleven', '12' => 'twelve',
		'13' => 'thirteen', '14' => 'fourteen',
		'15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
		'18' => 'eighteen', '19' =>'nineteen', '20' => 'twenty',
		'30' => 'thirty', '40' => 'forty', '50' => 'fifty',
		'60' => 'sixty', '70' => 'seventy',
		'80' => 'eighty', '90' => 'ninety');
		$digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
		while ($i < $digits_1) {
			$divider = ($i == 2) ? 10 : 100;
			$number = floor($no % $divider);
			$no = floor($no / $divider);
			$i += ($divider == 10) ? 1 : 2;
			if ($number) {
				$plural = (($counter = count($str)) && $number > 9) ? 's' : null;
				$hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
				$str [] = ($number < 21) ? $words[$number] .
					" " . $digits[$counter] . $plural . " " . $hundred
					:
					$words[floor($number / 10) * 10]
					. " " . $words[$number % 10] . " "
					. $digits[$counter] . $plural . " " . $hundred;
			} else $str[] = null;
		}
		$str = array_reverse($str);
		$result = implode('', $str);
		$points = ($point) ?
			"." . $words[$point / 10] . " " . 
				$words[$point = $point % 10] : '';
		$result .= "Rupees ";
		if($points) {
			$result .=  $points . " Paise";
		}
		return $result; 
	} 
}
