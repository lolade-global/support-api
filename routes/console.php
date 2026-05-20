<?php

use Illuminate\Support\Facades\Schedule;

// Auto-close idle conversations nightly.
Schedule::command('conversations:close-stale --days=14')->dailyAt('02:00');
