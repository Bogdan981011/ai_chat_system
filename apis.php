<?php

/**
 * ===========================================
 * 
 *          API INTEGRATION FUNCTIONS
 * 
 * ===========================================
 * 
 * ----------------------------------------------------------------------------------------------------------------------------------------------
 * 
 * 1. callAPI(base url, endpoint) --> base structure for calling an API
 *                                --> base function used in the other API functions
 * 
 * ----------------------------------------------------------------------------------------------------------------------------------------------
 * 
 * 2. callRestCountriesAPI(country name) --> calls Rest Countries API
 *        *-* gets conutry level informations like:
 *              - country name
 *              - population
 *              - currency
 *              - spoken langauges
 *              - etc.
 * 
 * ----------------------------------------------------------------------------------------------------------------------------------------------
 * 
 * 3.1. getCoordinatesByCityName(city name) --> returns the lat and long of a city by its name
 *                                          --> used in the OpenStreetMap API
 * 
 * 
 * 3.2. callOpenStreetMapApi(lat, lon, radius, amenity) --> gets all the locations for a selected amenity type (restaurant, school, etc.)
 * 
 * ----------------------------------------------------------------------------------------------------------------------------------------------
 * 
 * 4. callOpenRouteServiceAPI(startlat, startlon, endlat, endlon, profile='car driving')
 *              --> used for calculating routes between 2 or more points
 *              
 * ----------------------------------------------------------------------------------------------------------------------------------------------
 * 
 * 5. callOpenWeatherDataAPI(latitude, longitude)
 *              --> gets current weather data for a certain place
 *              --> gets daily forecast for 5 days (3 hours period)
 * 
 * ----------------------------------------------------------------------------------------------------------------------------------------------
 * 
 * 6.1 callGooglePlacesAPI(latitude, longitude, query)
 *              --> gets some of the (query) type places for a location
 * 
 * 6.2 fetchGooglePlaceDetails(placeId)
 *              --> gets all the available informations about a place
 * 
 * ----------------------------------------------------------------------------------------------------------------------------------------------
 */

// Helper function to call APIs
function callAPI($baseUrl, $endpoint) {
    /**
     * Base structure for calling an API
     * 
     * @param string $baseurl --> url API
     * @param string $endpoint --> completion url API (if needed)
     */
    $url = $baseUrl . $endpoint;

    try {
        $response = file_get_contents($url);
        return json_decode($response, true);
    } catch (Exception $e) {
        return null; // Handle API errors gracefully
    }
}


// Fetch REST Countries API data
function callRestCountriesAPI($countryName) {
    /**
     * Gets informations about a certain country
     * 
     * @param string $countryName --> Name of a country
     */
    $baseUrl = "https://restcountries.com/v3.1/name/";
    $endpoint = urlencode($countryName);
    return callAPI($baseUrl, $endpoint);
}

function getCoordinatesByCityName($cityName) {
    /**
     * Gets the coordinates of a city
     * 
     * @param string $cityName --> Name of a city
     */
    $baseUrl = "https://nominatim.openstreetmap.org/search";
    $query = http_build_query([
        'q' => $cityName,
        'format' => 'json',
        'limit' => 1
    ]);

    $options = [
        'http' => [
            'header' => "User-Agent: MyAwesomeApp/1.0 (contact@example.com)\r\n"
        ]
    ];
    $context = stream_context_create($options);

    try {
        $response = file_get_contents("$baseUrl?$query", false, $context);
        $data = json_decode($response, true);
        if (!empty($data)) {
            return [
                'lat' => $data[0]['lat'],
                'lon' => $data[0]['lon']
            ];
        }
    } catch (Exception $e) {
        return null;
    }

    return null; // Return null if the city is not found
}



