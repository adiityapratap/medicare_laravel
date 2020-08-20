<?php

use Illuminate\Database\Seeder;
use App\User;
use App\UserRole;
use App\Http\Helpers\AppHelper;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        echo PHP_EOL , 'seeding users...';

        $user= User::create(
            [
                'name' => 'Mr. admin',
                'username' => 'admin',
                'email' => 'info@sprinkleway.com',
                'password' => bcrypt('demo123'),
                'remember_token' => null,
            ]
        );

       UserRole::create(
           [
               'user_id' => $user->id,
               'role_id' => AppHelper::USER_ADMIN
           ]
       );

       $user= User::create(
           [
               'name' => 'Mr. Vikas',
               'username' => 'vikas',
               'email' => 'vikas@sprinkleway.com',
               'password' => bcrypt('demo123'),
               'remember_token' => null,
           ]
       );

      UserRole::create(
          [
              'user_id' => $user->id,
              'role_id' => AppHelper::USER_ADMIN
          ]
      );
      
      $user= User::create(
          [
              'name' => 'Mr. Jagadish',
              'username' => 'jagadish',
              'email' => 'jagadish@sprinkleway.com',
              'password' => bcrypt('demo123'),
              'remember_token' => null,
          ]
      );

     UserRole::create(
         [
             'user_id' => $user->id,
             'role_id' => AppHelper::USER_ADMIN
         ]
     );

    }
}
