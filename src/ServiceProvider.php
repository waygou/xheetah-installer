<?php

namespace Waygou\XheetahInstaller;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        Schema::defaultStringLength(191);

        $this->registerCommands();

        $this->publishFiles();
    }

    public function publishFiles()
    {
        $this->publishes([
            __DIR__.'/../config/database.php.stub' => config_path('database.php'),
            __DIR__.'/../config/tenancy.php.stub'  => config_path('tenancy.php'),
            __DIR__.'/../config/app.php.stub'      => config_path('app.php'),
            __DIR__.'/../.env.stub'                => base_path('.env'),
        ], 'waygou-xheetah-installer-init');
    }

    protected function registerCommands()
    {
        $this->commands([
            \Waygou\XheetahInstaller\Commands\Install::class,
            \Waygou\XheetahInstaller\Commands\Init::class,
        ]);
    }

    public function register()
    {
    }
}
