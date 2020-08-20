<?php

namespace App\Http\ViewComposers;
use App\Http\Helpers\AppHelper;
use Illuminate\Contracts\View\View;

class ReportMasterComposer
{
    public function compose(View $view)
    {

        // get app settings
        $instituteSettings = AppHelper::getAppSettings('institute_settings');
        $instituteName = '';
        $instituteAddress = '';
        if($instituteSettings) {
            $instituteName = $instituteSettings['name'] ??  '';
            $instituteAddress = $instituteSettings['address'] ??  '';
            $logo = $instituteSettings['logo'] ??  '';
        }

        $view->with(base64_decode('bWFpbnRhaW5lcg=='), base64_decode('U3ByaW5rbGV3YXkgVGVjaG5vbG9naWVz'));
        $view->with(base64_decode('bWFpbnRhaW5lcl91cmw='), base64_decode('aHR0cHM6Ly93d3cuc3ByaW5rbGV3YXkuY29tLw=='));
        $view->with('instituteName', $instituteName);
        $view->with('instituteAddress', $instituteAddress);
        $view->with('logo', $logo);
        $view->with('showLogo', AppHelper::getAppSettings('report_show_logo'));
        $view->with('background_color', AppHelper::getAppSettings('report_background_color'));
        $view->with('text_color', AppHelper::getAppSettings('report_text_color'));

    }
}