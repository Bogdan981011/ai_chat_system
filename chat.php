<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fullscreen AI Chat</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            padding-top: 80px;
        }
        .chat-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #ffffff;
        }
        .chat-header {
            background: #0078d7;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            flex-shrink: 0;
        }
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f9f9f9;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-messages .message {
            max-width: 75%;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 15px;
            font-size: 16px;
            line-height: 1.4;
        }
        .chat-messages .message.user {
            align-self: flex-end;
            background: #0078d7;
            color: white;
        }
        .chat-messages .message.bot {
            align-self: flex-start;
            background: #e5e5ea;
            color: #333;
        }
        .chat-input {
            display: flex;
            padding: 15px;
            background: #f4f4f9;
            border-top: 1px solid #ddd;
            flex-shrink: 0;
        }
        .chat-input input {
            flex: 1;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            outline: none;
            font-size: 16px;
            margin-right: 10px;
        }
        .chat-input button {
            padding: 15px 25px;
            background: #0078d7;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
        }
        .chat-input button:hover {
            background: #005bb5;
        }
        .map-controls {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 10px;
}

.map-controls button {
    padding: 10px 20px;
    font-size: 14px;
    cursor: pointer;
    border: none;
    border-radius: 5px;
    background: #0078d7;
    color: white;
}

.map-controls button:hover {
    background: #005bb5;
}

#map {
    height: 300px;
    width: 100%;
    margin-top: 10px;
    transition: height 0.3s ease-in-out;
}

.menu-bar {
    display: flex;
    width: 100%;
    justify-content: space-around; /* or space-between */
    align-items: center;
    background-color: #f4f4f4;
    padding: 10px 20px;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1000;
}

.menu-item {
    text-align: center;
    cursor: pointer;
}

.menu-item img {
    width: 50px;
    height: 50px;
    object-fit: contain;
    background-color: transparent;
}

.menu-item p {
    margin: 5px 0 0;
    font-size: 14px;
    color: #333;
}

.menu-item.logo {
    position: relative;
    width: 50px;
    height: 50px;
    bottom: -20px;
    border-radius: 80%;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
    padding: 10px;
    background-color: #f4f4f4;
}

.menu-item.logo img {
    width: 150%; /* The image is intentionally larger than its container */
    height: 150%;
    object-fit: cover;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

    </style>
</head>
<body>
    <?php include ('../navbar.php'); ?>
    <div class="chat-container">
        <div class="chat-header">AI Chat Assistant</div>
        <div class="chat-messages" id="chatMessages"></div>
        <div id="mapContainer">
            <div class="map-controls">
                <button onclick="toggleMap()">Hide Map</button>
                <button onclick="resizeMap()">Make Map Bigger</button>
                <button onclick="getUserLocation()">Show My Location</button>
                <button onclick="resetMap()">Reset Map</button>
            </div>
            <div id="map"></div>
        </div>
        <div class="chat-input">
            <input type="text" id="userMessage" placeholder="Type a message..." />
            <button onclick="sendMessage()">Send</button>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
    <script>
        let map; // Global map variable
        let routingControl; // Routing control for routes
        let markersLayer = L.layerGroup(); // Layer group for places of interest
        let mapVisible = true; // Track map visibility
        let mapExpanded = false; // Track if the map is expanded

        function toggleMap() {
    const mapElement = document.getElementById('map');
    const button = document.querySelector('.map-controls button:first-child');

    if (mapVisible) {
        mapElement.style.display = 'none';
        button.textContent = 'Show Map';
    } else {
        mapElement.style.display = 'block';
        button.textContent = 'Hide Map';
    }

    mapVisible = !mapVisible;
}


function getUserLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;

                console.log('User Location:', { lat, lon }); // Debug

                // Display the user's location on the map
                initializeMap(lat, lon);

                // Add a marker for the user's location
                const userMarker = L.circleMarker([lat, lon], {
                        color: 'red', // Border color
                        fillColor: 'red', // Fill color
                        fillOpacity: 0.8, // Fill opacity
                        radius: 10 // Radius of the marker
                  }).bindPopup('You are here!')
                    .addTo(map);

                // Zoom to user's location
                map.setView([lat, lon], 13);
            },
            (error) => {
                console.error('Error getting user location:', error);
                alert('Unable to retrieve your location.');
            }
        );
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}


