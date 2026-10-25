<?php

/*
 * The omnimap/* providers the harness installs beside the core: their tests'
 * namespace, and what their tests need besides (require-dev).
 */
return [
    'google' => ['Omnimap\\Google\\Tests\\'],
    'openstreetmap' => ['Omnimap\\OpenStreetMap\\Tests\\'],
    'maplibre' => ['Omnimap\\MapLibre\\Tests\\'],
];
