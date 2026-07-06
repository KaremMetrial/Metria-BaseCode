<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
*/

// Publish pending domain events from the transactional outbox.
Schedule::command('outbox:publish')->everyMinute()->withoutOverlapping();

// Prune expired governance data.
Schedule::command('governance:prune')->daily();
