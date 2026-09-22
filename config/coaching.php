<?php

return [
    // Installation-level gate; individual UVS contracts additionally need explicit opt-in.
    'enabled' => (bool) env('COACHING_ENABLED', false),
];
