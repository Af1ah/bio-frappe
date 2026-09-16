<?php

return [
    /*
     * The Go gateway calls the Laravel internal API with a scoped Sanctum
     * token. Do not place this value in source control or in a device URL.
     */
    'internal_token_ability' => 'device-gateway:write',

    'max_events_per_batch' => (int) env('DEVICE_GATEWAY_MAX_EVENTS_PER_BATCH', 250),
];
