<?php
namespace KWRI\ApiaryGenerator;

use Illuminate\Support\ServiceProvider;
use KWRI\ApiaryGenerator\Console\ApiaryCommand;

class LaravelDddServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the API doc commands.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiaryCommand::class,
            ]);
        }
    }
}
