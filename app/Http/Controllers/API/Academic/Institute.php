<?php

namespace App\Http\Controllers\API\Academic;

use \Exception;
use Log;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\AppHelper;
use App\Http\Resources\Academic\Institute\InfoCollection;
use App\Traits\InstituteUsersTrait;

class Institute extends Controller
{
    use InstituteUsersTrait;

    public $successStatus = 200;
    /**
     * Handle an authentication attempt.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return Response
     */
    public function users(Request $request, $type)
    {
        try{
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }
            if($user->role->role_id == AppHelper::USER_STUDENT || $user->role->role_id == AppHelper::USER_PARENTS) {
                return response()->json(['success' => false, 'message' => 'Looks like you are not supposed to do this action!'], 401);
            }

            $info = collect([$this->getUsers($type)]);

            return new InfoCollection($info);
        } catch (Exception $e) {
            Log::error($e);
            return response()->json([
                'success' => false, 
                'message' => 'Oops!, I lost you for a moment. Can you please try again?'
            ], 500);
        }
    }
}
