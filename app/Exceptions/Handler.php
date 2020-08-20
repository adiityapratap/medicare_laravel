<?php

namespace App\Exceptions;

use App\Http\Helpers\AppHelper;
use Exception;
use Abrigham\LaravelEmailExceptions\Exceptions\EmailHandler as ExceptionHandler;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * @param Exception $exception
     * @return mixed|void
     * @throws Exception
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception               $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if ($request->expectsJson() || $request->isJson()) {   //add Accept: application/json in request
            return $this->handleApiException($request, $exception);
        }
        if (method_exists($exception, 'getStatusCode')) {

            $statusCode = $exception->getStatusCode();

            if(!env('APP_DEBUG', false)) {
                if (!$request->user() && AppHelper::isFrontendEnabled()) {
                    $locale = Session::get('user_locale');
                    App::setLocale($locale);

                    if ($statusCode == 404) {
                        return response()->view('errors.front_404', [], 404);
                    }

                    if ($statusCode == 500) {

                        return response()->view('errors.front_500', [], 500);
                    }

                }
            }

            if ($request->user()) {
                if ($statusCode == 404) {
                    return response()->view('errors.back_404', [], 404);
                }

                if ($statusCode == 401) {
                    return response()->view('errors.back_401', [], 404);
                }
            }



        }



        return parent::render($request, $exception);
    }

    private function handleApiException($request, Exception $exception)
    {
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();
        } else {
            $statusCode = 500;
        }

        $response = [];

        switch ($statusCode) {
            case 401:
                $response['message'] = 'You are not allowed to be around here!';
                break;
            case 403:
                $response['message'] = 'Forbidden area, please stay away from here!';
                break;
            case 404:
                $response['message'] = 'Oops, something is missing here!';
                break;
            case 405:
                $response['message'] = 'Oops, looks like someone is out of mind!';
                break;
            case 422:
                $response['message'] = $exception->original['message'];
                $response['errors'] = $exception->original['errors'];
                break;
            default:
                $response['message'] = ($statusCode == 500) ? 'Oops!, I lost you for a moment. Can you please try again?' : $exception->getMessage();
                break;
        }

        if (config('app.debug')) {
            $response['trace'] = $exception->getTrace();
            $response['code'] = $exception->getCode();
        }

        $response['success'] = false;
        $response['status'] = $statusCode;

        return response()->json($response, $statusCode);
    }
}
