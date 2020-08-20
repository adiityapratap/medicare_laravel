<?php

namespace App\Http\Controllers\Backend;

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
use Illuminate\Support\Facades\Auth;


class GallaryController extends Controller
{

    public function index(Request $request) {
        
        if ($request->has('secid')) {
            $secid = $request->secid;
        }
        $classes = IClass::where('status', AppHelper::ACTIVE)->orderBy('order', 'asc')
            ->pluck('name', 'id');
        foreach($classes as $class_id => $class) {
            $sections = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->orderBy('name','asc')
                ->pluck('name', 'id');
            foreach($sections as $section_id => $section) {
                if(!isset($classlist[$class_id])) {
                    $classlist[$class_id] = new stdClass();
                }
                $classlist[$class_id]->{$section_id} = new stdClass();
                $classlist[$class_id]->{$section_id}->class = $class;
                $classlist[$class_id]->{$section_id}->section = $section;
                $classlist[$class_id]->{$section_id}->htmlclass = filter_var($class, FILTER_SANITIZE_NUMBER_INT);
            }
        }
        $selected = "";
        if ($request->has('secid')) {
            return view('backend.gallary.index', compact('classes','classlist','secid','selected'));
        } else {
            return view('backend.gallary.index', compact('classes','classlist','selected'));
        }
        
    }
    

