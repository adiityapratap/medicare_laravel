<?php

namespace App\Providers;

namespace App\Providers;

use Google_Client;
use Auth;
use App\GoogleAccounts;
use App\Http\Helpers\AppHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use \Hypweb\Flysystem\GoogleDrive\GoogleDriveAdapter;

class GoogleClientProvider extends ServiceProvider
{
    public function boot()
    {
        $default_account = AppHelper::getAppSettings('default_google_account');
        if(!$default_account) {
            return;
        }
        $ga = GoogleAccounts::where('id', $default_account)->first();

        Storage::extend('gdrive', function($app, $config) use ($ga){
            // dd($config);
            $client = new \Google_Client();
            $client->setClientId($config['client_id']);
            $client->setClientSecret($config['secret']);
            $client->refreshToken($ga->refresh_token);
            $service = new \Google_Service_Drive($client);

            $options = [];
            if(isset($config['teamDriveId'])) {
                $options['teamDriveId'] = $config['teamDriveId'];
            }
            $adapter = new GoogleDriveAdapter($service, 'root', $options);

            return new \League\Flysystem\Filesystem($adapter);
        });
    }

    public function register()
    {
        //
    }
}