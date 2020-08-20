<?php

namespace App\Http\Controllers\Backend;

use App\PreAdmissionForm;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\AppMeta;
use Carbon\Carbon;

class PreAdmissionFormController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $fields = PreAdmissionForm::get();
        $pre_admission_start_date = AppMeta::where('meta_key', 'pre_admission_start_date')->select('meta_value')->first();
        $pre_admission_start_date = (!empty($pre_admission_start_date->meta_value)) ? Carbon::createFromFormat('Y-m-d', $pre_admission_start_date->meta_value)->format('d/m/Y') : date('d/m/Y');
        $pre_admission_end_date = AppMeta::where('meta_key', 'pre_admission_end_date')->select('meta_value')->first();
        $pre_admission_end_date = (!empty($pre_admission_end_date->meta_value)) ? Carbon::createFromFormat('Y-m-d', $pre_admission_end_date->meta_value)->format('d/m/Y') : date('d/m/Y');
        return view('backend.pre-admission.list', compact('fields', 'pre_admission_start_date', 'pre_admission_end_date'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\PreAdmissionForm  $preAdmissionForm
     * @return \Illuminate\Http\Response
     */
    public function show(PreAdmissionForm $preAdmissionForm)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\PreAdmissionForm  $preAdmissionForm
     * @return \Illuminate\Http\Response
     */
    public function edit(PreAdmissionForm $preAdmissionForm)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PreAdmissionForm  $preAdmissionForm
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PreAdmissionForm $preAdmissionForm)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\PreAdmissionForm  $preAdmissionForm
     * @return \Illuminate\Http\Response
     */
    public function destroy(PreAdmissionForm $preAdmissionForm)
    {
        //
    }

    /**
     * status change
     * @return mixed
     */
    public function changeStatus(Request $request, $id = 0, $type = 'status') {
        $field = PreAdmissionForm::find($id);
        if (!$field) {
            return [ 'success' => false, 'message' => 'Record not found!' ];
        }
        $newStatus = (string)$request->get('status');
        if($type == 'status') {
            $field->status = $newStatus;
        } elseif($type == 'mandatory') {
            $field->mandatory = $newStatus;
        } elseif($type == 'initial_fields') {
            $field->initial_fields = $newStatus;
        }
        $message = 'Something went wrong!';
        DB::beginTransaction();
        try {
            $field->save();
            DB::commit();
            return [ 'success' => true, 'message' => 'Status updated.' ];
        } catch (\Exception $e) {
            DB::rollback();
            $message = str_replace(array("\r", "\n", "'", "`"), ' ', $e->getMessage());
        }
        return [ 'success' => false, 'message' => $message ];
    }

    /*
     * Update Pre Admission Period
     */
    public function updatePeriod(Request $request) {
        $this->validate($request, [
            'pre_admission_start_date' => 'required',
            'pre_admission_end_date' => 'required',
        ]);
        try {
            AppMeta::updateOrCreate(
                ['meta_key' => 'pre_admission_start_date'],
                ['meta_value' => Carbon::createFromFormat('d/m/Y', $request->get('pre_admission_start_date'))->format('Y-m-d')]
            );
            AppMeta::updateOrCreate(
                ['meta_key' => 'pre_admission_end_date'],
                ['meta_value' => Carbon::createFromFormat('d/m/Y', $request->get('pre_admission_end_date'))->format('Y-m-d')]
            );
            return redirect()->route('pre-admission.index')->with('success', "Pre Admission Period updated successfully.");
        } catch (\Exception $e) {
            return redirect()->route('pre-admission.index')->with('error', "Something went wrong. Please try again.");
        }
    }

}
