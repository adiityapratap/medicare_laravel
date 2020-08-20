<?php

namespace App\Providers;

use Form as Facade;
use Auth;
use App\Http\Helpers\AppHelper;
use Illuminate\Support\ServiceProvider as Provider;

class Form extends Provider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function boot()
    {
        $is_allowed_fileupload = AppHelper::getAppSettings('allow_fileupload') ? TRUE : FALSE;
        // Form components
        Facade::component('fileUpload', 'backend.partial.components.form.fileupload', [
            'mimetypes' => '*', 'source' => 'local', 'files' => [], 'enableFileUpload' => $is_allowed_fileupload, 'enableFileUrl' => TRUE, 'defaultDisk' => 'local'
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
