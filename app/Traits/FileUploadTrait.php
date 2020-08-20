<?php

namespace App\Traits;

use Log;
use \Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use App\Http\Helpers\AppHelper;
use App\AppMeta;
use Spatie\MediaLibrary\Models\Media;
use Spatie\MediaLibrary\MediaStream;

trait FileUploadTrait
{
    public function processfiles(Request $request, $model, $folder="files") {
        try {
            $uid = auth()->user()->id;

            if ($request->mediasource == "local") {
                $drive = 'public';
                if ($request->disk == 's3') {
                    $drive = 's3';
                } elseif ($request->disk == 'gdrive') {
                    $drive = 'gdrive';
                }
                foreach ($request->input('document', []) as $file) {
                    $model->addMedia(storage_path('tmp/uploads/' . $uid . '/' . $file))
                        ->preservingOriginal()
                        ->withCustomProperties(['source' => 'local'])
                        ->toMediaCollection(config('app.name').'/media/' . $folder . '/', $drive);
                }
            } else if ($request->mediasource == "url") {
                $excludes = $request->input('excludeurl', []);
                foreach ($request->input('fileurl', []) as $url) {
                    if($url && !in_array($url, $excludes)){
                        $model->addMediaFromUrl($url)
                            ->withCustomProperties(['source' => 'url', 'path' => $url])
                            ->toMediaCollection(config('app.name').'/media/' . $folder . '/', 'http');
                    }
                }
            }

            $file = new Filesystem;
            $file->cleanDirectory('tmp/uploads/'.$uid);
            $file->cleanDirectory(storage_path('tmp/uploads/' . $uid));
            return $model;

        } catch (\Exception $e) {
            Log::error($e);
            throw new Exception($e);
        }
    }

    public function retrieveFiles($model, $folder="files")
    {
        $files = [];
        $mediaItems = $model->getMedia(config('app.name').'/media/' . $folder . '/');
        foreach ($mediaItems as $key => $mediaItem) {
            $url = $this->getURL($mediaItem);
            array_push($files, ['id' => $mediaItem->id, 'url' => $url]);
        }

        return $files;
    }

    public function getSource($model, $folder="files")
    {
        $source = 'url';
        $mediaItems = $model->getMedia(config('app.name').'/media/' . $folder . '/');
        // dd($mediaItems);
        if(!empty($mediaItems) && isset($mediaItems[0])) {
            $source = $mediaItems[0]->getCustomProperty('source');
        }

        return $source;
    }

    public function downloadFiles($model, $folder="files")
    {
        // Let's get some media.
        $downloads = $model->getMedia(config('app.name').'/media/' . $folder . '/');

        // Download the files associated with the media in a streamed way.
        // No prob if your files are very large.
        return MediaStream::create('files.zip')->addMedia($downloads);
    }

    private function getURL(Media $mediaItem)
    {
        $source = $mediaItem->getCustomProperty('source');
        $path = NULL;
        if($source == 'url') {
            $path = $mediaItem->getCustomProperty('path');
        } else {
            $path = $mediaItem->getFullUrl(); //url including domain
        }
        return $path;
    }
}