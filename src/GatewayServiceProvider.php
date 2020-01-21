<?php

namespace ApiSdk;

use Illuminate\Support\ServiceProvider;

class GatewayServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ApiSdk\GatewayApi', function ($app) {
            return new GatewayApi($app->make('GuzzleHttp\Client'), env('GATEWAY_API_URL'), env('GATEWAY_API_ENV'), env('GATEWAY_API_APP'),
                env('GATEWAY_API_APP_TOKEN'), env('APP_ENV')=='local');
        });

        $this->app->singleton('ApiSdk\AuthApi', function ($app) {
            return new AuthApi($app->make('ApiSdk\GatewayApi'), env('AUTH_API_CLIENT_ID'), env('AUTH_API_CLIENT_SECRET'), env('APP_URL').'/oauth_callback');
        });

        $this->app->bind('coreapi',function($app){
            return $app->make('ApiSdk\CoreApi');
        });

        $this->app->bind('reportsapi',function($app){
            return $app->make('ApiSdk\ReportsApi');
        });

        $this->app->bind('filesapi',function($app){
            return $app->make('ApiSdk\FilesApi');
        });

        $this->app->bind('authapi',function($app){
            return $app->make('ApiSdk\AuthApi');
        });
    }
}
