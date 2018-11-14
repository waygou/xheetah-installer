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

        $this->overrideFiles();
    }

    public function overrideFiles()
    {
        $this->publishes([
            __DIR__.'/../config/database.php.stub' => config_path('database.php'),
            __DIR__.'/../config/app.php.stub'      => config_path('app.php'),
            __DIR__.'/../.env.stub'                => base_path('.env')
        ], 'waygou-xheetah-installer-overrides');
    }

    protected function registerCommands()
    {
        $this->commands([
            \Waygou\XheetahInstaller\Commands\Install::class,
        ]);
    }

    public function register()
    {
    }
}
