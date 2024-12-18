<?php
// Set the content type to JSON for the response
header('Content-Type: application/json');

// Read the raw POST data
$input = file_get_contents('php://input');

// Decode the JSON data from the request body
$data = json_decode($input, true);

// Validate input
if (!$data || !isset($data['bidRequest']) || !isset($data['campaigns'])) {
    echo json_encode(["error" => "Invalid input. Ensure bidRequest and campaigns are provided."]);
    exit;
}

$bidRequestJson = json_encode($data['bidRequest']);
$campaigns = $data['campaigns'];

// Function to handle the RTB bid request and select the most suitable campaign
function handleBidRequest($bidRequestJson, $campaigns) {
    // Decode the bid request JSON
    $bidRequest = json_decode($bidRequestJson, true);

    // Bid Request Handling - Parsing and Validation
    if (!$bidRequest) {
        return json_encode(["error" => "Invalid bid request JSON"]);
    }

    // Extract relevant bid request parameters - Parameter Extraction
    $device = $bidRequest['device'] ?? [];
    $geo = $device['geo'] ?? [];
    $imp = $bidRequest['imp'][0] ?? [];
    $banner = $imp['banner'] ?? [];
    $bidFloor = $imp['bidfloor'] ?? 0;

    // Initialize variables to track the best campaign
    $selectedCampaign = null;
    $highestBid = 0;

    // Iterate through campaigns to find the best match
    foreach ($campaigns as $campaign) {
        // Campaign Selection - Geographical Matching
        if (!empty($campaign['country']) && strtolower($campaign['country']) !== strtolower($geo['country'] ?? '')) {
            continue;
        }

        // Campaign Selection - Check dimension match
        $requiredDimensions = explode('x', $campaign['dimension']);
        if (
            isset($banner['w'], $banner['h']) &&
            ($banner['w'] != $requiredDimensions[0] || $banner['h'] != $requiredDimensions[1])
        ) {
            continue;
        }

        // Bid Floor Validation
        if ($campaign['price'] < $bidFloor) {
            continue;
        }

        // Highest Bid Selection
        if ($campaign['price'] > $highestBid) {
            $highestBid = $campaign['price'];
            $selectedCampaign = $campaign;
        }
    }

    // If no campaign matches, return no bid response
    if (!$selectedCampaign) {
        return json_encode(["id" => $bidRequest['id'], "seatbid" => []]);
    }

    // Generate the RTB response
    $response = [
        "id" => $bidRequest['id'],
        "seatbid" => [
            [
                "bid" => [
                    [
                        "id" => $selectedCampaign['code'],
                        "impid" => $imp['id'],
                        "price" => $highestBid,
                        "adid" => $selectedCampaign['creative_id'],
                        "adm" => $selectedCampaign['image_url'],
                        "crid" => $selectedCampaign['creative_id'],
                        "w" => $requiredDimensions[0],
                        "h" => $requiredDimensions[1],
                        "nurl" => $selectedCampaign['url']
                    ]
                ]
            ]
        ]
    ];

    return json_encode($response, JSON_PRETTY_PRINT);
}

// Process the request
$response = handleBidRequest($bidRequestJson, $campaigns);

// Return the response
echo $response;
