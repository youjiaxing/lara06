<?php

namespace App\Console;

use App\Console\Commands\Cron\FinishCrowdfundingCommand;
use App\Console\Commands\Cron\UpdateInstallmentFineCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();

        // if (Application::getInstance()->isLocal()) {
        //     return;
        // }

        // 众筹商品结束处理
        $schedule->command(FinishCrowdfundingCommand::class)
            ->everyMinute()
            ->onOneServer()
            ->runInBackground()
            ->withoutOverlapping(10);

        $schedule->command(UpdateInstallmentFineCommand::class)
            ->daily()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
