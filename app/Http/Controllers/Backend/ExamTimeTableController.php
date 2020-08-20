<?php

namespace App\Http\Controllers\Backend;

use App\AppMeta;
use App\Exam;
use App\ExamTimeTable;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Section;
use App\Subject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use stdClass;

class ExamTimeTableController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        // Build class list
        $classlist = [];
        $roleId = Auth::user()->role->role_id;
        if ($roleId == AppHelper::USER_TEACHER) {
            $classes = IClass::with('subject.teacher')
                ->whereHas('subject.teacher', function ($query) {
                    return $query->where('user_id', Auth::id());
                })
                ->where('status', AppHelper::ACTIVE)
                ->orderBy('order', 'asc')
                ->pluck('name', 'id');
        } else {
            $classes = IClass::where('status', AppHelper::ACTIVE)
                ->orderBy('order', 'asc')
                ->pluck('name', 'id');
        }

        foreach ($classes as $class_id => $class) {
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
            }
        }

        return view('backend.exam-timetable.index', compact(
            'classlist'
        ));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        $metas = AppMeta::pluck('meta_value','meta_key');
        $weekends = isset($metas['weekends']) ? json_decode($metas['weekends'], true) : [-1];

        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->select('name', 'id')->get();

        return  view('backend.exam-timetable.create-edit', compact('classes', 'weekends'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $class = $request->class;
        $section = $request->section;

        $class = IClass::find($class);
        $section = Section::find($section);
        return view('backend.exam-timetable.show', compact('class', 'section'));
    }

    /**
     * @param Request $request
     */
    public function loadEvents(Request $request)
    {
        $class = $request->class;
        $section = $request->section;

        $data = ExamTimeTable::with('subject', 'exam')
            ->where('i_class_id', $class)
            ->where('section_id', $section)
            ->get();

        $events = [];
        if($data->count()) {
            foreach ($data as $value) {
                $events[] = [
                    'id' => $value->id,
                    'title'   => $value->subject->name."(".$value->exam->name.")",
                    'start'   => $value->from,
                    'end'   => $value->to
                ];
            }
        }
        echo json_encode($events);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function student()
    {
        $roleId = Auth::user()->role->role_id;
        if ($roleId != AppHelper::USER_STUDENT) {
            return abort(403);
        }
        $class = Auth::user()->student->register->class_id;
        $section = Auth::user()->student->register->section_id;

        $class = IClass::find($class);
        $section = Section::find($section);
        return view('backend.exam-timetable.show', compact( 'class', 'section'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $this->validate(
            $request, [
                'class_id' => 'required',
                'exam_id' => 'required',
                'subject_id' => 'required',
                'section_id' => 'required',
                'from' => 'required',
                'to' => 'required'
            ]
        );

        $timeTable = new ExamTimeTable();
        $timeTable->i_class_id = $request->class_id;
        $timeTable->exam_id = $request->exam_id;
        $timeTable->subject_id = $request->subject_id;
        $timeTable->section_id = $request->section_id;
        $timeTable->from = $request->from;
        $timeTable->to = $request->to;
        if ($timeTable->save()) {
            return redirect()->back()->with("success", "Slot added successfully.");
        } else {
            return redirect()->back()->with("error", "Something went wrong!");
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($id)
    {
        $metas = AppMeta::pluck('meta_value','meta_key');
        $weekends = isset($metas['weekends']) ? json_decode($metas['weekends'], true) : [-1];

        $slot = ExamTimeTable::find($id);
        if (!$slot) {
            abort(404);
        }
        $classes = IClass::where('status', AppHelper::ACTIVE)->select('name', 'id')->get();
        $exams = Exam::where('class_id', $slot->i_class_id)->select('name', 'id')->get();
        $subject = Subject::where('class_id', $slot->i_class_id)->select('name', 'id')->get();
        $section = Section::where('class_id', $slot->i_class_id)->select('name', 'id')->get();
        return  view('backend.exam-timetable.create-edit', compact('slot', 'classes', 'exams', 'section', 'subject', 'weekends'));
    }

    /**
     * @param $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update($id, Request $request)
    {
        $this->validate(
            $request, [
                'class_id' => 'required',
                'exam_id' => 'required',
                'subject_id' => 'required',
                'section_id' => 'required',
                'from' => 'required',
                'to' => 'required'
            ]
        );

        $timeTable = ExamTimeTable::find($id);
        $timeTable->i_class_id = $request->class_id;
        $timeTable->exam_id = $request->exam_id;
        $timeTable->section_id = $request->section_id;
        $timeTable->subject_id = $request->subject_id;
        $timeTable->from = $request->from;
        $timeTable->to = $request->to;

        if ($timeTable->save()) {
            return redirect()->back()->with("success", "Slot updated successfully.");
        } else {
            return redirect()->back()->with("error", "Something went wrong!");
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $timeTable = ExamTimeTable::find($id);
        if ($timeTable) {
            $timeTable->delete();
            return redirect('/exam-timetables')->with("success", "Slot deleted successfully.");
        } else {
            return redirect('/exam-timetables')->with("error", "Something went wrong!");
        }
    }

    /**
     * @param $id
     * @return false|string
     */
    public function getExamSubject($id)
    {
        $exam = Exam::where('class_id', $id)->pluck("name","id");
        $subject = Subject::where('class_id', $id)->pluck("name","id");
        $section = Section::where('class_id', $id)->pluck("name","id");
        return json_encode(['exam' => $exam, 'subject' => $subject, 'section' => $section]);
    }
}
