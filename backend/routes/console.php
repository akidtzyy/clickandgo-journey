<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\CancelExpiredBookings;

/*
|--------------------------------------------------------------------------
| Console Schedule — Laravel 11 style (routes/console.php)
|--------------------------------------------------------------------------
*/

// Run the expired booking janitor every minute
Schedule::command(CancelExpiredBookings::class)->everyMinute();
