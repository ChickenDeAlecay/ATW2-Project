<?php
// Google Maps API Key - Replace with your actual API key
define('GOOGLE_MAPS_API_KEY', 'AIzaSyDFLNW9cPquUOv6b5mxXTOtYP_9qWpuko8');

// Bristol Trees API settings
define('BRISTOL_API_URL', 'https://maps2.bristol.gov.uk/server2/rest/services/ext/ll_environment_and_planning/MapServer/32/query');
define('BRISTOL_API_PARAMS', [
    'where' => '1=1',
    'outFields' => 'TYPE,UNIT,X,Y,DEAD,CLASSIFICATION,LATIN_NAME,FULL_COMMON_NAME,CROWN_HEIGHT,CROWN_WIDTH,CROWN_AREA,ASSET_ID,PRIM_MEAS',
    'outSR' => '4326',
    'f' => 'json'
]);

// Default map settings (Bristol, UK)
define('DEFAULT_LAT', 51.4545);
define('DEFAULT_LNG', -2.5879);
define('DEFAULT_ZOOM', 12);

// Cache settings
define('CACHE_DURATION', 21600);
?>