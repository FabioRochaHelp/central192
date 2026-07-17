<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inactivity Auto Lock
    |--------------------------------------------------------------------------
    |
    | When greater than zero, the session is locked automatically after the
    | configured number of minutes without user interaction. Set to 0 to disable.
    |
    */

    'idle_minutes' => (int) env('SCREEN_LOCK_IDLE_MINUTES', 0),

];
