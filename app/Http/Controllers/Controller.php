<?php

namespace App\Http\Controllers;

use App\AppMeta;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @param $date
     * @return bool
     */
    public function checkWeekends($date)
    {
        $metas = AppMeta::pluck('meta_value','meta_key');
        $weekends = isset($metas['weekends']) ? json_decode($metas['weekends'], true) : [];
        $weekendsDay = [];
        foreach ($weekends as $weekend) {
            if ($weekend == 0) {
                $weekendsDay[] = 'Sunday';
            }
            if ($weekend == 1) {
                $weekendsDay[] = 'Monday';
            }
            if ($weekend == 2) {
                $weekendsDay[] = 'Tuesday';
            }
            if ($weekend == 3) {
                $weekendsDay[] = 'Wednesday';
            }
            if ($weekend == 4) {
                $weekendsDay[] = 'Thursday';
            }
            if ($weekend == 5) {
                $weekendsDay[] = 'Friday';
            }
            if ($weekend == 6) {
                $weekendsDay[] = 'Saturday';
            }
        }

        if (in_array(date('l', strtotime($date)), $weekendsDay)) {
            return true;
        } else {
            return false;
        }
    }
}
