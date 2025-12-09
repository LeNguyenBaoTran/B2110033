<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['address']) || trim($_GET['address']) == '') {
    echo json_encode([]);
    exit;
}

$address = urlencode($_GET['address']);
$access_token = 'pk.eyJ1IjoiYmFvdHJhbjIwMDMiLCJhIjoiY203OHF6cTZ1MWxkMzJrcHg3Y3h1MzVmdyJ9.I2XjMTUrPg0HG7uXdE-VNg'; //token 

$url = "https://api.mapbox.com/geocoding/v5/mapbox.places/$address.json?access_token=$access_token&limit=1&country=VN";

// Gọi API Mapbox
$response = @file_get_contents($url);

if ($response === FALSE || trim($response) == '') {
    echo json_encode([]);
    exit;
}

// Mapbox trả về JSON
$data = json_decode($response, true);

if (isset($data['features'][0]['center'])) {
    echo json_encode([
        ['lon' => $data['features'][0]['center'][0], 'lat' => $data['features'][0]['center'][1]]
    ]);
} else {
    echo json_encode([]);
}
?>
