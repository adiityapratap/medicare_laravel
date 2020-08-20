<?php

namespace App\Http\Controllers\API;

use \stdClass;
use \DateTime;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\IClass;
use App\Section;
use App\Http\Helpers\AppHelper;
use Carbon\Carbon;
use App\Registration;
use App\Media;
use App\Gallery;
use App\Video;
use JWTAuth;
use Illuminate\Support\Facades\Auth;
use File;

class GallaryController extends Controller
{
    public function apistore(Request $request)
    {   
        //dd($request);
        $response = [];
        $allclasses = $request->class; 
        if (!$allclasses && ($request->images || $request->videos)) {
            return response()->json(['success' => false, 'message' => 'Bad request'], 403);
        }
        if (count($allclasses) > 0) {
            for($c=0;$c<count($allclasses);$c++) {
                $gal = Gallery::create(['title'=>$request->title,'class_id' => $allclasses[$c]]);
                
                if($request->images){
                    $gal->addMultipleMediaFromRequest(['images'])
                        ->each(function ($fileAdder) {
                            $fileAdder->preservingOriginal()->toMediaCollection(config('app.name').'/gallary/','s3');
                        });
                    
                    $original = $gal->getFirstMedia(config('app.name').'/gallary/')->getUrl();
                    $thumb = $gal->getFirstMedia(config('app.name').'/gallary/')->getUrl("thumb");
                    array_push($response, array(
                        'uri' => $original,
                        'thumb' => $thumb,
                        'mimetype' => 'image'
                    ));        
                }
                
                $videocollection = $request->videos;
                if($videocollection){
                    for($m=0; $m < count($videocollection); $m++) {       
                        if ($videocollection[$m] != "") {
                            $videourl = $mediacollection[$m];
                            Video::create([
                                'videourl' => $videourl,
                                'gal_id' => $gal->id
                            ]);
                            if (strpos($videourl, 'youtu') !== false) {
                                preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $videourl, $match);
                                $video_id = $match[1];
                                $thumb = "http://img.youtube.com/vi/".$video_id."/hqdefault.jpg";
                            }
                            if (strpos($videourl, 'vimeo.com') !== false) {
                                $video_id = explode("https://vimeo.com/", $videourl);
                                $video_id = $video_id[1];
                                $hash = unserialize(file_get_contents("http://vimeo.com/api/v2/video/$video_id.php"));
                                $thumb = $hash[0]['thumbnail_medium'];
                            }
    
                            array_push($response, array(
                                'uri' => $videourl,
                                'thumb' => $thumb,
                                'mimetype' => 'video'
                            ));
                        }       
                    } 
                }
                $message = "A new gallary, named '".$request->title."' created.";
                $acYear = AppHelper::getAcademicYear();
                $users = Registration::where('academic_year_id', $acYear)
                ->where('section_id', $allclasses[$c])
                ->where('status', AppHelper::ACTIVE)
                ->select('student_id')
                ->get();
                AppHelper::notifyUsers($users, $message);
            }
        }
        return response()->json(['success' => true, 'message' => $request->title." gallery is live now!", 'data' => $response]);
    }

    public function apigetGallary($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        if ($id) {
            $sectionid = $id;
        } else {
            $sectionid = $user->student->register->section_id;
        }
        //$classid = Auth::user()->student->register->class_id;

        $gallary = Gallery::where('class_id', $sectionid)->get();
        $gallaryitems = [];
        if ($gallary->count() > 0) {
            for ($gal=0;$gal<$gallary->count();$gal++) {
                $gallaryitems[$gal] = new \stdClass;
                $gally = $gallary[$gal];
                $gallaryitems[$gal]->title = $gally->title;
                //echo $gally->title."<br>";
                $mediaItems = $gally->getMedia(config('app.name').'/gallary/');
                $gallaryitems[$gal]->uri = [];
                $gallaryitems[$gal]->thumb = [];
                if ($mediaItems->count() > 0) {
                    for($mi=0;$mi<$mediaItems->count();$mi++) {
                        $gallaryitems[$gal]->uri[$mi] = $mediaItems[$mi]->getUrl();
                        $gallaryitems[$gal]->thumb[$mi] = $mediaItems[$mi]->getUrl('thumb');
                        $gallaryitems[$gal]->mimetype = 'image';
                    }
                } else {
                    $videos = Video::where('gal_id',$gallary[$gal]->id)->get();
                    $videocount = $videos->count();
                    if ($videocount > 0) {
                        
                        for($v=0;$v<$videocount;$v++) {
                            $gallaryitems[$gal]->media[$v] = $videos[$v]->videourl;
                            if (strpos($videos[$v]->videourl, 'youtu') !== false) {
                                preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $videos[$v]->videourl, $match);
                                $video_id = $match[1];
                                $gallaryitems[$gal]->mediapreview[$v] = "http://img.youtube.com/vi/".$video_id."/hqdefault.jpg";
                            }
                            if (strpos($videos[$v]->videourl, 'vimeo.com') !== false) {
                                $video_id = explode("https://vimeo.com/", $videos[$v]->videourl);
                                $video_id = $video_id[1];
                                $hash = unserialize(file_get_contents("http://vimeo.com/api/v2/video/$video_id.php"));
                                $gallaryitems[$gal]->thumb[$v] = $hash[0]['thumbnail_medium'];
                            }
                            
                        }
                    }
                }
            }
        }

        //dd($gallary);

        return response()->json($gallaryitems);
    }
}