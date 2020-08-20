<?php
namespace App\Http\Imports;

use Exception;
use App\User;
use App\Student;
use App\UserRole;
use App\AcademicYear;
use App\Http\Helpers\AppHelper;
use App\IClass;
use App\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

HeadingRowFormatter::default('none');

class StudentImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        $studentCount = 0;
        if($rows->count()){
            foreach ($rows as $key => $value) {
                $studentCount   =   $studentCount + 1;
                DB::beginTransaction();
                try {
                    $acYearId = '';
                    $acYears = [];
                    if(AppHelper::getInstituteCategory() != 'college') {
                        $settings = AppHelper::getAppSettings();
                        $acYearId = $settings['academic_year'];
                    }
                    //now create user
                    $username = AppHelper::generateUserName();
                    $user = User::create(
                        [
                            'name' => $value['name'],
                            'username' => $username,
                            'email' => $value['email'],
                            'password' => bcrypt($username),
                            'remember_token' => null,
                        ]
                    );
                    //now assign the user to role
                    UserRole::create(
                        [
                            'user_id' => $user->id,
                            'role_id' => AppHelper::USER_STUDENT
                        ]
                    );

                    $arr = [
                        'name' =>  $value['name'],
                        'dob' =>  $value['dob'] ? date('d/m/Y', strtotime($value['dob'])) : '',
                        'gender' =>  $value['gender'],
                        'religion' =>  $value['religion'],
                        'pob' => $value['pob'],
                        'caste' => $value['caste'],
                        'castecategory' => $value['castecategory'],
                        'nationalid' => $value['nationalid'],
                        'monther_tongue' => $value['monther_tongue'],
                        'need_transport' => strval($value['need_transport'])?strval($value['need_transport']):'0',
                        'transport_zone' => $value['transport_zone'],
                        'blood_group' =>  $value['blood_group'],
                        'nationality' =>  $value['nationality'],
                        'photo' =>  $value['photo'],
                        'email' =>  $value['email'],
                        'phone_no' =>  $value['phone_no'],
                        'extra_activity' =>  $value['extra_activity'],
                        'note' =>  $value['note'],
                        'father_name' =>  $value['father_name'],
                        'father_phone_no' =>  $value['father_phone_no'],
                        'mother_name' =>  $value['mother_name'],
                        'mother_phone_no' =>  $value['mother_phone_no'],
                        'guardian' =>  $value['guardian'],
                        'guardian_phone_no' =>  $value['guardian_phone_no'],
                        'present_address' =>  $value['present_address'] ? $value['present_address'] : 'NA',
                        'permanent_address' =>  $value['permanent_address'] ? $value['permanent_address'] : 'NA',
                        'status' =>  "1"
                    ];

                    $arr['user_id'] = $user->id;

                    // now save student
                    $student = Student::create($arr);

                    $classInfo = IClass::find($value['class_id']);

                    $academicYearInfo = AcademicYear::find($acYearId);
                    
                    $regiNo = $academicYearInfo->start_date->format('y') . (string)$classInfo->numeric_value;

                    $totalStudent = Registration::where('academic_year_id', $academicYearInfo->id)
                        ->where('class_id', $classInfo->id)->withTrashed()->count();
                    $regiNo .= str_pad(++$totalStudent,3,'0',STR_PAD_LEFT);


                    $registrationData = [
                        'regi_no' => $regiNo,
                        'student_id' => $student->id,
                        'class_id' => $value['class_id'],
                        'section_id' => $value['section_id'],
                        'academic_year_id' => $academicYearInfo->id,
                        'roll_no' => $value['roll_no'],
                        'shift' => $value['shift'],
                        'card_no' => $value['card_no'],
                        'board_regi_no' => '',
                        'fourth_subject' => $value['fourth_subject'] ??  0,
                        'alt_fourth_subject' => $value['alt_fourth_subject'] ??  0,
                        'house' => $value['house'] ??  ''
                    ];

                    Registration::create($registrationData);

                    // now commit the database
                    DB::commit();
                }

                catch(\Exception $e){
                    DB::rollback();
                    $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
                    throw new Exception($message);
                }   
            }
            $_SESSION['importmessage'] = 'File Uploaded Successfully! '. $studentCount. ' Student(s) added!';
        } else {
            throw new Exception("The CSV file was empty.");
        }
    }
}