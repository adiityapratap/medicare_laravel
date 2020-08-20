<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\AppHelper;
use App\Jobs\ProcessSms;
use App\Registration;
use App\AppMeta;
use App\FeeSetup;
use App\FeeCol;

class FeePaymentAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feepayment:alert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send SMS notifications to the students of a specific class for the fee payment reminder';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {        
        $gateway = AppMeta::where('id', AppHelper::getAppSettings('message_center_gateway'))->first();
        if(!$gateway){
            Log::channel('studenthomeworklog')->error("SMS Gateway not setup!");
            return;
        }
        $gateway = json_decode($gateway->meta_value);
		$feelist = DB::Table('fee_setup as fs')->select(
			'fs.title', 'fs.id', 'fs.fee', 'fs.installments', 'fs.type', 'fc.class_id', 
			'fi.due_date', 'fi.inst_fee', 'fi.inst_type', 'fi.latefee', 'fi.lftype as latefee_type'
		)
		->join('fee_setup_class_map as fc', 'fc.fee_item', '=', 'fs.id')
		->join('fee_installments as fi', 'fi.feeitem', '=', 'fs.id')
		->where('fi.due_date', '<=', DB::RAW('DATE(NOW() + INTERVAL 3 DAY) + INTERVAL 0 SECOND'))
		->where('fi.due_date', '>=', DB::RAW('DATE(NOW()) + INTERVAL 0 SECOND'))
		->whereNull('fs.deleted_at')
		->groupBy('fi.due_date', 'fs.title', 'fs.id', 'fs.fee', 'fs.installments', 'fs.type', 'fc.class_id', 
		'fi.inst_fee', 'fi.inst_type', 'fi.latefee', 'fi.lftype')
        ->get();
        
		$feeByClass = $feelist->groupBy('class_id');
		foreach($feeByClass as $cl => $installments) {
			$stdinfo = Registration::select("*")
				->where('class_id', $cl)
				->with('student')
				->get();
			foreach($stdinfo as $studs) {
				$message = 'Dear Parent, Your child\'s school fee due date is on {{duedate}} and remaining amount is {{due}}. Please pay before the last date to avoid the penalty. -Principal';
                $payables = ['ids' => [], 'total' => 0, 'latefee_type' => 'fixed', 'latefee' => 0, 'duedate'=> 0];
				foreach($installments as $inst) {
					array_push($payables['ids'], $inst->id);
					$payables['total'] += floatval($inst->inst_fee);
					$payables['latefee_type'] = $inst->latefee_type;
					$payables['latefee'] = $inst->latefee;
					$payables['duedate'] = !$payables['duedate'] ? $inst->due_date: (strtotime($payables['duedate']) > strtotime($inst->due_date)? $inst->due_date: $payables['duedate']);
				}
				$paid = $this->paidTotal($payables['ids'], $studs->student->id, 0);
				if(($payables['total'] - $paid) > 0) {
					$cellNumber = AppHelper::validateIndianCellNo($studs->student->father_phone_no);
					if(!$cellNumber) {
						$cellNumber = AppHelper::validateIndianCellNo($studs->student->mother_phone_no);
						if(!$cellNumber) {
							$cellNumber = AppHelper::validateIndianCellNo($studs->student->guardian_phone_no);
							if(!$cellNumber) {
								$cellNumber = AppHelper::validateIndianCellNo($studs->student->phone_no);
							}
						}
					}
					$keywords = array(
						'sid' => $studs->student->id,
						'total' => $payables['total'],
						'due' => ($payables['total'] - $paid),
						'latefee' => $payables['latefee'],
						'latefee_type' => $payables['latefee_type'],
						'phone' => $cellNumber,
						'duedate' => date('d-m-Y', strtotime($payables['duedate']))
                    );
					foreach ($keywords as $key => $value) {
                        if(is_string($value) || is_int($value) || is_float($value)){
                            $message = str_replace('{{' . $key . '}}', $value, $message);
                        }
                    }
                    if($cellNumber) {
                        ProcessSms::dispatch($gateway, array($cellNumber), $message)->onQueue('sms');
                    } else {
                        Log::channel('smsLog')->error("Invalid Cell No! ".$studs->father_phone_no);
                    }
				}
			}
		}
    }

	private function paidTotal($ids, $student_id, $month){
		$query = FeeCol::select((DB::RAW('IFNULL(sum(paidAmount),0) as paidamount')))
		->wherein('fee_item', $ids)
		->where('student_id', $student_id);
		if($month && $month != "0"){
			$query->where('month', $month);
		}
		$paid = $query->first();

		return floatval($paid->paidamount);
	}
}
