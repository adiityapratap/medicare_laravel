<?php

namespace App\Listeners\Auth;

use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;

class UserLoggedIn
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the event.
     *
     * @param  Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        $date = Carbon::now();
        $message = $event->user->name.' logged in from IP '.$this->request->ip().' at '.$date->toDateTimeString().'.';
        activity()
            ->causedBy($event->user)
            ->withProperties(['ip' => $this->request->ip()])
            ->log($message);
    }
}
