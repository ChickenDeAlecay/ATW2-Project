<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Build the API URL with parameters
$api_url = BRISTOL_API_URL . '?' . http_build_query(BRISTOL_API_PARAMS);

// Simple file-based caching
$cache_file = 'cache/trees_data.json';
$cache_dir = dirname($cache_file);

// Create cache directory if it doesn't exist
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// Check if cache exists and is still valid
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < CACHE_DURATION) {
    // Add ETag for better caching
    $etag = md5_file($cache_file);
    
    // Check if client has current version
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
        http_response_code(304);
        exit;
    }
    
    // Send ETag header
    header("ETag: $etag");
    
    // Serve from cache
    echo file_get_contents($cache_file);
    exit;
}

// Function to fetch data with pagination
function fetchAllTrees() {
    $all_features = [];
    $offset = 0;
    $batch_size = 1000; // Bristol API appears to limit to 1000 records per request
    $has_more = true;
    
    while ($has_more) {
        $batch_data = null;
        
            // Update API parameters with pagination
            $params = BRISTOL_API_PARAMS;
            $params['resultRecordCount'] = $batch_size;
            $params['resultOffset'] = $offset;

            error_log("Fetching batch: offset=$offset, batch_size=$batch_size");

            // Build the URL for this batch
            $batch_url = BRISTOL_API_URL . '?' . http_build_query($params);
            
            // Initialize curl for this batch
            $ch = curl_init();
            
            // Set curl options
            curl_setopt($ch, CURLOPT_URL, $batch_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Bristol Trees Map/1.0');
            
            // Execute the request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($response === false || !empty($error)) {
                error_log("cURL Error : " . $error);
                continue;
            }
            
            if ($http_code !== 200) {
                error_log("HTTP Error : " . $http_code);
                continue;
            }
            
            // Decode JSON
            $batch_data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON Error : " . json_last_error_msg());
                continue;
            }
        

            $batch_features = $batch_data['features'];
            $features_count = count($batch_features);
            $all_features = array_merge($all_features, $batch_features);
            
            error_log("Batch completed - got $features_count records, total so far: " . count($all_features));
            
            // Check if we got fewer records than requested (end of data)
            // Since Bristol API limits to 1000 records max, we continue if we get exactly 1000
            if ($features_count === 0) {
                error_log("End of data reached - got 0 records");
                $has_more = false;
            } else if ($features_count < $batch_size) {
                error_log("End of data reached - got $features_count records (less than $batch_size)");
                $has_more = false;
            } else {
                $offset += $batch_size;
                error_log("Continuing to next batch with offset: $offset");
            }
    }
    
    error_log("Finished fetching. Total features: " . count($all_features));
    
    // Construct the final response in the same format as the original API
    $final_data = [
        'features' => $all_features,
        'totalCount' => count($all_features)
    ];
    
    return $final_data;
}

// Fetch all trees using pagination
$data = fetchAllTrees();

// Return the data
$json_response = json_encode($data);

// Cache the response
file_put_contents($cache_file, $json_response);

echo $json_response;

?>