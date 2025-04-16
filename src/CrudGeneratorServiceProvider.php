<?php
namespace AdminCrud\CrudGenerator;

use Illuminate\Support\ServiceProvider;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SimpleCrud::class,
                ApiCrud::class
            ]);
        }
    }

    public function register()
    {
        //
    }
}