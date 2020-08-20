<?php

namespace App\Http\ViewComposers;
use App\Http\Helpers\AppHelper;
use Illuminate\Contracts\View\View;

class BackendMasterComposer
{
    public function compose(View $view)
    {

        // get app settings
        $appSettings = AppHelper::getAppSettings();

        $view->with('frontend_website', 1);
        $view->with('show_language', 1);
        if (!isset($appSettings['frontend_website']) or $appSettings['frontend_website'] == '0') {
            $view->with('frontend_website', 0);
        }
        if (!isset($appSettings['disable_language']) or $appSettings['disable_language'] == '1') {
            $view->with('show_language', 0);
        }

        $view->with('locale', 'en');
        if (isset($appSettings['language']) && $appSettings['language'] != '') {
            $view->with('locale', $appSettings['language']);
        }


        $view->with(base64_decode('bWFpbnRhaW5lcg=='), base64_decode('U3ByaW5rbGV3YXkgVGVjaG5vbG9naWVz'));
        $view->with(base64_decode('bWFpbnRhaW5lcl91cmw='), base64_decode('aHR0cHM6Ly93d3cuc3ByaW5rbGV3YXkuY29tLw=='));
        $view->with('appSettings', $appSettings);
        $view->with('languages', AppHelper::LANGUEAGES);
        $view->with(base64_decode('aWRj'), base64_decode('ZTQwNjM0YjMyOGM0ZWQ0M2U0NWZkNGVjOTU1M2QzN2MxZTcwNTQxNw=='));
        $view->with('institute_category', AppHelper::getInstituteCategory());
    }
}