<?php
namespace AdminCrud\CrudGenerator;

use Illuminate\Support\ServiceProvider;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Crud::class,
                ApiCrud::class,
                MakeAuthCommand::class,
                MakeApiAuthCommand::class
            ]);
        }
    }

    public function register()
    {
        //
    }
}