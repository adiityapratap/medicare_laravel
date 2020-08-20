<?php

use Illuminate\Database\Seeder;
use App\Permission;
use App\Role;

class PermissionTableNewDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		$commonPermissionList = [];
		$administratorPermissionList = [];
		$onlyAdminPermissions = [];
		$academicPermissionList = [];
		$websitePermissionList = [];
		$hrmPermissionList = [];
		$examPermissionList = [];
		$reportsPermissionList = [];


        //merge all permissions and insert into db
        $permissions = array_merge($commonPermissionList, $administratorPermissionList, $onlyAdminPermissions,
            $academicPermissionList, $websitePermissionList, $hrmPermissionList, $examPermissionList, $reportsPermissionList);

        echo PHP_EOL , 'seeding permissions...';

        Permission::insert($permissions);


		echo PHP_EOL , 'seeding role permissions...';
		
		// Add slug names except the commom group to specific array, as you wish to allow access to specific roles
		$teachersPermissions = [];
		$studentPermissions = [];
		$parentsPermissions = [];
		$accountantPermissions = [];
		$librarianPermissions = [];
		$receptionistPermissions = [];


		$adminPermissions = [];
		$princiPermissions = [];
		foreach($permissions as $p) {
			array_push($adminPermissions, $p['slug']);
			if($p['group'] != 'Website' && $p['group'] != 'Admin Only') {
				array_push($princiPermissions, $p['slug']);
			}
		}

		if(!empty($permissions)){
			//now add speicific role permissions
			//Admin
			$admin = Role::where('name', 'admin')->first();
			$permissions = Permission::whereIn('slug', $adminPermissions)->get();
			$admin->permissions()->saveMany($permissions);
			//Principal
			$principal = Role::where('name', 'principal')->first();
			$princiPermissions = Permission::whereIn('slug', $princiPermissions)->get();
			$principal->permissions()->saveMany($princiPermissions);
			//Teacher
			if(count($teachersPermissions)){
				$teacher = Role::where('name', 'teacher')->first();
				$teachersPermissions = Permission::whereIn('slug', $teachersPermissions)->get();
				$teacher->permissions()->saveMany($teachersPermissions);
			}
			//Student
			if(count($studentPermissions)){
				$student = Role::where('name', 'student')->first();
				$studentPermissions = Permission::whereIn('slug', $studentPermissions)->get();
				$student->permissions()->saveMany($studentPermissions);
			}
			//Parent
			if(count($parentsPermissions)){
				$parent = Role::where('name', 'parents')->first();
				$parentsPermissions = Permission::whereIn('slug', $parentsPermissions)->get();
				$parent->permissions()->saveMany($parentsPermissions);
			}
			//Accountant
			if(count($accountantPermissions)){
				$accountant = Role::where('name', 'accountant')->first();
				$accountantPermissions = Permission::whereIn('slug', $accountantPermissions)->get();
				$accountant->permissions()->saveMany($accountantPermissions);
			}
			//Librarian
			if(count($librarianPermissions)){
				$librarian = Role::where('name', 'librarian')->first();
				$librarianPermissions = Permission::whereIn('slug', $librarianPermissions)->get();
				$librarian->permissions()->saveMany($librarianPermissions);
			}
			//Receptionist
			if(count($receptionistPermissions)){
				$receptionist = Role::where('name', 'receptionist')->first();
				$receptionistPermissions = Permission::whereIn('slug', $receptionistPermissions)->get();
				$receptionist->permissions()->saveMany($receptionistPermissions);
			}
			
	
			//now add other roles common permissions
			$slugs = array_map(function ($permission){
				return $permission['slug'];
			}, $commonPermissionList);
	
			$permissions = Permission::whereIn('slug', $slugs)->get();
	
			$roles = Role::where('name', '!=', 'admin')->get();
			foreach ($roles as $role){
				echo PHP_EOL , 'seeding '.$role->name.' permissions...';
				$role->permissions()->saveMany($permissions);
			}
		}
    }
}