    public function view(Request $request) {
        $sectionid = $request->secid;
        $gallary = Gallery::where('class_id', $sectionid)->get();
        // echo "<pre>";
        // print_r($gallary);
        // echo "</pre>";
        $gallaryitems = [];
        if ($gallary->count() > 0) {
            for ($gal=0;$gal<$gallary->count();$gal++) {
                $gallaryitems[$gal] = new \stdClass;
                $gally = $gallary[$gal];
                $gallaryitems[$gal]->title = $gally->title;
                //echo $gally->title."<br>";
                $mediaItems = $gally->getMedia(config('app.name').'/gallary/');
                $gallaryitems[$gal]->media = [];
                $gallaryitems[$gal]->mediapreview = [];
                if ($mediaItems->count() > 0) {
                    $gallaryitems[$gal]->mediatype = "image";
                    for($mi=0;$mi<$mediaItems->count();$mi++) {
                        // $gallaryitems[$gal]->media[$mi] = new \stdClass;
                        // $gallaryitems[$gal]->media[$mi]->media = $mediaItems[$mi]->getUrl();
                        // $gallaryitems[$gal]->media[$mi]->preview = $mediaItems[$mi]->getUrl('thumb');
                        $gallaryitems[$gal]->media[$mi] = $mediaItems[$mi]->getUrl();
                        $gallaryitems[$gal]->mediapreview[$mi] = $mediaItems[$mi]->getUrl('thumb');
                        //echo $mediaItems[$mi]->getUrl();
                        //echo "<br>";
                        //348x235
                    }
                } else {
                    $gallaryitems[$gal]->mediatype = "video";
                    $videos = Video::where('gal_id',$gallary[$gal]->id)->get();
                    $videocount = $videos->count();
                    $gallaryitems[$gal]->media = [];
                    $gallaryitems[$gal]->mediapreview = [];
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
                                $gallaryitems[$gal]->mediapreview[$v] = $hash[0]['thumbnail_medium'];
                            }                            
                        }
                    }
                }
            }
        }
        // echo "<pre>";
        // print_r($gallaryitems);
        // echo "</pre>";
        // exit();
        return view('backend.gallary.show', compact('gallaryitems'));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function summary() {

        $academic_years = [];
        $today = new DateTime('now');
        $attendance_date = date_format($today, 'd/m/Y');
        $acYear = AppHelper::getAcademicYear();

        // Build class list
        $classlist = [];
        $classes = IClass::where('status', AppHelper::ACTIVE)
            ->orderBy('order','asc')
            ->pluck('name', 'id');
        foreach($classes as $class_id => $class) {
            $sections = Section::where('status', AppHelper::ACTIVE)
                ->where('class_id', $class_id)
                ->orderBy('name','asc')
                ->pluck('name', 'id');
            foreach($sections as $section_id => $section) {
                $gallary = Gallery::where('class_id', $section_id)->get();                

                if(!isset($classlist[$class_id])) {
                    $classlist[$class_id] = new stdClass();
                }
                $classlist[$class_id]->{$section_id} = new stdClass();
                $classlist[$class_id]->{$section_id}->class = $class;
                $classlist[$class_id]->{$section_id}->section = $section;
                $classlist[$class_id]->{$section_id}->galcount = $gallary->count();
                $classlist[$class_id]->{$section_id}->htmlclass = filter_var($class, FILTER_SANITIZE_NUMBER_INT);
            }
        }

        return view('backend.gallary.summary', compact(
            'classlist'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $uid = auth()->user()->id;
        // echo "<pre>";
        // print_r($request->all());
        // echo "</pre>";
        // exit();
        //$files = Storage::disk('s3')->files(config('app.name').'/gallary');
        //$allFiles = $request->input('document', []);

        $allclasses = $request->class_id;
        $filestobedeleted = [];       
        
        if (count($allclasses) > 0) {
            for($c=0;$c<count($allclasses);$c++) {
                $gal = Gallery::create(['title'=>$request->galname,'class_id' => $allclasses[$c]]);
                if ($request->mediatype == "image") {
                    foreach ($request->input('document', []) as $file) {                   
                        $gal->addMedia(storage_path('tmp/uploads/' . $uid . '/' . $file))->preservingOriginal()->toMediaCollection(config('app.name').'/gallary/','s3');
                        array_push($filestobedeleted,storage_path('tmp/uploads/' . $file));
                        //echo $gal->getFirstMedia(config('app.name').'/gallary/')->getUrl();
                        //echo "<br>";
                    }
                    //$mediaItems = $gal->getMedia(config('app.name').'/gallary/');
                    //echo "<pre>";
                    //print_r($mediaItems);
                    //echo "</pre>";
                } else if ($request->mediatype == "video") {
                    $videos = $request->videourl;
                    if (count($videos) > 0) {
                        for($v=0;$v<count($videos);$v++) {
                            Video::create([
                                'videourl' => $videos[$v],
                                'gal_id' => $gal->id
                            ]);
                        }
                    }
                    //$gal->addMediaFromUrl($request->videourl)->toMediaLibrary(config('app.name').'/gallary/','s3');
                }
                $message = "A new gallary, named '".$request->galname."' created.";
                $acYear = AppHelper::getAcademicYear();
                $users = Registration::where('academic_year_id', $acYear)
                ->where('section_id', $allclasses[$c])
                ->where('status', AppHelper::ACTIVE)
                ->select('student_id')
                ->get();
                AppHelper::notifyUsers($users, $message);
            }
        }
        if (count($filestobedeleted) > 0) {
            for ($fe=0;$fe<count($filestobedeleted);$fe++) {
                if (file_exists($filestobedeleted[$fe])) {
                    unlink($filestobedeleted[$fe]);
                }
            }
        }
        return redirect()->route('gallary.index')->with("success","Gallary Created successfully!");
    }   

    public function storeMedia(Request $request)
    {  
        if ($request->hasFile('file')) {
            $uid = auth()->user()->id;
            
            $this->validate($request, [
                'file.*' => 'required'
            ]);
            
            // $file = $request->file('file');                   
            // $name = time() . $file->getClientOriginalName();
            // $filePath = config('app.name').'/gallary/' . $name;
            // $path = Storage::disk('s3')->put($filePath, file_get_contents($file));
            
            // return response()->json([
            //     'name'          => $name,
            //     'original_name' => $file->getClientOriginalName(),
            // ]);

            $path = storage_path('tmp/uploads/'.$uid);
            if (!Storage::exists('tmp/uploads/'.$uid)) {
                Storage::makeDirectory('tmp/uploads/'.$uid, 0775, true, true);
            }
            $file = $request->file('file');
            $name = uniqid() . '_' . trim($file->getClientOriginalName());
            $file->move($path, $name);
            return response()->json([
                'name'          => $name,
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
    }

    public function getGallary()
    {
        $classid = Auth::user()->student->register->class_id;
        $sectionid = Auth::user()->student->register->section_id;

        $gallary = Gallery::where('class_id', $sectionid)->get();
        $gallaryitems = [];
        if ($gallary->count() > 0) {
            for ($gal=0;$gal<$gallary->count();$gal++) {
                $gallaryitems[$gal] = new \stdClass;
                $gally = $gallary[$gal];
                $gallaryitems[$gal]->title = $gally->title;
                //echo $gally->title."<br>";
                $mediaItems = $gally->getMedia(config('app.name').'/gallary/');
                $gallaryitems[$gal]->media = [];
                $gallaryitems[$gal]->mediapreview = [];
                if ($mediaItems->count() > 0) {
                    $gallaryitems[$gal]->mediatype = "image";
                    for($mi=0;$mi<$mediaItems->count();$mi++) {
                        $gallaryitems[$gal]->media[$mi] = $mediaItems[$mi]->getUrl();
                        $gallaryitems[$gal]->mediapreview[$mi] = $mediaItems[$mi]->getUrl('thumb');
                        //echo $mediaItems[$mi]->getUrl();
                        //echo "<br>";
                        //348x235
                    }
                } else {
                    $gallaryitems[$gal]->mediatype = "video";
                    $videos = Video::where('gal_id',$gallary[$gal]->id)->get();
                    $videocount = $videos->count();
                    $gallaryitems[$gal]->media = [];
                    $gallaryitems[$gal]->mediapreview = [];
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
                                $gallaryitems[$gal]->mediapreview[$v] = $hash[0]['thumbnail_medium'];
                            }
                            
                        }
                    }
                }
            }
        }

        return view('backend.gallary.gallary', compact(
            'gallaryitems'
        ));
    }

    public function apistore(Request $request)
    {   
        $response = [];
        $allclasses = $request->class; 
        $filestobedeleted = [];
        if (count($allclasses) > 0) {
            for($c=0;$c<count($allclasses);$c++) {
                $gal = Gallery::create(['title'=>$request->title,'class_id' => $allclasses[$c]]);
                $path = storage_path('tmp/uploads');
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }
                foreach ($request->media as $media) { 
                    if ($media->image != "") {
                        $base64_image = $media->image;
                        $image_parts = explode(";base64,", $_POST['image']);
                        $image_type_aux = explode("image/", $image_parts[0]);
                        $image_type = $image_type_aux[1];
                        $file = base64_decode($image_parts[1]);
                        $imageName = uniqid() . '_' .str_random(10).'.'.'png';
                        $file->move($path, $imageName);
                        $gal->addMedia(storage_path('tmp/uploads/' . $imageName))->preservingOriginal()->toMediaCollection(config('app.name').'/gallary/','s3');
                        array_push($filestobedeleted,storage_path('tmp/uploads/' . $file));
                        $original = $gal->getFirstMedia(config('app.name').'/gallary/')->getUrl();
                        $thumb = $gal->getFirstMedia(config('app.name').'/gallary/')->getUrl("thumb");
                        array_push($response, array(
                            'original' => $original,
                            'thumb' => $thumb
                        ));
                    }
                }                
            }
        }
        if (count($filestobedeleted) > 0) {
            for ($fe=0;$fe<count($filestobedeleted);$fe++) {
                if (file_exists($filestobedeleted[$fe])) {
                    unlink($filestobedeleted[$fe]);
                }
            }
        }
        return response()->json($response);
    }

    public function apigetGallary()
    {
        $classid = Auth::user()->student->register->class_id;
        $sectionid = $request->section_id;

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
                        $gallaryitems[$gal]->media[$mi] = $mediaItems[$mi]->getUrl();
                        $gallaryitems[$gal]->mediapreview[$mi] = $mediaItems[$mi]->getUrl('thumb');
                    }
                } else {
                    $videos = Video::where('gal_id',$gallary[$gal]->id)->get();
                    $videocount = $videos->count();
                    $gallaryitems[$gal]->media = [];
                    $gallaryitems[$gal]->mediapreview = [];
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
                                $gallaryitems[$gal]->mediapreview[$v] = $hash[0]['thumbnail_medium'];
                            }
                            
                        }
                    }
                }
            }
        }

        return response()->json($gallaryitems);
    }
}