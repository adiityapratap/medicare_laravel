<?php

namespace App\Http\Controllers\Backend;

use Log;
use View;
use App\Http\Helpers\AppHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\AppMeta;
use App\MessageNotification;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\VoiceCallHelper;
use App\Media;
use App\Gallery;
// File upload helper
use App\Traits\FileUploadTrait;

class MediaManagerController extends Controller
{
    use FileUploadTrait;

    public function externalPage(Request $request) 
    {
        $url = $request->query('url');
        return response()->stream(function () use($url) {
            echo file_get_contents($url);
        });
        // return View::make($url)->render();
    }

    public function deleteFile($id)
    {
        try {
            $mediaItem = Media::where('id', $id)->first();
            if($mediaItem) {
                $mediaItem->delete();
            }

            return [
                'success' => true,
                'message' => 'Media item deleted successfully'
            ];
        } catch(\Exception $e){
            Log::error($e);
            return [
                'success' => false,
                'message' => 'Failed to perform the action. Please try again!'
            ];
        }
    }

    public function uploadDemo(Request $request)
    {
        if(config('app.env') == 'development' && config('app.debug')) {
            $files = [];

            if($request->isMethod('post')) {
                $gallery = Gallery::create(['title'=> 'File upload demo ' . date('Y-m-d H:i:s'),'class_id' => 1]);
                $this->processfiles($request, $gallery, 'demo');

                // $gal = Gallery::where('id', 311)->first();
                dd($this->retrieveFiles($gallery, 'demo'));
            }

            return view('backend.medialibrary.demo', compact(
                'files'
            ));
        }
        abort(404);
    }

    public function uploadAudio(Request $request) {
        try {
            $return = ['status'=>false,'path'=>''];
            if($request->hasFile('file')) {
                $messageNotifications = MessageNotification::create(
                    [
                        'message_type' => 'Voice Call'
                    ]
                );
                $messageId = $messageNotifications->id;
                $mediaData = $messageNotifications->addMedia($request->file('file'))->toMediaCollection(config('app.name').'/voice/','s3');
                if(!empty($mediaData->id)){
                    $path = $mediaData->getUrl(); // env('AWS_URL', 'https://sprinkletest.s3.amazonaws.com').'/'.$mediaData->collection_name.$mediaData->id.'/'.$mediaData->file_name;
                    DB::table('message_notifications')->where('id', $messageId)->update([
                        'message' => $path
                    ]);
                    $return = ['status'=>true,'path'=>$path,'id'=>$messageId];
                    return $return;
                } else {
                    $messageNotifications = MessageNotification::where('id', $messageId)->first();
                    $messageNotifications->delete();
                }
            }
            return $return;
        } catch (\Exception $ex) {
            Log::error($ex);
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            $return = ['status'=>false,'message'=>$message];
            return $return;
        }
    }

    public function deleteAudio(Request $request){
        $id = (!empty($request->id)) ? $request->id : '';
        $messageNotifications = MessageNotification::where('id', $id)->first();
        $oldFile = $messageNotifications->getFirstMedia(config('app.name').'/voice/');
            if(!empty($oldFile)) {
                $oldFile->delete();
            }
        $messageNotifications->delete();
        $return = ['status'=>true];
        return $return;
    }

    public function checkSendMessage(Request $request) {
        try {
            $audio_path = (!empty($request->audio_path)) ? $request->audio_path : '';
            $phoneNumbers = (!empty($request->mobNum)) ? $request->mobNum : '';
            
            $cellNumber[] = AppHelper::validateCellNo($phoneNumbers);
            if(!empty($cellNumber)) {
                $gateway = AppMeta::where('id', AppHelper::getAppSettings('voice_call_center_gateway'))->first();
                $gateway = (!empty($gateway->meta_value)) ? json_decode($gateway->meta_value) : "";
                // send sms via helper
                $smsHelper = new VoiceCallHelper($gateway);
                $smsHelper->makeCall($cellNumber, $audio_path);
                $return = ['status'=>true,'message'=>'Message accepted, pending for delivery.'];
                return $return;
            } else {
                $return = ['status'=>false,'message'=>'Not a valid number'];
                return $return;
            }
        } catch (\Exception $ex) {
            Log::error($ex);
            $message = str_replace(array("\r", "\n","'","`"), ' ', $ex->getMessage());
            $return = ['status'=>false,'message'=>$message];
            return $return;
        }
    }

}
