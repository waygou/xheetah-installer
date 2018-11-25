<?php

namespace Waygou\XheetahInstaller\Commands;

use Hyn\Tenancy\Environment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use PHLAK\Twine\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Waygou\MultiTenant\Services\TenantProvision;
use Waygou\Surveyor\Models\Profile;
use Waygou\Xheetah\Models\Client;
use Waygou\Xheetah\Models\MainRole;
use Waygou\Xheetah\Models\User;

class Install extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xheetah:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Installs Xheetah for the first time in a new server.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->lineSpace();
        $this->info('----------------------------');
        $this->info('--- Xheetah installation ---');
        $this->info('----------------------------');
        $this->lineSpace();

        if (!File::exists(app_path('Providers/NovaServiceProvider.php'))) {
            $this->info('Checking if Nova is installed ...');

            return $this->error('Looks like Laravel Nova is not installed on your system. Please try again.');
        }

        // Obtain Xheetah Nova Library. -- It will install all necessary libraries.
        $this->info('Importing waygou/xheetah-nova composer library (takes some minutes) ...');
        $this->commandExecute('composer require waygou/xheetah-nova');

        $this->info('Publishing xheetah/utils resources ...');
        $this->commandExecute('php artisan vendor:publish --tag=xheetah-utils-resources --force');

        $this->info('Publishing xheetah/utils migration file ...');
        $this->commandExecute('php artisan vendor:publish --tag=xheetah-utils-create-schema --force');

        $this->info('Publishing all Laravel Nova files ...');
        $this->commandExecute('php artisan vendor:publish --provider=Laravel\Nova\NovaServiceProvider --force');

        $this->info('Publishing Hyn/multi-tenant tenancy tag ...');
        $this->commandExecute('php artisan vendor:publish --tag=tenancy --force');

        $this->info('Publishing Waygou Surveyor files ...');
        $this->commandExecute('php artisan vendor:publish --provider=Waygou\Surveyor\ServiceProvider --force');

        $this->info('Publishing Waygou Surveyor Nova files ...');
        $this->commandExecute('php artisan vendor:publish --provider=Waygou\SurveyorNova\ToolServiceProvider --force');

        $this->info('Publishing Waygou Xheetah files ...');
        $this->commandExecute('php artisan vendor:publish --provider=Waygou\Xheetah\ServiceProvider --force');

        $this->info('Publishing Waygou Xheetah Nova files ...');
        $this->commandExecute('php artisan vendor:publish --provider=Waygou\XheetahNova\ToolServiceProvider --force');

        $this->info('Copying migrations folder to tenant folder ...');
        $files = collect(glob(
            database_path('migrations/*')
        ));

        // Filter the specific migration files that should be copied to the
        // migrations/tenant folder.
        $tenantMigrationFiles = $files->filter(function ($value, $key) {
            $file = new Str($value);

            return $file->contains('_create_users') ||
                   $file->contains('_password_resets') ||
                   $file->contains('_action_events') ||
                   $file->contains('_surveyor') ||
                   $file->contains('_xheetah_');
        });

        File::makeDirectory(database_path('migrations/tenant'));

        // Copy all the files to the tenant directory.
        $tenantMigrationFiles->each(
            function ($value) {
                File::copy($value, database_path('migrations/tenant/'.basename($value)));
            }
        );

        // Delete migration files that are not needed in the database/migrations.
        $this->info('Deleting migration files that are no longer needed in the database/migrations folder ...');
        $migrationFilesToDelete = $files->filter(function ($value, $key) {
            $file = new Str($value);

            return $file->contains('_xheetah_schema');
        });

        $migrationFilesToDelete->each(function ($value) {
            File::delete($value);
        });

        $this->info('Running composer dumpautoload ...');
        $this->commandExecute('composer dumpautoload');

        $this->info('Running migrations FRESH (installs users, password resets and xheetah utils schema) ...');
        $this->commandExecute('php artisan migrate:fresh');

        $this->info("Creating the 'genesys' tenant ...");
        // What environment are we? Should use auto db, https?
        if (App::environment('local')) {
            $website = TenantProvision::createTenant('genesys', true, false);
        } else {
            $website = TenantProvision::createTenant('genesys', false, true);
        }

        // Change default hyn environment to the genesys tenant.
        $environment = app()->make(\Hyn\Tenancy\Environment::class);
        $environment->tenant($website);

        $this->info('Seeding the genesys tenant with the schema creation seeder ...');
        $this->commandExecute('php artisan tenancy:db:seed --class=Waygou\Xheetah\Seeders\InstallSeeder --website_id='.$website->id);

        /*
         * Install a super admin in the new tenant.
         * 1. Create the client 'Genesys'.
         * 2. Create the user associated to client, main role code=super admin.
         * 3. Associate a Surveyor profile
         */

        Client::saveMany([
            ['name' => 'Genesys'],
        ]);

        User::saveMany([
            ['name'         => 'Super Admin',
             'email'        => 'superadmin@genesys.com',
             'password'     => bcrypt('honda'),
             'phone'        => '+418765411',
             'client_id'    => 1,
             'main_role_id' => MainRole::where('code', 'super-admin')->first()->id, ],
        ])->each(function ($user) {
            $user->profiles()->attach(Profile::where('code', 'super-admin')->first()->id);
        });

        $this->info('Overriding Kernel.php to install the new tenany.enforce middleware ...');
        $this->commandExecute('php artisan vendor:publish --tag=waygou-xheetah-installer-kernel-override --force');
    }

    private function lineSpace($num = 3)
    {
        for ($i = 0; $i < $num; $i++) {
            $this->info('');
        }
    }

    protected function commandExecute($command)
    {
        $process = new Process($command);
        $process->setTimeout(300);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->error($process->getOutput());
    }
}
