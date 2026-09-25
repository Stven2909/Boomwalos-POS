<?php

return [
    'require_explicit_establishment' => (bool) env('POS_REQUIRE_EXPLICIT_ESTABLISHMENT', false),
    'catalogo_images_dir' => env('CATALOGO_IMAGES_DIR', ''),
];
