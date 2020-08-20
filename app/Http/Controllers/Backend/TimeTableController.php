<?php

namespace App\Http\Controllers\Backend;

use App\AppMeta;
use App\AcademicYear;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Section;
use App\Http\Controllers\Controller;
use App\Subject;
use App\TimeTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use stdClass;

class TimeTableController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        // Build class list
        $classlist = [];
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order', 'asc')
            ->pluck('name', 'id');

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

        return view('backend.timetable.index', compact(
            'classlist'
        ));
    }

    /**
     * @return array
     */
    private function getDays()
    {
        $allDays = [
            '0' => 'Sunday',
            '1' => 'Monday',
            '2' => 'Tuesday',
            '3' => 'Wednesday',
            '4' => 'Thursday',
            '5' => 'Friday',
            '6' => 'Saturday'
        ];
        $metas = AppMeta::pluck('meta_value','meta_key');
        $weekends = json_decode($metas['weekends'], true);
        $days = [];
        foreach ($allDays as $key=>$day) {
            if (!in_array($key, $weekends)) {
                $days[$key] = $day;
            }
        }
        return $days;
    }

    /**
     * @param $number
     * @return mixed
     */
    private function getDayByNumber($number)
    {
        $allDays = [
            '0' => 'Sunday',
            '1' => 'Monday',
            '2' => 'Tuesday',
            '3' => 'Wednesday',
            '4' => 'Thursday',
            '5' => 'Friday',
            '6' => 'Saturday'
        ];
        foreach ($allDays as $key=>$day) {
            if ($key == $number) {
                return $day;
            }
        }
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
        $days = $this->getDays();
        return  view('backend.timetable.create-edit', compact('classes', 'weekends', 'days'));
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

//        $data = TimeTable::with('subject')->where('i_class_id', $class)->where('section_id', $section)->get();
//
//        $events = [];
//        if($data->count()) {
//            foreach ($data as $value) {
//                if (!$value->full_month) {
//                    $from = Carbon::parse($value->from);
//                    $to = Carbon::parse($value->to);
//                    $diff = $to->diffInDays($from);
//
//                    if ($diff) {
//                        for ($i = 0; $i < $diff; $i++) {
//                            $from = Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->addDays($i)->format('Y-m-d H:i:s');
//                            $to = Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->addDays($i)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->format('H:i:s');
//                            if ($this->checkWeekends($from)) {
//                                continue;
//                            }
//                            $events[] = Calendar::event(
//                                $value->subject->name,
//                                false,
//                                new \DateTime($from),
//                                new \DateTime($to),
//                                null,
//                                [
//                                    'color' => '#f05050',
//                                    'url' => route('timetables.edit', $value->id),
//                                ]
//                            );
//                        }
//                    } else {
//                        $events[] = Calendar::event(
//                            $value->subject->name,
//                            false,
//                            new \DateTime($value->from),
//                            new \DateTime($value->to),
//                            null,
//                            [
//                                'color' => '#f05050',
//                                'url' => route('timetables.edit', $value->id),
//                            ]
//                        );
//                    }
//                }
//                if ($value->monthly_repeat) {
//                    for ($i = 1; $i < 13; $i++) {
//                        $from2 = Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->addMonths($i)->format('Y-m-d H:i:s');
//                        $to2 = Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->addMonths($i)->format('Y-m-d H:i:s');
//                        if (!$value->full_month) {
//                            $from = Carbon::parse($from2);
//                            $to = Carbon::parse($to2);
//                            $diff = $to->diffInDays($from);
//
//                            if ($diff) {
//                                for ($j = 0; $j <= $diff; $j++) {
//                                    $from1 = Carbon::createFromFormat('Y-m-d H:i:s', $from)->addDays($j)->format('Y-m-d H:i:s');
//                                    $to1 = Carbon::createFromFormat('Y-m-d H:i:s', $from)->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $to)->format('H:i:s');
//                                    if ($this->checkWeekends($from1)) {
//                                        continue;
//                                    }
//                                    $events[] = Calendar::event(
//                                        $value->subject->name,
//                                        false,
//                                        new \DateTime($from1),
//                                        new \DateTime($to1),
//                                        null,
//                                        [
//                                            'color' => '#f05050',
//                                            'url' => route('timetables.edit', $value->id),
//                                        ]
//                                    );
//                                }
//                            } else {
//                                $events[] = Calendar::event(
//                                    $value->subject->name,
//                                    false,
//                                    new \DateTime($value->from),
//                                    new \DateTime($value->to),
//                                    null,
//                                    [
//                                        'color' => '#f05050',
//                                        'url' => route('timetables.edit', $value->id),
//                                    ]
//                                );
//                            }
//                        }
//                    }
//                }
//                if ($value->full_month) {
//                    if ($value->monthly_repeat) {
//                        for ($j = 0; $j < 365; $j++) {
//                            $from1 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->format('H:i:s');
//                            $to1 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->format('H:i:s');
//                            if ($this->checkWeekends($from1)) {
//                                continue;
//                            }
//                            $events[] = Calendar::event(
//                                $value->subject->name,
//                                false,
//                                new \DateTime($from1),
//                                new \DateTime($to1),
//                                null,
//                                [
//                                    'color' => '#f05050',
//                                    'url' => route('timetables.edit', $value->id),
//                                ]
//                            );
//                        }
//                    } else {
//                        $now = Carbon::createFromFormat('Y-m-d H:s:i', Carbon::now());
//                        for ($j = 0; $j < 30; $j++) {
//                            $from1 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->format('H:i:s');
//                            $to1 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->format('H:i:s');
//                            $fro12 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j+2)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->format('H:i:s');
//                            $from11 = Carbon::createFromFormat('Y-m-d H:s:i',$fro12);
//                            if ($from11->diffInMonths($now)) {
//                                break;
//                            }
//                            if ($this->checkWeekends($from1)) {
//                                continue;
//                            }
//                            $events[] = Calendar::event(
//                                $value->subject->name,
//                                false,
//                                new \DateTime($from1),
//                                new \DateTime($to1),
//                                null,
//                                [
//                                    'color' => '#f05050',
//                                    'url' => route('timetables.edit', $value->id),
//                                ]
//                            );
//                        }
//                    }
//                }
//            }
//        }
//
//        $calendar = Calendar::addEvents($events);
        $class = IClass::find($class);
        $section = Section::find($section);
        return view('backend.timetable.show', compact('class', 'section'));
    }

    /**
     * @param Request $request
     */
    public function loadEvents(Request $request)
    {
        $class = $request->class;
        $section = $request->section;
        $start = $request->start;
        $end = $request->end;

        $data = TimeTable::with('subject')
            ->where('i_class_id', $class)
            ->where('section_id', $section)
            ->get();

        $events = [];
        if($data->count()) {
            foreach ($data as $value) {
                if ($value->days_of_month == 9) {
                    if (!$value->full_month) {
                        $from = Carbon::parse($value->from);
                        $to = Carbon::parse($value->to);
                        $diff = $to->diffInDays($from);
                        if ($diff) {
                            for ($i = 0; $i < $diff; $i++) {
                                $from = Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->addDays($i)->format('Y-m-d H:i:s');
                                $to = Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->addDays($i)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->format('H:i:s');

                                if (strtotime($start) > strtotime($from)) continue;
                                if (strtotime($end) < strtotime($from)) break;

                                if ($this->checkWeekends($from)) {
                                    continue;
                                }
                                $events[] = [
                                    'id' => $value->id,
                                    'title'   => $value->subject->name,
                                    'start'   => $from,
                                    'end'   => $to
                                ];
                            }
                        } else {
                            $events[] = [
                                'id' => $value->id,
                                'title'   => $value->subject->name,
                                'start'   => $value->from,
                                'end'   => $value->to
                            ];
                        }
                    }
                    if ($value->monthly_repeat) {
                        for ($i = 1; $i < 13; $i++) {
                            $from2 = Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->addMonths($i)->format('Y-m-d H:i:s');
                            $to2 = Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->addMonths($i)->format('Y-m-d H:i:s');
                            if (!$value->full_month) {
                                $from = Carbon::parse($from2);
                                $to = Carbon::parse($to2);
                                $diff = $to->diffInDays($from);

                                if ($diff) {
                                    for ($j = 0; $j <= $diff; $j++) {
                                        $from1 = Carbon::createFromFormat('Y-m-d H:i:s', $from)->addDays($j)->format('Y-m-d H:i:s');
                                        $to1 = Carbon::createFromFormat('Y-m-d H:i:s', $from)->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $to)->format('H:i:s');

                                        if (strtotime($start) > strtotime($from1)) continue;
                                        if (strtotime($end) < strtotime($from1)) break;

                                        if ($this->checkWeekends($from1)) {
                                            continue;
                                        }
                                        $events[] = [
                                            'id' => $value->id,
                                            'title'   => $value->subject->name,
                                            'start'   => $from1,
                                            'end'   => $to1
                                        ];
                                    }
                                } else {
                                    if (strtotime($start) <= strtotime($from2) && strtotime($end) >= strtotime($from2)) {
                                        $events[] = [
                                            'id' => $value->id,
                                            'title'   => $value->subject->name,
                                            'start'   => $from2,
                                            'end'   => $to2
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    if ($value->full_month) {
                        if ($value->monthly_repeat) {
							$academic_year = AppHelper::getAcademicYear();
							if($academic_year) {
								$academic_years = AcademicYear::where('status', '1')->where('id', $academic_year)->get()->first();
								$from = Carbon::parse($academic_years->start_date);
								$to = Carbon::parse($academic_years->end_date);
                                $diff = $to->diffInDays($from);
								for ($j = 0; $j < $diff; $j++) {
									$from1 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->format('H:i:s');
									$to1 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->format('H:i:s');
	
									if (strtotime($start) > strtotime($from1)) continue;
									if (strtotime($end) < strtotime($from1)) break;
	
									if ($this->checkWeekends($from1)) {
										continue;
									}
									$events[] = [
										'id' => $value->id,
										'title'   => $value->subject->name,
										'start'   => $from1,
										'end'   => $to1
									];
								}
							}
                        } else {
                            $now = Carbon::createFromFormat('Y-m-d H:s:i', Carbon::now());
                            for ($j = 0; $j < 31; $j++) {
                                $from1 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->format('H:i:s');
                                $to1 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->format('H:i:s');
                                $fro12 = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->addDays($j+2)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->format('H:i:s');
                                $from11 = Carbon::createFromFormat('Y-m-d H:s:i',$fro12);

                                if (strtotime($start) > strtotime($from1)) continue;
                                if (strtotime($end) < strtotime($from1)) break;

                                if ($from11->diffInMonths($now)) {
                                    break;
                                }
                                if ($this->checkWeekends($from1)) {
                                    continue;
                                }
                                $events[] = [
                                    'id' => $value->id,
                                    'title'   => $value->subject->name,
                                    'start'   => $from1,
                                    'end'   => $to1
                                ];
                            }
                        }
                    }
                } else {
					if ((strtotime($start) >= strtotime($value->from)) || (strtotime($end) <= strtotime($value->to))) {
						$searchDay = $this->getDayByNumber($value->days_of_month);
						$searchDate = new Carbon();
						// if ($value->monthly_repeat) {
						// 	for ($j = 0; $j < 5; $j++) {
						// 		$from1 = Carbon::createFromTimeStamp(strtotime("$searchDay", strtotime($value->from)))->addWeek($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->format('H:i:s');
						// 		$to1 = Carbon::createFromTimeStamp(strtotime("$searchDay", strtotime($value->to)))->addWeek($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->format('H:i:s');

						// 		if (strtotime($start) > strtotime($from1)) continue;
						// 		if (strtotime($end) < strtotime($from1)) break;

						// 		if ($this->checkWeekends($from1)) {
						// 			continue;
						// 		}
						// 		$events[] = [
						// 			'id' => $value->id,
						// 			'title'   => $value->subject->name,
						// 			'start'   => $from1,
						// 			'end'   => $to1
						// 		];
						// 	}
						// } else {
							$now = $value->from;
							$from = Carbon::parse($value->from);
							$to = Carbon::parse($value->to);
							$diff = $to->diffInDays($from);
							for ($j = 0; $j < 6; $j++) {
								$from1 = Carbon::createFromTimeStamp(strtotime("$searchDay", strtotime($start)))->addWeek($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->from)->format('H:i:s');
								$to1 = Carbon::createFromTimeStamp(strtotime("$searchDay", strtotime($start)))->addWeek($j)->format('Y-m-d').' '.Carbon::createFromFormat('Y-m-d H:i:s', $value->to)->format('H:i:s');
								$from11 = Carbon::createFromFormat('Y-m-d H:s:i',$value->from);

								if (strtotime($value->from) >= strtotime($from1)) continue;
								if (strtotime($value->to) <= strtotime($to1)) break;

								if ($this->checkWeekends($from1)) {
									continue;
								}
								if ($from11->diffInMonths($now)) {
									break;
								}
								$events[] = [
									'id' => $value->id,
									'title'   => $value->subject->name,
									'start'   => $from1,
									'end'   => $to1
								];
							}
						// }
					}
                }
            }
        }
        echo json_encode($events);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function student()
    {
        $class = Auth::user()->student->register->class_id;
        $section = Auth::user()->student->register->section_id;

        $class = IClass::find($class);
        $section = Section::find($section);
        return view('backend.timetable.show', compact( 'class', 'section'));
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
                'section_id' => 'required',
                'subject_id' => 'required',
                'from' => 'required',
                'to' => 'required'
            ]
        );
//        if ($this->checkSlot($request->from, $request->to, $request->class_id, $request->subject_id)) {
//            return redirect()->back()->with("error", "Slot already added by other subject !");
//        }
        $timeTable = new TimeTable();
        $timeTable->i_class_id = $request->class_id;
        $timeTable->section_id = $request->section_id;
        $timeTable->subject_id = $request->subject_id;
        if ($request->full_month) {
            $timeTable->from = Carbon::now()->format('Y-m-d').' '.$request->on_from;
            $timeTable->to = Carbon::now()->format('Y-m-d').' '.$request->on_to;
        } elseif ($request->is_days_of_month) {
			$academic_year = AppHelper::getAcademicYear();
			if($academic_year) {
				$academic_years = AcademicYear::where('status', '1')->where('id', $academic_year)->get()->first();
				$timeTable->from = Carbon::parse($academic_years->start_date)->format('Y-m-d').' '.' '.$request->on_from;
				$timeTable->to = Carbon::parse($academic_years->end_date)->format('Y-m-d').' '.$request->on_to;
			}else {
				return redirect()->back()->with("error", "Update the institute setting with academic year to create day wise timetable.");
			}
		} else {
            $timeTable->from = $request->from;
            $timeTable->to = $request->to;
        }
        $timeTable->monthly_repeat = $request->monthly_repeat ?? 0;
        $timeTable->full_month = $request->full_month ?? 0;
        $timeTable->days_of_month = $request->is_days_of_month ? $request->days_of_month : 9;

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

        $slot = TimeTable::find($id);
        if (!$slot) {
            abort(404);
        }
        $classes = IClass::with('section', 'subject')->where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->select('name', 'id')->get();
        $section = Section::where('class_id', $slot->i_class_id)->select('name', 'id')->get();
        $subject = Subject::where('class_id', $slot->i_class_id)->select('name', 'id')->get();
        $days = $this->getDays();
        return  view('backend.timetable.create-edit', compact('slot', 'classes', 'section', 'subject', 'days', 'weekends'));
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
                'section_id' => 'required',
                'subject_id' => 'required',
                'from' => 'required',
                'to' => 'required'
            ]
        );
//        if ($this->checkSlot($request->from, $request->to, $request->class_id, $request->subject_id, $id)) {
//            return redirect()->back()->with("error", "Slot already added by other subject !");
//        }
        $timeTable = TimeTable::find($id);
        $timeTable->i_class_id = $request->class_id;
        $timeTable->section_id = $request->section_id;
        $timeTable->subject_id = $request->subject_id;
        if ($request->full_month) {
            $timeTable->from = Carbon::now()->format('Y-m-d').' '.$request->on_from;
            $timeTable->to = Carbon::now()->format('Y-m-d').' '.$request->on_to;
        } elseif ($request->is_days_of_month) {
			$academic_year = AppHelper::getAcademicYear();
			if($academic_year) {
				$academic_years = AcademicYear::where('status', '1')->where('id', $academic_year)->get()->first();
				$timeTable->from = $academic_years->start_date.' '.$request->on_from;
				$timeTable->to = $academic_years->end_date.' '.$request->on_to;
			}else {
				return redirect()->back()->with("error", "Update the institute setting with academic year to create day wise timetable.");
			}
		} else {
            $timeTable->from = $request->from;
            $timeTable->to = $request->to;
        }
        $timeTable->monthly_repeat = $request->monthly_repeat ?? 0;
        $timeTable->full_month = $request->full_month ?? 0;
        $timeTable->days_of_month = $request->is_days_of_month ? $request->days_of_month : 9;

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
        $timeTable = TimeTable::find($id);
        if ($timeTable) {
            $timeTable->delete();
            return redirect('/timetables')->with("success", "Slot deleted successfully.");
        } else {
            return redirect('/timetables')->with("error", "Something went wrong!");
        }
    }

    /**
     * @param $id
     * @return false|string
     */
    public function getSectionSubject($id)
    {
        $section = Section::where('class_id', $id)->pluck("name","id");
        $subject = Subject::where('class_id', $id)->pluck("name","id");
        return json_encode(['section' => $section, 'subject' => $subject]);
    }

    /**
     * @param $from
     * @param $to
     * @param $class
     * @param $section
     * @return bool
     */
    public function checkSlot($from, $to, $class, $section, $slotId = 0)
    {
        $from = Carbon::createFromFormat('Y-m-d H:i:s', $from)->format('Y-m-d');
        $to = Carbon::createFromFormat('Y-m-d H:i:s', $to)->format('Y-m-d');
        if ($slotId) {
            $check = TimeTable::where('i_class_id', $class)
                ->where('i_class_id', $class)
                ->where('id', '<>', $slotId)
                ->where('section_id', $section)
                ->where(function ($query) use($from, $to) {
                    $query->where('from' , '>', $from)->where('from', '<', $to);
                })
                ->orWhere(function ($query) use($from, $to) {
                    $query->where('to' , '>', $from)->where('to', '<', $to);
                })
                ->first();
        } else {
            $check = TimeTable::where('i_class_id', $class)
                ->where('i_class_id', $class)
                ->where('section_id', $section)
                ->where(function ($query) use($from, $to) {
                    $query->where('from' , '>', $from)->where('from', '<', $to);
                })
                ->orWhere(function ($query) use($from, $to) {
                    $query->where('to' , '>', $from)->where('to', '<', $to);
                })
                ->first();
        }
        if ($check) {
            return true;
        } else {
            return false;
        }
    }
}
