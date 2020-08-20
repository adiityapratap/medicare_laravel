<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Token not needed for the below API's
Route::group(
    ['namespace' => 'API', 'middleware' => ['api']], function () {

    Route::post('login', 'ApiController@login');

}
);

// Token needed for the below API's
Route::group(
    ['namespace' => 'API', 'middleware' => ['api', 'jwt']], function () {

Route::group(['prefix' => 'academic'], function(){
    Route::group(['prefix' => 'class'], function(){
        //feedback
        Route::get('teachers/feeback/questions','FeedbackController@index');
        Route::post('teachers/feeback/response','FeedbackController@submitfeedback');
        Route::get('teachers','FeedbackController@listTeacher');
        //feedback

        Route::get('sections', 'Academic\Sections@sections');
    });

    Route::get('institute/{type}', 'Academic\Institute@users');
});
    Route::post('user/messages', 'ApiController@sendMessages');
    Route::get('getProfile', 'ApiController@getProfile');
    Route::get('getMessages', 'ApiController@getMessages');
    Route::post('updateMessages', 'ApiController@updateMessages');
    Route::post('deleteMessages', 'ApiController@deleteMessages');

    Route::post('attendance', 'AttendanceController@getAttendanceDetails');
    Route::get('getclassdata', 'AttendanceController@getclassdata');
    Route::get('students', 'AttendanceController@students');
    Route::post('attendancestudents', 'AttendanceController@attendancestudents');
    Route::put('attendance/students/update', 'AttendanceController@updateStudentsAttendance');
    Route::post('attendancestaff', 'AttendanceController@attendancestaff');
    Route::post('user/update/{id}', 'UserController@updateUser');
    Route::get('user/notifications/{id}', 'ApiController@notifications');
    Route::put('user/notifications/{notificationid}', 'ApiController@updatetimereadnotification');

    Route::get('all-class-details', 'AttendanceController@getAllClassDetails');

    Route::get('class/timetable/{id}', 'TimeTableController@index');
    Route::post('class/timetable', 'TimeTableController@store');

    Route::post('media/create', 'GallaryController@apistore');
    Route::get('media/gallery/{section_id}', 'GallaryController@apigetGallary');

    Route::get('exam/timetable/{id}', 'TimeTableController@getExamTimeTables');
    Route::post('exam/timetable', 'TimeTableController@storeExamTimeTables');
    
    // Homework
    Route::get('homeworks', 'HomeworkController@getHomework');
    Route::post('homeworks', 'HomeworkController@store');
    Route::post('homeworks/update/{id}', 'HomeworkController@update');
    Route::delete('homeworks/{id}', 'HomeworkController@destroy');
    Route::post('homeworks/submission/{submissionID}', 'HomeworkController@updateSubmissionStatus');
    Route::get('homeworks/student/{studentID}', 'HomeworkController@getStudentHomeworks');
    Route::post('homeworks/submission/', 'HomeworkController@submitHomework');
    // Homework


    // Get Exam Deails
    Route::get('exam', 'ExamsController@getExamList');
    Route::get('exam/result/marks', 'ExamsController@getmarks');
    Route::get('exam/rules/{id}', 'ExamsController@getExamRules');    
});

