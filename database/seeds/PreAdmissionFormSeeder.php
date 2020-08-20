<?php

use Illuminate\Database\Seeder;

class PreAdmissionFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	DB::table('pre_admission_forms')->truncate();
        DB::table('pre_admission_forms')->insert([
        	[
	            'field_title' => 'Name',
	            'field_name' => 'name',
	            'initial_fields' => '1',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Date of birth',
	            'field_name' => 'dob',
	            'initial_fields' => '1',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Phone/Mobile No.',
	            'field_name' => 'phone_no',
	            'initial_fields' => '1',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Class',
	            'field_name' => 'class_id',
	            'initial_fields' => '0',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Place of birth',
	            'field_name' => 'pob',
	            'initial_fields' => '0',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Gender',
	            'field_name' => 'gender',
	            'initial_fields' => '0',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Religion',
	            'field_name' => 'religion',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Caste',
	            'field_name' => 'caste',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Caste Category',
	            'field_name' => 'castecategory',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Blood Group',
	            'field_name' => 'blood_group',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Nationality',
	            'field_name' => 'nationality',
	            'initial_fields' => '0',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'National ID',
	            'field_name' => 'nationalid',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Mother Tongue',
	            'field_name' => 'monther_tongue',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Need Transportation',
	            'field_name' => 'need_transport',
	            'initial_fields' => '0',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Transportation Zone',
	            'field_name' => 'transport_zone',
	            'initial_fields' => '0',
	            'mandatory' => '1',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Photo',
	            'field_name' => 'photo',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Email',
	            'field_name' => 'email',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Extra Curricular Activity',
	            'field_name' => 'extra_activity',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Note',
	            'field_name' => 'note',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Father Name',
	            'field_name' => 'father_name',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Father Phone/Mobile No.',
	            'field_name' => 'father_phone_no',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Mother Name',
	            'field_name' => 'mother_name',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Mother Phone/Mobile No.',
	            'field_name' => 'mother_phone_no',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Local Guardian',
	            'field_name' => 'guardian',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Guardian Phone/Mobile No.',
	            'field_name' => 'guardian_phone_no',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Present Address',
	            'field_name' => 'present_address',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ],
	        [
	            'field_title' => 'Permanent Address',
	            'field_name' => 'permanent_address',
	            'initial_fields' => '0',
	            'mandatory' => '0',
	            'status' => '1',
	        ]
	    ]);
    }
}
