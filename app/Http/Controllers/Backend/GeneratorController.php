<?php

namespace App\Http\Controllers\Backend;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Http\Helpers\AppHelper;
use App\Jobs\CreateOrUpdateUsernames;
use App\IClass;
use App\Student;
use App\Employee;
use App\Section;

class GeneratorController extends Controller
{
    use DispatchesJobs;

    public function generateUsername(Request $request)
    {
        $prefixes = [
            '' => 'None',
            'shortname' => 'Inst. Code',
        ];
        $suffixes = [
            'number' => 'Incremental Number',
            'names' => 'Characters from Name',
        ];
        $prefix = 'shortname';
        $infix = null;
        $suffix = 'number';
        $message = null;
        $students = null;
        $staff = null;
        $sections = [];
        $iclass = Input::get('class_id', 0);
        $section_id = Input::get('section_id', 0);
        $sms = "0";
        
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');

        if ($request->isMethod('post')) {
			//validate form
			$messages = [
				'message.required' => 'Message template must be enterd to send SMS.',
			];

			$rules = [
				'prefix' => 'required',
				'suffix' => 'required',
			];
			$sms = Input::get('sms');

			if($sms == 'yes') {
				$rules['message'] = 'required|min:5';
			}

			$prefix = Input::get('prefix');
			$infix = Input::get('infix');
			$suffix = Input::get('suffix');
			$message = Input::get('message');
			$students = Input::get('students');
			$staff = Input::get('staff');

            $this->validate($request, $rules, $messages);
            
            $stafflist = [];
            $studentlist = [];
			if($students == 'students') {
                $query = Student::select('*');
                if($iclass) {
                    $query->whereHas('registration' , function ($query) use($iclass, $section_id) {
                        $query->where('class_id', $iclass);
                        if($section_id) {
                            $query->where('section_id', $section_id);
                        }
                    });
                }
                $studentlist = $query->get()->all();
			}
			if($staff == 'staff') {
                $stafflist = Employee::select('user_id', 'name', 'phone_no')->get()->all();
            }
            
            $job = new CreateOrUpdateUsernames($studentlist, $stafflist, $prefix, $infix, $suffix, $message);
            $this->dispatchNow($job);
    
            $jobStatusId = $job->getJobStatusId();

            if($iclass){
                $sections = Section::where('status', AppHelper::ACTIVE)
                    ->where('class_id', $iclass)
                    ->pluck('name', 'id');

            }
            
            return redirect()->route('administrator.generators.username')->with(
                'success', 
                "The updation of usernames are queued and will be completed soon.");
			// return redirect()->action('\Imtigger\LaravelJobStatus\ProgressController@progress', [$jobStatusId]);
        }
        return view('backend.generators.updateusername', compact(
            'classes', 'sections', 'iclass', 'section_id', 'prefixes', 'suffixes', 'prefix', 'infix', 'suffix', 'message', 'sms', 'students', 'staff'
        ));
    }
}