// Fetch OpenStreetMap data using Overpass API
function callOpenStreetMapAPI($lat, $lon, $radius = 5000, $amenity = "restaurant") {
    /**
     * Gets all the locations of a selected amenity type (ex: restaurants, schools, etc.)
     * 
     * @param float $lat --> latitude
     * @param float $lon --> longitude
     * @param int/float $radius --> radius in km
     * @param string $amenity --> type of places to find (restaurants, schools, etc.)
     */
    $baseUrl = "https://overpass-api.de/api/interpreter";
    
    // Overpass API query for specific POIs
    $query = "
        [out:json];
        node[\"amenity\"=\"$amenity\"](around:$radius,$lat,$lon);
        out;
    ";
    
    // URL encode the query
    $encodedQuery = urlencode($query);
    
    try {
        $response = file_get_contents($baseUrl . "?data=" . $encodedQuery);
        return json_decode($response, true);
    } catch (Exception $e) {
        return null; // Handle API errors gracefully
    }
}

function callOpenRouteServiceAPI($startLat, $startLon, $endLat, $endLon, $profile = "driving-car") {
    /**
     * Calls the OpenRouteService API to get route details between two points.
     * 
     * @param float $startLat --> Latitude of the starting point.
     * @param float $startLon --> Longitude of the starting point.
     * @param float $endLat --> Latitude of the ending point.
     * @param float $endLon --> Longitude of the ending point.
     * @param string $profile --> Profile type (driving-car, cycling-regular, walking, etc.).
     * 
     * @return array|null Route details or null in case of failure.
     */
    
    $apiKey = "5b3ce3597851110001cf6248b42080527d0f403db0d355fa0a17a547"; 
    $baseUrl = "https://api.openrouteservice.org/v2/directions/$profile";

    // Construct the API endpoint with parameters
    $query = http_build_query([
        'api_key' => $apiKey,
        'start' => "$startLon,$startLat",
        'end' => "$endLon,$endLat"
    ]);

    // Full URL
    $url = "$baseUrl?$query";

    try {
        // Make the API request
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        // Check for valid response structure
        if (isset($data['routes'][0])) {
            return $data['routes'][0]; // Return the first route
        } else {
            error_log("No route found or invalid response structure.");
            return null;
        }
    } catch (Exception $e) {
        // Handle API errors gracefully
        error_log("OpenRouteService API error: " . $e->getMessage());
        return null;
    }
}


// Open Weather API
function callOpenWeatherAPI($latitude, $longitude) {
    /**
     * Fetches current weather data and 5 days forecast using the OpenWeather API.
     *
     * @param float $latitude --> Latitude of the location.
     * @param float $longitude --> Longitude of the location.
     * @param string $apiKey --> Your OpenWeather API key.
     *
     * @return array|null Weather data or null in case of failure.
     */


    $apiKey = "5640ad06f0c08cfc5e13a73e749ad24f";
    $currentWeatherUrl = "https://api.openweathermap.org/data/2.5/weather";
    $forecastUrl = "https://api.openweathermap.org/data/2.5/forecast";


    // Construct current weather URL
    $currentWeatherQuery = http_build_query([
        'lat' => $latitude,
        'lon' => $longitude,
        'appid' => $apiKey,
        'units' => 'metric' // 'metric' for Celsius; use 'imperial' for Fahrenheit
    ]);

    // Construct current weather URL
    $forecastQuery = http_build_query([
        'lat' => $latitude,
        'lon' => $longitude,
        'appid' => $apiKey,
        'units' => 'metric' // 'metric' for Celsius; use 'imperial' for Fahrenheit
    ]);

    try {
       // Fetch current weather data
       $currentWeatherResponse = file_get_contents("$currentWeatherUrl?$currentWeatherQuery");

       // Fetch forecast data
       $forecastResponse = file_get_contents("$forecastUrl?$forecastQuery");

        // Decode both responses if valid
        $currentWeather = $currentWeatherResponse !== false ? json_decode($currentWeatherResponse, true) : null;
        $forecast = $forecastResponse !== false ? json_decode($forecastResponse, true) : null;

        // Return combined data
        return [
            'currentWeather' => $currentWeather,
            'forecast' => $forecast
        ];
    } catch (Exception $e) {
        // Handle any errors
        error_log("Error calling OpenWeather API: " . $e->getMessage());
        return null;
    }
}

// Fetch place details
function fetchGooglePlaceDetails($placeId) {
    $apiKey = "AIzaSyBfMQcUgCgwC8Qdbwa1tcTscxVLZpiSpPc";
    $baseUrl = "https://maps.googleapis.com/maps/api/place/details/json";
    $queryParams = http_build_query([
        'place_id' => $placeId,
        'fields' => 'name,rating,formatted_phone_number,opening_hours,website,geometry',
        'key' => $apiKey
    ]);
    $url = "$baseUrl?$queryParams";

    try {
        $response = file_get_contents($url);

        if ($response) {
            return json_decode($response, true);
        } else {
            error_log("Failed to fetch data from Google Place Details API.");
            return null;
        }
    } catch (Exception $e) {
        error_log("Google Place Details API error: " . $e->getMessage());
        return null;
    }
}

// Fetch nearby places
function callGooglePlacesAPI($lat, $lon, $type) {
    $apiKey = "AIzaSyBfMQcUgCgwC8Qdbwa1tcTscxVLZpiSpPc";
    $baseUrl = "https://maps.googleapis.com/maps/api/place/nearbysearch/json";
    $queryParams = http_build_query([
        'location' => "$lat,$lon",
        'radius' => 20000,
        'type' => $type,
        'key' => $apiKey
    ]);
    $url = "$baseUrl?$queryParams";

    try {
        $response = file_get_contents($url);

        if ($response) {
            return json_decode($response, true);
        } else {
            error_log("Failed to fetch data from Google Places API.");
            return null;
        }
    } catch (Exception $e) {
        error_log("Google Places API error: " . $e->getMessage());
        return null;
    }
}