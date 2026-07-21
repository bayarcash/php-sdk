<?php

namespace Bayarcash;

use Illuminate\Support\ServiceProvider;

class BayarcashSdkServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (!defined('BAYARCASH_SDK_PATH')) {
            define('BAYARCASH_SDK_PATH', realpath(__DIR__ . '/../'));
        }

        $this->mergeConfigFrom(
            BAYARCASH_SDK_PATH .'/config/bayarcash_sdk.php', 'bayarcash_sdk'
        );

        $this->app->singleton(Bayarcash::class, function ($app) {
            return new Bayarcash(
                $app['config']->get('bayarcash_sdk.token'),
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->offerPublishing();
    }

    /**
     * Setup the resource publishing groups for Bayarcash Laravel.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                BAYARCASH_SDK_PATH . '/config/bayarcash_sdk.php' => config_path('bayarcash_sdk.php'),
            ], 'bayarcash-sdk-config');
        }
    }
}
