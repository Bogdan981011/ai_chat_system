<?php
header('Content-Type: application/json');

// Basic HTTP Authentication check
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    // Ask the client to provide credentials
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['reply' => 'Authentication required']);
    exit;
} else {
    // Define your expected username and password (store these securely in production)
    $expectedUser = 'myuser';
    $expectedPass = 'password';
    
    if ($_SERVER['PHP_AUTH_USER'] !== $expectedUser || $_SERVER['PHP_AUTH_PW'] !== $expectedPass) {
        header('WWW-Authenticate: Basic realm="Restricted Area"');
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['reply' => 'Forbidden: invalid credentials. Expected --> ' . $expectedUser]);
        exit;
    }
}

// Include the API helper functions
require_once 'apis.php';

// Fetch user message
$requestBody = file_get_contents('php://input');
error_log("chatbot.php received JSON: " . $requestBody);

$data = json_decode($requestBody, true);
$userMessage = strtolower(trim($data['formattedQuery'] ?? ''));
$chatInfo = strtolower(trim($data['informations'] ?? ''));
error_log("userMessage : " . $userMessage);

// Check for country-specific queries using REST Countries
if (preg_match('/tell me about (.+)/', $userMessage, $matches)) {
    $countryName = $matches[1];

    // Fetch country data
    $countryData = callRestCountriesAPI($countryName);
    if (!empty($countryData)) {
        $country = $countryData[0];
        $reply = "Here's some information about {$country['name']['common']}:\n";
        $reply .= "Capital: {$country['capital'][0]}\n";
        $reply .= "Region: {$country['region']}\n";
        $reply .= "Population: " . number_format($country['population']) . "\n";
        $reply .= "Languages: " . implode(', ', $country['languages']) . "\n";
        // Append $chatInfo if it exists
        if (!empty($chatInfo)) {
            $reply .= "\nAdditional info: " . $chatInfo;
        }

        echo json_encode(['reply' => $reply]);
        exit;
    } else {
        error_log("REST Countries API returned no data for: {$countryName}");
        echo json_encode(['reply' => "Sorry, no data about {$countryName}."]);
        exit;
    }
}

// Check for city-specific POI queries
if (preg_match('/find (.+) in (.+)/', $userMessage, $matches)) {
    $amenityType = $matches[1];
    $cityName = $matches[2];

    $coordinates = getCoordinatesByCityName($cityName);
    if ($coordinates) {
        $lat = $coordinates['lat'];
        $lon = $coordinates['lon'];
        $radius = 20000;

        $osmData = callOpenStreetMapAPI($lat, $lon, $radius, $amenityType);
        $googlePlaceData = null;

        if ($osmData && isset($osmData['elements'])) {
            $amenities = [];
            foreach ($osmData['elements'] as $element) {
                $amenities[] = [
                    'name' => $element['tags']['name'] ?? 'Unknown',
                    'type' => $amenityType,
                    'lat' => $element['lat'],
                    'lon' => $element['lon'],
                ];
            }

            echo json_encode([
                'reply' => $chatInfo,
                'mapData' => [
                    'type' => 'places',
                    'lat' => $lat,
                    'lon' => $lon,
                    'amenities' => $amenities
                ]
            ]);
            exit;
        }
    }
}

// Check for route calculation queries
if (preg_match('/route from (.+) to (.+)/', $userMessage, $matches)) {
    $startCity = $matches[1];
    $endCity = $matches[2];

    // Get coordinates for both cities
    $startCoordinates = getCoordinatesByCityName($startCity);
    $endCoordinates = getCoordinatesByCityName($endCity);

    if ($startCoordinates && $endCoordinates) {
        echo json_encode([
            'reply' => $chatInfo,
            'routeData' => [
                'start' => [
                    'lat' => $startCoordinates['lat'],
                    'lon' => $startCoordinates['lon']
                ],
                'end' => [
                    'lat' => $endCoordinates['lat'],
                    'lon' => $endCoordinates['lon']
                ]
            ]
        ]);
        exit;
    } else {
        echo json_encode(['reply' => "Sorry, I couldn't find coordinates for one or both of the cities."]);
        exit;
    }
}

