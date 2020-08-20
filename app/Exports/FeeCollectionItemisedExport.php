<?php
namespace App\Http\Exports;

use \stdClass;
use App\Student;
use App\FeeSetup;
use App\Http\Helpers\AppHelper;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;

class FeeCollectionItemisedExport implements FromView
{
    use Exportable;

    private $feeitems = array();

    public function __construct($academic_year=NULL, $sDate=NULL, $eDate=NULL, $type=array(), $class_id=NULL, $section_id=NULL, $feeitems=array())
    {
        $this->sDate = $sDate;
        $this->eDate = $eDate;
        $this->types = $type;
        $this->class_id = $class_id;
        $this->section_id = $section_id;
        $this->feeitems = $feeitems;
        $this->academic_year = $academic_year;
    }
    
    public function view(): View
    {
		$selectedItems = FeeSetup::whereIn('id', $this->feeitems)->get()->all();

        $result = AppHelper::getCollectedFeeList($this->academic_year, $this->sDate, $this->eDate, $this->types, $this->class_id, $this->section_id, $this->feeitems, FALSE, TRUE);

        $data = $result->groupBy('class')->transform(function($item, $k) {
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

		$report = ['result' => $result, 'data' => $data];
        return view('backend.fees.report.itemisedexport', compact('report', 'selectedItems'));
    }
}