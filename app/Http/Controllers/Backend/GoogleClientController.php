<?php

namespace App\Http\Controllers\Backend;

use Cache;
use Log;
use Auth;
use App\GoogleAccounts;
use App\User;
use App\AppMeta;
use App\Http\Helpers\AppHelper;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Controller;

class GoogleClientController extends Controller
{
    public function index()
    {
        $default_account = AppHelper::getAppSettings('default_google_account');
        $ga = GoogleAccounts::get();
        // dd($default_account);
        return view('backend.administrator.user.google.list', compact('ga', 'default_account'));
    }
    public function redirectToGoogleProvider()
    {
        $parameters = ['access_type' => 'offline'];
        return Socialite::driver('google')->scopes(["https://www.googleapis.com/auth/drive"])->with($parameters)->redirect();
    }

    public function handleProviderGoogleCallback()
    {
        $updatable = [];
        $user = Auth::user();
        $auth_user = Socialite::driver('google')->user();
        if(!$user->email) {
            $updatable['email'] = $auth_user->email;
        }
        $updatable['refresh_token'] = $auth_user->token;
        $user = GoogleAccounts::updateOrCreate(['email' => $auth_user->email], ['refresh_token' => $auth_user->token]);
        return redirect()->route('google.index')->with('success', 'New account added'); // Redirect to a secure page
    }

    public function setDefault(Request $request, $id)
    {
        try {
            $status = $request->get('status');
            $status  = $status ? $id : $status;

            AppMeta::updateOrCreate(
                ['meta_key' => 'default_google_account'],
                ['meta_value' => $status]
            );

            Cache::forget('app_settings');

            return [
                'success' => true,
                'message' => $status ? 'Account set as default.' : 'Account removed from default'
            ];
        } catch(\Exception $e){
            Log::error($e);
            return [
                'success' => false,
                'message' => 'Failed to perform the update. Please try again!'
            ];
        }
    }

    public function destroy($id)
    {
        $default_account = AppHelper::getAppSettings('default_google_account');
        if($default_account == $id) {
            return redirect()->route('google.index')->with('error', 'You cannot delete default account!');
        }
		$ga = GoogleAccounts::find($id);
		$ga->delete();
		return redirect()->route('google.index')->with('success', 'Google account deleted succesfully!');
    }
}