// Check for weather queries using OpenWeather API
if (preg_match('/weather in (.+)/', $userMessage, $matches)) {
    $cityName = $matches[1];

    // Get coordinates for the city
    $coordinates = getCoordinatesByCityName($cityName);

    if ($coordinates) {
        $lat = $coordinates['lat'];
        $lon = $coordinates['lon'];

        // Fetch current weather and 5-day forecast using the combined function
        $weatherData = callOpenWeatherAPI($lat, $lon);

        if ($weatherData && isset($weatherData['currentWeather'], $weatherData['forecast'])) {
            $currentWeather = $weatherData['currentWeather'];
            $forecast = $weatherData['forecast'];

            // Build the reply for current weather
            $reply = "Current weather in $cityName:\n";
            $reply .= "- Temperature: {$currentWeather['main']['temp']}°C, feels like {$currentWeather['main']['feels_like']}°C\n";
            $reply .= "- Condition: {$currentWeather['weather'][0]['description']}\n";
            $reply .= "- Wind speed: {$currentWeather['wind']['speed']} m/s\n\n";

            // Group forecast data by day
            $forecastByDays = [];
            foreach ($forecast['list'] as $forecastEntry) {
                $dateTime = $forecastEntry['dt_txt'];
                $date = explode(' ', $dateTime)[0]; // Extract the date part
                $time = explode(' ', $dateTime)[1]; // Extract the time part
                $temp = $forecastEntry['main']['temp'];
                $condition = $forecastEntry['weather'][0]['description'];

                $forecastByDays[$date][] = [
                    'time' => $time,
                    'temperature' => $temp,
                    'condition' => $condition
                ];
            }

            // Build the forecast table as HTML
            $forecastTable = "<table style='width: 100%; border-collapse: collapse;'>";
            $forecastTable .= "<thead>";
            $forecastTable .= "<tr style='background-color: #0078d7; color: #fff;'>";
            $forecastTable .= "<th style='padding: 8px; border: 1px solid #ddd;'>Date</th>";
            $forecastTable .= "<th style='padding: 8px; border: 1px solid #ddd;'>Time</th>";
            $forecastTable .= "<th style='padding: 8px; border: 1px solid #ddd;'>Temperature (°C)</th>";
            $forecastTable .= "<th style='padding: 8px; border: 1px solid #ddd;'>Condition</th>";
            $forecastTable .= "</tr>";
            $forecastTable .= "</thead>";
            $forecastTable .= "<tbody>";

            foreach ($forecastByDays as $date => $entries) {
                foreach ($entries as $entry) {
                    $forecastTable .= "<tr>";
                    $forecastTable .= "<td style='padding: 8px; border: 1px solid #ddd;'>$date</td>";
                    $forecastTable .= "<td style='padding: 8px; border: 1px solid #ddd;'>{$entry['time']}</td>";
                    $forecastTable .= "<td style='padding: 8px; border: 1px solid #ddd;'>{$entry['temperature']}°C</td>";
                    $forecastTable .= "<td style='padding: 8px; border: 1px solid #ddd;'>{$entry['condition']}</td>";
                    $forecastTable .= "</tr>";
                }
            }

            $forecastTable .= "</tbody>";
            $forecastTable .= "</table>";

            // Return response with current weather and forecast
            echo json_encode([
                'reply' => $reply . "\n" .$chatInfo,
                'forecastTable' => $forecastTable, // Ensure this contains the HTML table
                'mapData' => [
                    'type' => 'weather',
                    'lat' => $lat,
                    'lon' => $lon,
                    'currentWeather' => [
                        'temperature' => $currentWeather['main']['temp'],
                        'condition' => $currentWeather['weather'][0]['description']
                    ],
                    'forecast' => array_map(function ($entry) {
                        return [
                            'dateTime' => $entry['dt_txt'],
                            'temperature' => $entry['main']['temp'],
                            'condition' => $entry['weather'][0]['description']
                        ];
                    }, $forecast['list'])
                ]
            ]);
            exit;
        } else {
            echo json_encode(['reply' => "Sorry, I couldn't fetch weather data for $cityName."]);
            exit;
        }
    } else {
        echo json_encode(['reply' => "Sorry, I couldn't find coordinates for $cityName."]);
        exit;
    }
}


if (preg_match('/details concerning (.+) in (.+)/', $userMessage, $matches)) {
    $query = $matches[1]; // The type of place (e.g., restaurants, cafes, etc.)
    $cityName = $matches[2]; // The city name

    // Fetch the coordinates for the city
    $coordinates = getCoordinatesByCityName($cityName);
    if ($coordinates) {
        $lat = $coordinates['lat'];
        $lon = $coordinates['lon'];

        // Call the Google Places API to fetch places
        $placesData = callGooglePlacesAPI($lat, $lon, $query);

        if ($placesData && isset($placesData['results'])) {
            $places = [];
        
            foreach ($placesData['results'] as $place) {
                $placeDetails = fetchGooglePlaceDetails($place['place_id']);
        
                if ($placeDetails) {
                    $name = $placeDetails['result']['name'] ?? 'Unknown';
                    $phone = $placeDetails['result']['formatted_phone_number'] ?? 'No phone number';
                    $rating = $placeDetails['result']['rating'] ?? 'No rating';
                    $hours = $placeDetails['result']['opening_hours']['weekday_text'] ?? 'No hours available';
                    if (is_array($hours)) {
                        $hours = implode(', ', $hours);
                    }
        
                    $places[] = [
                        'name' => $name,
                        'phone' => $phone,
                        'rating' => $rating,
                        'hours' => $hours,
                    ];
                }
            }
        
            echo json_encode([
                'reply' => $chatInfo,
                'places' => $places
            ]);
            exit;        
        } else {
            echo json_encode(['reply' => "Sorry, I couldn't find any $query in $cityName."]);
            exit;
        }
    } else {
        echo json_encode(['reply' => "Sorry, I couldn't find coordinates for $cityName."]);
        exit;
    }
}



// Default response for unrecognized queries
error_log("Unrecognized query: $userMessage");
echo json_encode(['reply' => 'I\'m not sure how to answer that. Can you try rephrasing?']);