function resizeMap() {
    const mapElement = document.getElementById('map');
    const button = document.querySelector('.map-controls button:nth-child(2)');

    if (mapExpanded) {
        mapElement.style.height = '300px';
        button.textContent = 'Make Map Bigger';
    } else {
        mapElement.style.height = '900px';
        button.textContent = 'Make Map Smaller';
    }

    mapExpanded = !mapExpanded;

    // Refresh the map size after resizing
    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 300);
}

function resetMap() {
    // Clear all markers from the markers layer
    clearMarkers();

    // Remove the routing control (route) if it exists
    if (routingControl) {
        map.removeControl(routingControl);
        routingControl = null;  // Reset routingControl so it can be recreated later
        console.log('Route has been reset: routing control removed.');
    }
}


function initializeMap(lat, lon) {
    console.log('Initializing map with center:', { lat, lon }); // Debug
    if (!map) {
        // Initialize the map if it doesn't exist
        map = L.map('map').setView([lat, lon], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Add the markers layer to the map
        markersLayer.addTo(map);
    } else {
        // Update the map center
        map.setView([lat, lon], 13);
    }
}

function calculateAndShowRoute(start, end, travelMode = 'driving-car') {
    console.log('Calculating route from:', start, 'to:', end, 'using mode:', travelMode); // Debug

    // Remove existing route
    if (routingControl) {
        console.log('Removing existing route control'); // Debug
        map.removeControl(routingControl);
    }

    // Configure travel mode profiles
    const profiles = {
        'driving-car': 'driving-car',
        'walking': 'foot-walking',
        'cycling': 'cycling-regular',
        'flying': null // Flying does not use a profile
    };

    if (travelMode === 'flying') {
        // Calculate straight-line distance using haversine formula
        const distance = haversineDistance(start, end);
        L.popup()
            .setLatLng([start.lat, start.lon])
            .setContent(`Straight-line Distance (Flying): ${distance.toFixed(2)} km`)
            .openOn(map);
    } else {
        // Initialize routing control for supported travel modes
        try {
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(start.lat, start.lon),
                    L.latLng(end.lat, end.lon)
                ],
                routeWhileDragging: true,
                router: new L.Routing.OSRMv1({
                    profile: profiles[travelMode] // Use the selected travel mode
                }),
                lineOptions: {
                    styles: [{ color: 'blue', weight: 6 }] // Highlight the route in blue
                },
                showAlternatives: false
            })
            .on('routesfound', function (e) {
                const routes = e.routes;
                const summary = routes[0].summary;

                console.log('Route found:', summary); // Debug

                // Convert time to a readable format
                const travelTime = formatTime(summary.totalTime);
                const distance = (summary.totalDistance / 1000).toFixed(2); // Convert meters to kilometers

                // Add a popup at the middle of the route with time and distance
                const midPoint = routes[0].coordinates[Math.floor(routes[0].coordinates.length / 2)];
                L.popup()
                    .setLatLng([midPoint.lat, midPoint.lng])
                    .setContent(`Travel Mode: ${travelMode}<br>Travel Time: ${travelTime}<br>Distance: ${distance} km`)
                    .openOn(map);
            })
            .on('routingerror', function (e) {
                console.error('Routing error:', e); // Debug for errors
            })
            .addTo(map);

            console.log('Routing control added to map.'); // Debug
        } catch (error) {
            console.error('Error during route calculation:', error); // Debug for unexpected errors
        }
    }
}

function formatTime(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = Math.floor(totalSeconds % 60);

    if (hours > 0) {
        return `${hours} hour${hours !== 1 ? 's' : ''}, ${minutes} minute${minutes !== 1 ? 's' : ''}`;
    } else if (minutes > 0) {
        return `${minutes} minute${minutes !== 1 ? 's' : ''}, ${seconds} second${seconds !== 1 ? 's' : ''}`;
    } else {
        return `${seconds} second${seconds !== 1 ? 's' : ''}`;
    }
}

