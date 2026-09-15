<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Desktop OUT sync (Phase 3)
    |--------------------------------------------------------------------------
    |
    | Cloud backup of append-only events from stand-alone installs.
    | Does not mutate SaaS tenant databases.
    |
    */

    'max_events_per_batch' => (int) env('DESKTOP_SYNC_MAX_EVENTS_PER_BATCH', 200),

];
