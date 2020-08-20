<?php

namespace App\Http\Controllers\API;

use Log;
use App\ExamTimeTable;
use App\TimeTable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;

class TimeTableController extends Controller
{
    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        if ($id) {
            $sectionId = $id;
        } else {
            $sectionId = $user->student->register->section_id;
        }
        $timetables = TimeTable::with('subject')
            ->where('section_id', $sectionId)
            ->get();
        foreach ($timetables as &$timetable) {
            $subject = $timetable->subject->name;
            unset($timetable->subject);
            $timetable->subject = $subject;
            unset($timetable->created_at);
            unset($timetable->updated_at);
            unset($timetable->i_class_id);
            unset($timetable->section_id);
            unset($timetable->subject_id);
        }
        return response()->json($timetables);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validate(
            $request, [
                'class_id' => 'required',
                'section_id' => 'required',
                'subject_id' => 'required',
                'from' => 'required',
                'to' => 'required',
                'monthly_repeat' => 'required',
                'full_month' => 'required'
            ]
        );
        try {
            $timeTable = new TimeTable();
            $timeTable->i_class_id = $request->class_id;
            $timeTable->section_id = $request->section_id;
            $timeTable->subject_id = $request->subject_id;
            $timeTable->from = $request->from;
            $timeTable->to = $request->to;
            $timeTable->monthly_repeat = $request->monthly_repeat ?? 0;
            $timeTable->full_month = $request->full_month ?? 0;

            if ($timeTable->save()) {
                return response()->json(['success' => true, 'message' =>
                    'Timetable saved successfully.'], 200);
            } else {
                return response()->json(['success' => false, 'message' => 'Something went wrong!'], 200);
            }
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 200);
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExamTimeTables($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        try{
            if ($id) {
                $sectionId = $id;
            } else {
                $sectionId = $user->student->register->section_id;
            }
            $timetables = ExamTimeTable::with('subject', 'exam')
                ->where('section_id', $sectionId)
                ->get()->groupBy('exam.name');
            foreach ($timetables as &$exam) {
                foreach($exam as &$timetable){
                    $subject = $timetable->subject->name;
                    $exam = $timetable->exam->name;
                    unset($timetable->subject);
                    unset($timetable->exam);
                    $timetable->subject = $subject;
                    $timetable->exam = $exam;
                    unset($timetable->created_at);
                    unset($timetable->updated_at);
                    unset($timetable->i_class_id);
                    unset($timetable->section_id);
                    unset($timetable->exam_id);
                    unset($timetable->subject_id);
                }
            }
            return response()->json(['success' => true, 'message' => 'Exam timetable retrieved successfully.', 'data'=> $timetables], 200);
        } catch (\Exception $exception) {
            Log::error($exception);
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => $exception->getMessage()], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeExamTimeTables(Request $request)
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
        try {
            $timeTable = new ExamTimeTable();
            $timeTable->i_class_id = $request->class_id;
            $timeTable->exam_id = $request->exam_id;
            $timeTable->subject_id = $request->subject_id;
            $timeTable->section_id = $request->section_id;
            $timeTable->from = $request->from;
            $timeTable->to = $request->to;
            if ($timeTable->save()) {
                return response()->json(['success' => true, 'message' =>
                    'Timetable saved successfully.'], 200);
            } else {
                return response()->json(['success' => false, 'message' => 'Something went wrong!'], 200);
            }
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 200);
        }
    }
}