function addTemperatureMap(lat, lon) {
    const uniqueMapId = `temp-map-${Date.now()}`; // Generate a unique map ID for temperature map

    // Create a container for the map
    const tempMapContainer = document.createElement('div');
    tempMapContainer.id = uniqueMapId;
    tempMapContainer.style.height = '300px';
    tempMapContainer.style.marginTop = '10px';
    tempMapContainer.style.border = '1px solid #ddd';
    tempMapContainer.style.borderRadius = '10px';

    // Append the temperature map container to chat
    const chatMessages = document.getElementById('chatMessages');
    const botMapMessageDiv = document.createElement('div');
    botMapMessageDiv.className = 'message bot';
    botMapMessageDiv.appendChild(tempMapContainer);
    chatMessages.appendChild(botMapMessageDiv);

    // Initialize a new Leaflet map instance for the temperature map
    const tempMap = L.map(uniqueMapId).setView([lat, lon], 6);
    L.tileLayer(
        `https://tile.openweathermap.org/map/temp_new/{z}/{x}/{y}.png?appid=YOUR_OPENWEATHER_API_KEY`,
        {
            attribution: '© OpenWeatherMap',
            maxZoom: 19,
        }
    ).addTo(tempMap);

    // Scroll to the bottom of the chat
    chatMessages.scrollTop = chatMessages.scrollHeight;
}



function sendMessage() {
    const userMessage = document.getElementById('userMessage').value;
    if (!userMessage.trim()) return;

    console.log('User message:', userMessage); // Debug

    // Display user message
    const chatMessages = document.getElementById('chatMessages');
    const userMessageDiv = document.createElement('div');
    userMessageDiv.className = 'message user';
    userMessageDiv.textContent = userMessage;
    chatMessages.appendChild(userMessageDiv);

    // Scroll to the bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;

    // Send to backend via AJAX
    fetch('http://localhost:5000/decision', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ message: userMessage }),
    })
    .then(response => {
        console.log('Response status:', response.status); // Debug response status
        return response.json();
    })
    .then(data => {
        console.log('Backend response:', data); // Debug response data

        // Display bot response
        if (data.reply) {
            const botMessageDiv = document.createElement('div');
            botMessageDiv.className = 'message bot';
            botMessageDiv.textContent = data.reply;
            chatMessages.appendChild(botMessageDiv);
        }

        // Append forecast table
        if (data.forecastTable) {
            const botTableDiv = document.createElement('div');
            botTableDiv.className = 'message bot';

            // Add table styling and content
            botTableDiv.innerHTML = `
                <div style="overflow-x: auto;">
                    <h4>5-Day Forecast:</h4>
                    ${data.forecastTable}
                </div>
            `;
            chatMessages.appendChild(botTableDiv);
        }

        // Check if map data for temperature is returned
        if (data.tempMap) {
            addTemperatureMap(data.tempMap.lat, data.tempMap.lon, data.tempMap.tileURL);
        }

        // Check if map data for places of interest is returned
        if (data.mapData && data.mapData.type === 'places') {
            const mapContainer = document.getElementById('map');
            mapContainer.style.display = 'block';

            console.log('Places data:', data.mapData); // Debug places data
            initializeMap(data.mapData.lat, data.mapData.lon);
            addMarkers(data.mapData.amenities);
        }

        // Check if route data is returned
        if (data.routeData) {
            const mapContainer = document.getElementById('map');
            mapContainer.style.display = 'block';

            console.log('Route data:', data.routeData); // Debug route data
            initializeMap(data.routeData.start.lat, data.routeData.start.lon);
            calculateAndShowRoute(data.routeData.start, data.routeData.end);
        }

        // Append the list of places, if available
        if (data.places && Array.isArray(data.places)) {
            const placesDiv = document.createElement('div');
            placesDiv.className = 'message bot';
            const placesList = data.places.map(place => `
                <b>${place.name}</b><br>
                Phone: ${place.phone}<br>
                Rating: ${place.rating}<br>
                Hours: ${Array.isArray(place.hours) ? place.hours.join(', ') : place.hours}<br>
            `).join('<br>');
            placesDiv.innerHTML = placesList;
            chatMessages.appendChild(placesDiv);
        }

        // Scroll to the bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    })
    .catch(error => {
        console.error('Error during fetch:', error); // Debug fetch errors
    });

    // Clear input
    document.getElementById('userMessage').value = '';
}
    

function clearMarkers() {
    console.log('Clearing markers'); // Debug
    // Clear all markers from the markers layer
    markersLayer.clearLayers();
}

function addMarkers(amenities) {
    clearMarkers(); // Clear existing markers

    console.log('Adding markers:', amenities); // Debug amenities data
    amenities.forEach(amenity => {
        const marker = L.marker([amenity.lat, amenity.lon])
            .bindPopup(`<b>${amenity.name}</b><br>${amenity.type}`);
        markersLayer.addLayer(marker); // Add the marker to the markers layer
    });
}

    </script>
</body>
</html>
