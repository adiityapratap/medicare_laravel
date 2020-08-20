<?php

use Illuminate\Support\Facades\Session;
use App\Http\Helpers\AppHelper;
use \Imtigger\LaraelJobStatus\ProgressController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Route::group(['middleware' => 'role:admin'], function() {
//    Route::get('/admin', function() {
//        return 'Welcome Admin';
//    });
//});


/**
 * Admin panel routes goes below
 */
Route::group(
    ['namespace' => 'Backend', 'middleware' => ['guest']], function () {
    Route::get('/login', 'UserController@login')->name('login');
    Route::post('/login', 'UserController@authenticate');
    Route::get('/forgot', 'UserController@forgot')->name('forgot');
    Route::post('/forgot', 'UserController@forgot')
        ->name('forgot');
    Route::get('/reset/{token}', 'UserController@reset')
        ->name('reset');
    Route::post('/reset/{token}', 'UserController@reset')
        ->name('reset');
}
);

Route::get('/public/exam', 'Backend\ExamController@indexPublic')->name('public.exam_list');
Route::any('/online-result', 'Backend\ReportController@marksheetPublic')->name('report.marksheet_pub');

Route::group(
    ['namespace' => 'Backend', 'middleware' => ['auth', 'permission']], function () {
    // ['namespace' => 'Backend', 'middleware' => ['auth']], function () {
    Route::get('/logout', 'UserController@logout')->name('logout');
    Route::get('/lock', 'UserController@lock')->name('lockscreen');
    Route::get('/dashboard', 'UserController@dashboard')->name('user.dashboard');
    
    //feedback form
    Route::resource('feedback','FeedbackController');
    Route::resource('question','QuestionController');

    //message
    Route::get('/message/{type?}', 'UserController@message')
        ->name('message');
    Route::post('/storemessage', 'UserController@storemessage')
        ->name('storemessage');
    Route::get('/sentMessages/{type?}', 'UserController@sentMessages')
        ->name('sentMessages');

    // Get Message Details
    Route::post('get/messages', 'UserController@getMessageDetails')
        ->name('get.message-details');


    //Circular - notification
    Route::get('/send-circular', 'CircularController@getCircularCreate')->name('send-circular');
    Route::post('/store-circular', 'CircularController@storeSendCircular')->name('store-circular');
    Route::get('/list-sent-circular', 'CircularController@listAllCircular')->name('all-sent-circular');
        Route::post('get/circular-message', 'CircularController@getCircularMessage')
                ->name('get.circular-message');

    //announcement : seperate route and view required according to vikas
    Route::get('/send-announcement', 'AnnouncementController@getAnnouncementCreate')->name('send-announcement');
    Route::post('/store-announcement', 'AnnouncementController@storeSendAnnouncement')->name('store-announcement');
    Route::get('/list-sent-announcement', 'AnnouncementController@listAllAannouncement')->name('all-sent-announcement');

    //user management
    Route::resource('user', 'UserController');
    Route::get('/profile', 'UserController@profile')
        ->name('profile');
    Route::post('/profile', 'UserController@profile')
        ->name('profile');
    Route::get('/change-password', 'UserController@changePassword')
        ->name('change_password');
    Route::post('/change-password', 'UserController@changePassword')
        ->name('change_password');
    Route::post('user/status/{id}', 'UserController@changeStatus')
        ->name('user.status');
    Route::any('user/{id}/permission', 'UserController@updatePermission')
        ->name('user.permission');

    //user notification
    Route::get('/notification/unread', 'NotificationController@getUnReadNotification')
        ->name('user.notification_unread');
    Route::get('/notification/read', 'NotificationController@getReadNotification')
        ->name('user.notification_read');
    Route::get('/notification/all', 'NotificationController@getAllNotification')
        ->name('user.notification_all');

    //system user management
    Route::get('/administrator/user', 'AdministratorController@userIndex')
        ->name('administrator.user_index');
    Route::get('/administrator/user/create', 'AdministratorController@userCreate')
        ->name('administrator.user_create');
    Route::post('/administrator/user/store', 'AdministratorController@userStore')
        ->name('administrator.user_store');
    Route::get('/administrator/user/{id}/edit', 'AdministratorController@userEdit')
        ->name('administrator.user_edit');
    Route::post('/administrator/user/{id}/update', 'AdministratorController@userUpdate')
        ->name('administrator.user_update');
    Route::post('/administrator/user/{id}/delete', 'AdministratorController@userDestroy')
        ->name('administrator.user_destroy');
    Route::post('administrator/user/status/{id}', 'AdministratorController@userChangeStatus')
        ->name('administrator.user_status');

    Route::any('/administrator/user/reset-password', 'AdministratorController@userResetPassword')
        ->name('administrator.user_password_reset');
    Route::any('/administrator/generators/username', 'GeneratorController@generateUsername')
        ->name('administrator.generators.username');


    //Permissions
    Route::get('/permissions', 'UserController@permissionList')
        ->name('user.permission_index');
    Route::any('/permissions/create', 'UserController@permissionCreate')
        ->name('user.permission_create');


    //user role manage
    Route::get('/role', 'UserController@roles')
        ->name('user.role_index');
    Route::post('/role', 'UserController@roles')
        ->name('user.role_destroy');
    Route::get('/role/create', 'UserController@roleCreate')
        ->name('user.role_create');
    Route::post('/role/store', 'UserController@roleCreate')
        ->name('user.role_store');
    Route::any('/role/update/{id}', 'UserController@roleUpdate')
        ->name('user.role_update');


    // application settings routes
    Route::get('settings/institute', 'SettingsController@institute')
        ->name('settings.institute');
    Route::post('settings/institute', 'SettingsController@institute')
        ->name('settings.institute');

    // academic calendar
    Route::get('settings/academic-calendar', 'SettingsController@academicCalendarIndex')
        ->name('settings.academic_calendar.index');
    Route::post('settings/academic-calendar', 'SettingsController@academicCalendarIndex')
        ->name('settings.academic_calendar.destroy');
    Route::get('settings/academic-calendar/create', 'SettingsController@academicCalendarCru')
        ->name('settings.academic_calendar.create');
    Route::post('settings/academic-calendar/create', 'SettingsController@academicCalendarCru')
        ->name('settings.academic_calendar.store');
    Route::get('settings/academic-calendar/edit/{id}', 'SettingsController@academicCalendarCru')
        ->name('settings.academic_calendar.edit');
    Route::post('settings/academic-calendar/update/{id}', 'SettingsController@academicCalendarCru')
        ->name('settings.academic_calendar.update');

    //sms gateways
    Route::get('settings/sms-gateway', 'SettingsController@smsGatewayIndex')
        ->name('settings.sms_gateway.index');
    Route::post('settings/sms-gateway', 'SettingsController@smsGatewayIndex')
        ->name('settings.sms_gateway.destroy');
    Route::get('settings/sms-gateway/create', 'SettingsController@smsGatewayCru')
        ->name('settings.sms_gateway.create');
    Route::post('settings/sms-gateway/create', 'SettingsController@smsGatewayCru')
        ->name('settings.sms_gateway.store');
    Route::get('settings/sms-gateway/edit/{id}', 'SettingsController@smsGatewayCru')
        ->name('settings.sms_gateway.edit');
    Route::post('settings/sms-gateway/update/{id}', 'SettingsController@smsGatewayCru')
        ->name('settings.sms_gateway.update');

    //Voice gateways
    Route::get('settings/voice-gateway', 'SettingsController@voiceGatewayIndex')
        ->name('settings.voice_gateway.index');
    Route::post('settings/voice-gateway', 'SettingsController@voiceGatewayIndex')
        ->name('settings.voice_gateway.destroy');
    Route::get('settings/voice-gateway/create', 'SettingsController@voiceGatewayCru')
        ->name('settings.voice_gateway.create');
    Route::post('settings/voice-gateway/create', 'SettingsController@voiceGatewayCru')
        ->name('settings.voice_gateway.store');
    Route::get('settings/voice-gateway/edit/{id}', 'SettingsController@voiceGatewayCru')
        ->name('settings.voice_gateway.edit');
    Route::post('settings/voice-gateway/update/{id}', 'SettingsController@voiceGatewayCru')
        ->name('settings.voice_gateway.update');

    Route::get('media/external', 'MediaManagerController@externalPage')
        ->name('media.file.external');
    Route::post('media/delete/{id}', 'MediaManagerController@deleteFile')
        ->name('media.file.delete');
    Route::post('media/audio/upload', 'MediaManagerController@uploadAudio')
        ->name('media.audio.upload');
    Route::post('media/audio/delete', 'MediaManagerController@deleteAudio')
        ->name('media.audio.delete');
    Route::post('media/voice-gateway/check-send-message', 'MediaManagerController@checkSendMessage')
        ->name('media.voice_gateway.checkSendMessage');

    //report settings
    Route::get('settings/report', 'SettingsController@report')
        ->name('settings.report');
    Route::post('settings/report', 'SettingsController@report')
        ->name('settings.report');


    // administrator routes
    //academic year
    Route::get('administrator/academic_year', 'AdministratorController@academicYearIndex')
        ->name('administrator.academic_year');
    Route::post('administrator/academic_year', 'AdministratorController@academicYearIndex')
        ->name('administrator.academic_year_destroy');
    Route::get('administrator/academic_year/create', 'AdministratorController@academicYearCru')
        ->name('administrator.academic_year_create');
    Route::post('administrator/academic_year/create', 'AdministratorController@academicYearCru')
        ->name('administrator.academic_year_store');
    Route::get('administrator/academic_year/edit/{id}', 'AdministratorController@academicYearCru')
        ->name('administrator.academic_year_edit');
    Route::post('administrator/academic_year/update/{id}', 'AdministratorController@academicYearCru')
        ->name('administrator.academic_year_update');
    Route::post('administrator/academic_year/status/{id}', 'AdministratorController@academicYearChangeStatus')
        ->name('administrator.academic_year_status');

    // template
    //mail and sms
    Route::get('administrator/template/mailandsms', 'AdministratorController@templateMailAndSmsIndex')
        ->name('administrator.template.mailsms.index');
    Route::post('administrator/template/mailandsms', 'AdministratorController@templateMailAndSmsIndex')
        ->name('administrator.template.mailsms.destroy');
    Route::get('administrator/template/mailandsms/create', 'AdministratorController@templateMailAndSmsCru')
        ->name('administrator.template.mailsms.create');
    Route::post('administrator/template/mailandsms/create', 'AdministratorController@templateMailAndSmsCru')
        ->name('administrator.template.mailsms.store');
    Route::get('administrator/template/mailandsms/edit/{id}', 'AdministratorController@templateMailAndSmsCru')
        ->name('administrator.template.mailsms.edit');
    Route::post('administrator/template/mailandsms/update/{id}', 'AdministratorController@templateMailAndSmsCru')
        ->name('administrator.template.mailsms.update');
    // id card
    Route::get('administrator/template/idcard', 'AdministratorController@templateIdcardIndex')
        ->name('administrator.template.idcard.index');
    Route::post('administrator/template/idcard', 'AdministratorController@templateIdcardIndex')
        ->name('administrator.template.idcard.destroy');
    Route::get('administrator/template/idcard/create', 'AdministratorController@templateIdcardCru')
        ->name('administrator.template.idcard.create');
    Route::post('administrator/template/idcard/create', 'AdministratorController@templateIdcardCru')
        ->name('administrator.template.idcard.store');
    Route::get('administrator/template/idcard/edit/{id}', 'AdministratorController@templateIdcardCru')
        ->name('administrator.template.idcard.edit');
    Route::post('administrator/template/idcard/update/{id}', 'AdministratorController@templateIdcardCru')
		->name('administrator.template.idcard.update');
	Route::get('administrator/logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index')->name('logs.index');


    // academic routes
    // bus
    Route::group(['prefix' => 'academic'], function(){
        Route::resource('bus', 'BusController');
        Route::post('bus/status/{id}', 'BusController@busStatus')
            ->name('academic.bus_status');
    });
    // class
    Route::get('academic/class', 'AcademicController@classIndex')
        ->name('academic.class');
    Route::post('academic/class', 'AcademicController@classIndex')
        ->name('academic.class_destroy');
    Route::get('academic/class/create', 'AcademicController@classCru')
        ->name('academic.class_create');
    Route::post('academic/class/create', 'AcademicController@classCru')
        ->name('academic.class_store');
    Route::get('academic/class/edit/{id}', 'AcademicController@classCru')
        ->name('academic.class_edit');
    Route::post('academic/class/update/{id}', 'AcademicController@classCru')
        ->name('academic.class_update');
    Route::post('academic/class/status/{id}', 'AcademicController@classStatus')
        ->name('academic.class_status');

    // section
    Route::get('academic/section', 'AcademicController@sectionIndex')
        ->name('academic.section');
    Route::post('academic/section', 'AcademicController@sectionIndex')
        ->name('academic.section_destroy');
    Route::get('academic/section/create', 'AcademicController@sectionCru')
        ->name('academic.section_create');
    Route::post('academic/section/create', 'AcademicController@sectionCru')
        ->name('academic.section_store');
    Route::get('academic/section/edit/{id}', 'AcademicController@sectionCru')
        ->name('academic.section_edit');
    Route::post('academic/section/update/{id}', 'AcademicController@sectionCru')
        ->name('academic.section_update');
    Route::post('academic/section/status/{id}', 'AcademicController@sectionStatus')
        ->name('academic.section_status');

    // subject
    Route::get('academic/subject', 'AcademicController@subjectIndex')
        ->name('academic.subject');
    Route::post('academic/subject', 'AcademicController@subjectIndex')
        ->name('academic.subject_destroy');
    Route::get('academic/subject/create', 'AcademicController@subjectCru')
        ->name('academic.subject_create');
    Route::post('academic/subject/create', 'AcademicController@subjectCru')
        ->name('academic.subject_store');
    Route::get('academic/subject/edit/{id}', 'AcademicController@subjectCru')
        ->name('academic.subject_edit');
    Route::post('academic/subject/update/{id}', 'AcademicController@subjectCru')
        ->name('academic.subject_update');
    Route::post('academic/subject/status/{id}', 'AcademicController@subjectStatus')
        ->name('academic.subject_status');

    // Route::get('defau','DefaultExamRuleController@Create');
    // Route::get('defultexamlisting','DefaultExamRuleController@index');
    // Route::post('default/exam','DefaultExamRuleController@store')->name('default.exam.rule');
    // Route::post('exam/rule/temp/{id}', 'DefaultExamRuleController@destroy')
    //     ->name('templae.destroy');

    // teacher routes
    Route::resource('teacher', 'TeacherController');
    Route::post('teacher/status/{id}', 'TeacherController@changeStatus')
        ->name('teacher.status');

    // student routes
    Route::any('student/file-upload', 'StudentController@createFromFile')
        ->name('student.create_file'); // Newly Added
    Route::any('student/search', 'StudentController@searchStudent')
    ->name('student.search_student'); 
    
    Route::any('student/studyCertificate', 'StudentController@studyCertificate')->name('student.studyCertificate');
    Route::get('student/preStudents', 'StudentController@preStudents')->name('student.preStudents');
    Route::post('student/setInterview', 'StudentController@setInterview')->name('student.setInterview');

    // Student Promotion
    Route::get('student/promotion', 'StudentController@promotion')->name('student.promotion');
    Route::post('student/promoteStudents', 'StudentController@promoteStudents')->name('student.promoteStudents');
    // Student Promotion

    Route::get('student/create/{id?}', 'StudentController@create')->name('student.create');
    Route::resource('student', 'StudentController');
    Route::get('student-profile/edit', 'StudentController@profileEdit')->name('student.profile.edit');
    Route::post('student/profile/update', 'StudentController@profileUpdate')->name('student.profile.update');


    Route::post('student/status/{id}', 'StudentController@changeStatus')
        ->name('student.status');
    Route::get('student-list-by-filter', 'StudentController@studentListByFitler')
        ->name('student.list_by_fitler');

    // Gallary Routes
    Route::post('gallary/media', 'GallaryController@storeMedia')->name('gallary.storeMedia');
    Route::get('gallary/summary', 'GallaryController@summary')->name('gallary.summary');
    Route::get('gallary/view', 'GallaryController@view')->name('gallary.view');
    Route::get('/students/gallary', 'GallaryController@getGallary')
        ->name('students.get_gallary');
    Route::resource('gallary', 'GallaryController');

    // bus attendance routes
    Route::group(['prefix' => 'attendance'], function(){
        Route::resource('busrecord', 'BusAttendanceController');
        Route::get('bus/summary', 'BusAttendanceController@attendenceSummary')
            ->name('attendance.bus_summary');
        Route::get('bus/zones', 'BusAttendanceController@buszones')
            ->name('busrecord.zones');
        Route::post('bus/status/{id}', 'BusAttendanceController@changeStatus')
            ->name('busrecord.bus_status');
    });

    // student attendance routes

    Route::get('student-attendance/summary', 'StudentAttendanceController@attendenceSummary')->name('student_attendance.summary');

    Route::get('student-attendance', 'StudentAttendanceController@index')->name('student_attendance.index');
    Route::any('student-attendance/create', 'StudentAttendanceController@create')->name('student_attendance.create');
    Route::post('student-attendance/store', 'StudentAttendanceController@store')->name('student_attendance.store');
    Route::post('student-attendance/status/{id}', 'StudentAttendanceController@changeStatus')
        ->name('student_attendance.status');
    Route::any('student-attendance/file-upload', 'StudentAttendanceController@createFromFile')
        ->name('student_attendance.create_file');
    Route::get('student-attendance/file-queue-status', 'StudentAttendanceController@fileQueueStatus')
        ->name('student_attendance.file_queue_status');
    Route::post('student-attendance/session-attendance-card', 'StudentAttendanceController@getSessionAttendanceCard')
        ->name('student_attendance.session_attendance_card');
    Route::post('student-attendance/subject-attendance-card', 'StudentAttendanceController@getSubjectAttendanceCard')
        ->name('student_attendance.subject_attendance_card');
    
    
    
    //student view profile
    
    Route::get('attendance','StudentProfileView@attendance')->name('student.attendance');
    Route::get('view-marks','StudentProfileView@marksListing')->name('marks-view');
    Route::post('mark', 'StudentProfileView@getResultsDetails')->name('marks.view');
    Route::get('student_message','StudentProfileView@message')->name('student.message');
    Route::get('circulars','StudentProfileView@circular')->name('student.circulars');
    Route::get('announcement','StudentProfileView@announcement')->name('student.announcement');
    Route::get('exam_timetable', 'ExamTimeTableController@show')->name('exam');
    Route::get('class_timetable','TimeTableController@show')->name('class_timetable');
    Route::any('payment','StudentProfileView@feeCollectionList')->name('feescollection');
    Route::get('academic_calendar','StudentProfileView@academicCalendar')->name('acaledar');

    // Timetable
    Route::resource('timetables', 'TimeTableController');
    Route::get('/students/timetables', 'TimeTableController@student')->name('timetables.student');
    Route::get('/timetables/events/load', 'TimeTableController@loadEvents')->name('timetables.load');
    Route::get('/change-class/ajax/{id}', 'TimeTableController@getSectionSubject')
        ->name('timetables.change-class');

    //Exam Timetable
    Route::resource('exam-timetables', 'ExamTimeTableController');
    Route::get('/students/exam-timetables', 'ExamTimeTableController@student')->name('exam-timetables.student');
    Route::get('/exam-timetables/events/load', 'ExamTimeTableController@loadEvents')->name('exam-timetables.load');
    Route::get('/change-exam-class/ajax/{id}', 'ExamTimeTableController@getExamSubject')
        ->name('exam-timetables.change-class');

    // HRM
    //Employee
    Route::resource('hrm/employee', 'EmployeeController', ['as' => 'hrm']);
    Route::post('hrm/employee/status/{id}', 'EmployeeController@changeStatus')
        ->name('hrm.employee.status');
    // Leave
    Route::resource('hrm/leave', 'LeaveController', ['as' => 'hrm']);
    Route::resource('hrm/work_outside', 'WorkOutsideController', ['as' => 'hrm']);
    // policy
    Route::get('hrm/policy', 'EmployeeController@hrmPolicy')
        ->name('hrm.policy');
    Route::post('hrm/policy', 'EmployeeController@hrmPolicy')
        ->name('hrm.policy');

    // employee attendance routes
    Route::get('employee-attendance', 'EmployeeAttendanceController@index')->name('employee_attendance.index');
    Route::get('employee-attendance/create', 'EmployeeAttendanceController@create')->name('employee_attendance.create');
    Route::post('employee-attendance/create', 'EmployeeAttendanceController@store')->name('employee_attendance.store');
    Route::post('employee-attendance/status/{id}', 'EmployeeAttendanceController@changeStatus')
        ->name('employee_attendance.status');
    Route::any('employee-attendance/file-upload', 'EmployeeAttendanceController@createFromFile')
        ->name('employee_attendance.create_file');
    Route::get('employee-attendance/file-queue-status', 'EmployeeAttendanceController@fileQueueStatus')
        ->name('employee_attendance.file_queue_status');


    //exam
    Route::get('exam', 'ExamController@index')
        ->name('exam.index');
    Route::get('exam/create', 'ExamController@create')
        ->name('exam.create');
    Route::post('exam/store', 'ExamController@store')
        ->name('exam.store');
    Route::get('exam/edit/{id}', 'ExamController@edit')
        ->name('exam.edit');
    Route::post('exam/update/{id}', 'ExamController@update')
        ->name('exam.update');
    Route::post('exam/status/{id}', 'ExamController@changeStatus')
        ->name('exam.status');
    Route::post('exam/delete/{id}', 'ExamController@destroy')
        ->name('exam.destroy');
    //grade
    Route::get('exam/grade', 'ExamController@gradeIndex')
        ->name('exam.grade.index');
    Route::post('exam/grade', 'ExamController@gradeIndex')
        ->name('exam.grade.destroy');
    Route::get('exam/grade/create', 'ExamController@gradeCru')
        ->name('exam.grade.create');
    Route::post('exam/grade/create', 'ExamController@gradeCru')
        ->name('exam.grade.store');
    Route::get('exam/grade/edit/{id}', 'ExamController@gradeCru')
        ->name('exam.grade.edit');
    Route::post('exam/grade/update/{id}', 'ExamController@gradeCru')
        ->name('exam.grade.update');
    //exam rules
    Route::get('exam/rule', 'ExamController@ruleIndex')
        ->name('exam.rule.index');
    Route::post('exam/rule', 'ExamController@ruleIndex')
        ->name('exam.rule.destroy');
    Route::get('exam/rule/create', 'ExamController@ruleCreate')
        ->name('exam.rule.create');
    Route::post('exam/rule/create', 'ExamController@ruleCreate')
        ->name('exam.rule.store');
    Route::get('exam/rule/edit/{id}', 'ExamController@ruleEdit')
        ->name('exam.rule.edit');
    Route::post('exam/rule/update/{id}', 'ExamController@ruleEdit')
        ->name('exam.rule.update');
    Route::get('exam/rule/templates/{id}', 'ExamController@getRulesTemplate')
        ->name('exam.rule.templates');
    /**
     * for default exam rule
     */
    Route::resource('exam/rule/template','DefaultExamRuleController');
    Route::delete('exam/rule/template/rule/{id}','DefaultExamRuleController@deleteMdt')->name('default.template.destroy');
    Route::get('subjectlist','DefaultExamRuleController@subjectindex');
    // Admit Card
    Route::get('exam/admitCard', 'ExamController@admitCardIndex')->name('exam.admitCardIndex');
    Route::any('exam/getAdmitCard/{print}', 'ExamController@getAdmitCard')->name('exam.getAdmitCard');
    // Admit Card

    //Marks
    Route::any('marks', 'MarkController@index')
        ->name('marks.index');

    Route::get('marks/marks-listing/{class}/{section}', 'MarkController@marksListing')->name('marks.listing');
    Route::post('marks/marks-listing/', 'MarkController@getResultsDetails')->name('get-marks.list');
    
    Route::get('marks/marks-edit/{class}/{section}/{exam}/{subject}', 'MarkController@editExamDetails')->name('edit-marks.list');
    Route::put('marks/marks-update', 'MarkController@updateExamDetails')->name('update-marks.list');
    
    Route::any('marks/create/{class?}/{section?}', 'MarkController@create')
        ->name('marks.create');
    Route::post('marks/store', 'MarkController@store')
        ->name('marks.store');
    Route::get('marks/edit/{id}', 'MarkController@edit')
        ->name('marks.edit');
    Route::post('marks/update/{id}', 'MarkController@update')
        ->name('marks.update');
    //result
    Route::any('result', 'MarkController@resultIndex')
        ->name('result.index');
    Route::any('result/generate', 'MarkController@resultGenerate')
        ->name('result.create');
    Route::any('result/delete', 'MarkController@resultDelete')
        ->name('result.delete');

    // Reporting
    $get_attendance_type = AppHelper::getAppSettings('attendance_type');
    if($get_attendance_type == 'daily_attendance') {
        Route::any('report/student-monthly-attendance', 'ReportController@studentMonthlyAttendance')
            ->name('report.student_monthly_attendance');
    } else {
        Route::any('report/student-monthly-attendance', 'ReportController@studentMonthlyAttendanceSubSess')
        ->name('report.student_monthly_attendance');
    }
    Route::any('report/student-list', 'ReportController@studentList')
        ->name('report.student_list');
    Route::any('report/employee-monthly-attendance', 'ReportController@employeeMonthlyAttendance')
        ->name('report.employee_monthly_attendance');
    Route::any('report/employee-list', 'ReportController@employeeList')
        ->name('report.employee_list');

    //lav ranjan
    Route::any('report/student-daily-attendance', 'ReportController@studentDailyttendance')
        ->name('report.student_daily_attendance');

    Route::any('report/post_student-daily-attendance', 'ReportController@postStudentDailyttendance')
        ->name('report.post_student_daily_attendance');
    
    Route::post('report/attendance_log', 'ReportController@completeAttendanceLog')
        ->name('report.attendance_log');


    //Fees Related routes

    Route::any('/fees/list', 'FeesController@getList')->name('fees.index');
    Route::any('/fees/setup', 'FeesController@feeSetup')->name('fees.create');
    Route::any('/fee/edit/{id}', 'FeesController@feeUpdate')->name('fees.feeUpdate');
    Route::delete('/fee/delete/{id}', 'FeesController@feeDelete')->name('fees.delete');
    Route::get('/fee/type/total/{class}/{student}', 'FeesController@feeTypeTotal')->name('fees.totalsum');

    Route::any('/fee/collection', 'FeesController@feeCollection')->name('feescollection.create');
    Route::any('/fee/collection/upload', 'FeesController@createFromFile')->name('feescollection.fromfile');
    Route::any('/fees/view', 'FeesController@feeCollectionList')->name('feescollection.index');
    Route::delete('/fees/delete/{billNo}', 'FeesController@feeCollectionDelete')->name('feecollection.delete');

    Route::get('/fee/getListjson/{class}/{type}/{zone}', 'FeesController@feelistByClassAndType')->name('fees.feelistbyclass');
    Route::get('/fee/getFeeInfo/{id}', 'FeesController@getFeeInfo')->name('fees.getFeeInfo');
    Route::get('/fee/getDue/{class}/{stdId}/{feeItem}/{month}', 'FeesController@getDue')->name('fees.getDue');

    Route::any('/fees/collection/report', 'FeesController@feeCollectionReport')->name('feescollectonreport');
    Route::post('/fees/collection/export', 'FeesController@feeCollectionReportExport')->name('feescollectonexport');
    Route::any('/fees/collection/report/monthly', 'FeesController@feeCollectionReport')->name('monthlycollectonreport');
    Route::post('/fees/collection/export/monthly', 'FeesController@feeCollectionReportExport')->name('monthlycollectonexport');
    Route::any('/fees/collection/itemised/report', 'FeesController@feeCollectionItemisedReport')->name('feescollectonitemisedreport');
    Route::post('/fees/collection/itemised/export', 'FeesController@feeCollectionItemisedReportExport')->name('feescollectonitemisedexport');
    Route::any('/fees/collection/itemised/report/monthly', 'FeesController@feeCollectionItemisedReport')->name('monthlyitemisedreport');
    Route::post('/fees/collection/itemised/export/monthly', 'FeesController@feeCollectionItemisedReportExport')->name('monthlyitemisedexport');
    Route::any('/fees/report', 'FeesController@feeReportList')->name('feesreport.index');
    Route::get('/fees/report/std/{student_id}', 'FeesController@reportstd')->name('feesreport.summary');
    Route::get('/fees/report/{sDate}/{eDate}', 'FeesController@feePrintReport')->name('feesreport.print');

    Route::get('/fees/details/{billNo}/print', 'FeesController@printBillDetails')->name('feesreport.print');
    Route::get('/fees/details/{billNo}', 'FeesController@billDetails')->name('feesreport.details');

    // Homework
    Route::get('homework', 'HomeworkController@index')->name('homework.index');
    Route::get('homework/summary', 'HomeworkController@homeworkSummary')->name('homework.summary');
    Route::get('homework/create', 'HomeworkController@create')->name('homework.create');
    Route::post('homework/store', 'HomeworkController@store')->name('homework.store');
    Route::get('homework/show/{id}', 'HomeworkController@show')->name('homework.show');
    Route::get('homework/edit/{id}', 'HomeworkController@edit')->name('homework.edit');
    Route::post('homework/update/{id}', 'HomeworkController@update')->name('homework.update');
    Route::post('homework/delete/{id}', 'HomeworkController@destroy')->name('homework.destroy');
    Route::get('homework/submissionForm', 'HomeworkController@submissionForm')->name('homework.submissionForm');
    Route::post('homework/submitHomework/{id}', 'HomeworkController@submitHomework')->name('homework.submitHomework');
    Route::get('homework/homeworkSubmissions', 'HomeworkController@homeworkSubmissions')->name('homework.homeworkSubmissions');
    Route::post('homework/updateSubmissionStatus', 'HomeworkController@updateSubmissionStatus')->name('homework.updateSubmissionStatus');
    // Homework

    // Study Certificate Template
    Route::get('administrator/template/studyCertificate', 'AdministratorController@templateStudyCertificateIndex')
        ->name('administrator.template.studyCertificate.index');
    Route::post('administrator/template/studyCertificate', 'AdministratorController@templateStudyCertificateIndex')
        ->name('administrator.template.studyCertificate.destroy');
    Route::get('administrator/template/studyCertificate/create', 'AdministratorController@templateStudyCertificateCru')
        ->name('administrator.template.studyCertificate.create');
    Route::post('administrator/template/studyCertificate/create', 'AdministratorController@templateStudyCertificateCru')
        ->name('administrator.template.studyCertificate.store');
    Route::get('administrator/template/studyCertificate/edit/{id}', 'AdministratorController@templateStudyCertificateCru')
        ->name('administrator.template.studyCertificate.edit');
    Route::post('administrator/template/studyCertificate/update/{id}', 'AdministratorController@templateStudyCertificateCru')
        ->name('administrator.template.studyCertificate.update');
    // Study Certificate Template

    // Admit Card Template
    Route::get('administrator/template/admitCard', 'AdministratorController@templateAdmitCardIndex')
        ->name('administrator.template.admitCard.index');
    Route::post('administrator/template/admitCard', 'AdministratorController@templateAdmitCardIndex')
        ->name('administrator.template.admitCard.destroy');
    Route::get('administrator/template/admitCard/create', 'AdministratorController@templateAdmitCardCru')
        ->name('administrator.template.admitCard.create');
    Route::post('administrator/template/admitCard/create', 'AdministratorController@templateAdmitCardCru')
        ->name('administrator.template.admitCard.store');
    Route::get('administrator/template/admitCard/edit/{id}', 'AdministratorController@templateAdmitCardCru')
        ->name('administrator.template.admitCard.edit');
    Route::post('administrator/template/admitCard/update/{id}', 'AdministratorController@templateAdmitCardCru')
        ->name('administrator.template.admitCard.update');
    // Admit Card Template

    // ProgressController::routes();

    // Pre Admission Form Fields
    Route::resource('pre-admission', 'PreAdmissionFormController');
    Route::post('pre-admission/status/{id}/{type?}', 'PreAdmissionFormController@changeStatus')->name('pre-admission.changeStatus');
    Route::post('pre-admission/updatePeriod', 'PreAdmissionFormController@updatePeriod')->name('pre-admission.updatePeriod');
    // Pre Admission Form Fields

    // Chapter
    Route::get('chapter/summary', 'ChapterController@subjectSummary')->name('chapter.summary');
    Route::get('chapter', 'ChapterController@index')->name('chapter.index');
    Route::get('chapter/create', 'ChapterController@create')->name('chapter.create');
    Route::post('chapter/store', 'ChapterController@store')->name('chapter.store');
    Route::get('chapter/show/{id}', 'ChapterController@show')->name('chapter.show');
    Route::get('chapter/edit/{id}', 'ChapterController@edit')->name('chapter.edit');
    Route::post('chapter/update/{id}', 'ChapterController@update')->name('chapter.update');
    Route::post('chapter/delete/{id}', 'ChapterController@destroy')->name('chapter.destroy');
    // Chapter
    // Chapter's Topic
    Route::get('topic/{chapter_id}', 'ChapterTopicController@index')->name('topic.index');
    Route::get('topic/create/{chapter_id}', 'ChapterTopicController@create')->name('topic.create');
    Route::post('topic/store', 'ChapterTopicController@store')->name('topic.store');
    Route::get('topic/show/{id}', 'ChapterTopicController@show')->name('topic.show');
    Route::get('topic/edit/{id}', 'ChapterTopicController@edit')->name('topic.edit');
    Route::post('topic/update/{id}', 'ChapterTopicController@update')->name('topic.update');
    Route::post('topic/delete/{id}', 'ChapterTopicController@destroy')->name('topic.destroy');
    // Chapter

    // Google Accounts
    Route::get('google/accounts', 'GoogleClientController@index')->name('google.index');
    Route::post('google/accounts/default/{id}', 'GoogleClientController@setDefault')->name('google.default');
    Route::delete('google/accounts/{id}', 'GoogleClientController@destroy')->name('google.destroy');
    Route::get('google/accounts/add', 'GoogleClientController@redirectToGoogleProvider')->name('google.create');
    Route::get('google/accounts/callback', 'GoogleClientController@handleProviderGoogleCallback')->name('google.store');
}
);


//change website locale
Route::get(
    '/set-locale/{lang}', function ($lang) {
    //set user wanted locale to session
    Session::put('user_locale', $lang);
    return redirect()->back();
}
)->name('setLocale');

//web artisan routes
Route::get(
    '/student-attendance-file-queue-start/{code}', function ($code) {
    if($code == "hr799"){
        try {
            echo '<br>Started student attendance processing...<br>';
            Artisan::call('attendance:seedStudent');
            echo '<br>Student attendance processing completed.<br>You will be redirect in 5 seconds.<br>';
            sleep(5);

            return redirect()->route('student_attendance.create_file')->with("success", "Students attendance saved and send sms successfully.");

        } catch (Exception $e) {
            Response::make($e->getMessage(), 500);
        }
    }else{
        App::abort(404);
    }
}
)->name('student_attendance_seeder');

Route::get(
    '/employee-attendance-file-queue-start/{code}', function ($code) {
    if($code == "hr799"){
        try {
            echo '<br>Started employee attendance processing...<br>';
            Artisan::call('attendance:seedEmployee');
            echo '<br>Employee attendance processing completed.<br>You will be redirect in 5 seconds.<br>';
            sleep(5);

            return redirect()->route('employee_attendance.create_file')->with("success", "Employee attendance saved and notify successfully.");

        } catch (Exception $e) {
            Response::make($e->getMessage(), 500);
        }
    }else{
        App::abort(404);
    }
}
)->name('employee_attendance_seeder');


//dev routes
Route::get(
    '/make-link/{code}', function ($code) {
    if($code !== '007') {
        return 'Wrong code!';
    }

    //check if developer mode enabled?
    if(!env('DEVELOPER_MODE_ENABLED', false)) {
        return "Please enable developer mode in '.env' file.".PHP_EOL."set 'DEVELOPER_MODE_ENABLED=true'";
    }
    //remove first
    if(is_link(public_path('storage'))){
        unlink(public_path('storage'));
    }


    //create symbolic link for public image storage
    App::make('files')->link(storage_path('app/public'), public_path('storage'));
    return 'Done link';
}
);
Route::get(
    '/cache-clear', function () {
    // if($code !== '007') {
    //     return 'Wrong code!';
    // }

    // //check if developer mode enabled?
    // if(!env('DEVELOPER_MODE_ENABLED', false)) {
    //     return "Please enable developer mode in '.env' file.".PHP_EOL."set 'DEVELOPER_MODE_ENABLED=true'";
    // }
    if(auth()->user() && (auth()->user()->role == 'Admin' || (!empty(auth()->user()->role['role_id']) && auth()->user()->role['role_id'] == AppHelper::USER_ADMIN))) {
        $exitCode = Artisan::call('cache:clear');
        $exitCode = Artisan::call('config:clear');
        $exitCode = Artisan::call('view:clear');
        $exitCode = Artisan::call('route:clear');
        return 'cleared cache';
    } else {
        abort(404);
    }
}
);

// create tiggers
Route::get(
    '/create-triggers/{code}', function ($code) {
    if($code !== '007') {
        return 'Wrong code!';
    }

    //check if developer mode enabled?
    if(!env('DEVELOPER_MODE_ENABLED', false)) {
        return "Please enable developer mode in '.env' file.".PHP_EOL."set 'DEVELOPER_MODE_ENABLED=true'";
    }

    AppHelper::createTriggers();

    return 'Triggers created :)';
}
);
//test sms send
Route::get(
    '/test-sms/{code}', function ($code) {
    if($code !== '007') {
        return 'Wrong code!';
    }
    //check if developer mode enabled?
    if(!env('DEVELOPER_MODE_ENABLED', false)) {
        return "Please enable developer mode in '.env' file.".PHP_EOL."set 'DEVELOPER_MODE_ENABLED=true'";
    }

    $gateway = \App\AppMeta::where('id', AppHelper::getAppSettings('student_attendance_gateway'))->first();
    $gateway = json_decode($gateway->meta_value);
    $smsHelper = new \App\Http\Helpers\SmsHelper($gateway);
    $res = $smsHelper->sendSms('8801722813644','test sms vai');
    dd($res);
}
);

// Clear the cache
Route::get('/clear-cache', function() {
    Artisan::call('cache:clear');
    return "Cache is cleared";
});

Route::group(
    ['namespace' => 'Backend', 'middleware' => ['auth']], function () {
        Route::any('/media/demo', 'MediaManagerController@uploadDemo')->name('media.demo');
    });