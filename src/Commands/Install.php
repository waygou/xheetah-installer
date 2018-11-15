<?php

namespace Waygou\XheetahInstaller\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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

        // Obtain Xheetah Nova Library.
        $this->info('Importing waygou/xheetah-nova composer library (takes some minutes) ...');
        $this->commandExecute('composer require waygou/xheetah-nova');
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
