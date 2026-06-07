<?php

return [
    'debug_logs' => env('API_SYNC_DEBUG_LOGS', false),
    'max_course_jobs_per_person' => (int) env('API_SYNC_MAX_COURSE_JOBS_PER_PERSON', 100),
];
