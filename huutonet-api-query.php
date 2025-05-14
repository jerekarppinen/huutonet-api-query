<?php
require 'vendor/autoload.php';

function getSessionId($username, $password): string {
    $url = "https://api.huuto.net/1.1/authentication";

    $client = new GuzzleHttp\Client();

    $postData = [
        'username' => $username,
        'password' => $password
    ];
    
    $res = $client->request("POST", $url, ['form_params' => $postData]);

    $contents = $res->getBody()->getContents();

    $data = json_decode($contents, true);

    return $data["authentication"]["token"]["id"];
}

function fetchClosedItemsWrapper($session_id) {
    $client = new GuzzleHttp\Client();

    $url = "https://api.huuto.net/1.1/items";

    $res = $client->request("GET", $url,
    [
        'headers' => ["X-HuutoApiToken" => $session_id],
        'query' => [
            'category' => 110,
            'sort' => 'newest',
            'status' => 'closed',
            'limit', 500
        ]
    ]);

    $contents = $res->getBody()->getContents();

    $data = json_decode($contents, true);

    return $data;
}

function getAllItemsInDateRange($category, $fromDate, $sessionId) {
    $url = "https://api.huuto.net/1.1/items";
    $client = new GuzzleHttp\Client();
    $fromTimestamp = strtotime($fromDate);
    $allFilteredItems = [];
    $page = 1;
    $hasMoreItems = true;
    
    while ($hasMoreItems) {
        $response = $client->request("GET", $url, [
            'headers' => ["X-HuutoApiToken" => $sessionId],
            'query' => [
                'category' => $category,
                'sort' => 'newest',
                'status' => 'closed',
                'limit' => 500,
                'page' => $page
            ]
        ]);
        
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        
        // Check if we have any items left
        if (empty($data['items'])) {
            $hasMoreItems = false;
            continue;
        }
        
        // Process items on the current page
        $validItemsOnPage = [];
        $allItemsTooOld = true;
        
        foreach ($data['items'] as $item) {
            // Guard clause 1: Skip if item is too old
            $itemTimestamp = strtotime($item['listTime']);
            if ($itemTimestamp < $fromTimestamp) {
                continue;
            }
            
            $allItemsTooOld = false;
            
            // Guard clause 2: Skip if item has no bids
            if ($item['bidderCount'] === 0) {
                continue;
            }
            
            // Add more guard clauses here as needed:
            // Guard clause 3: Example - Skip if price is too low
            // if ($item['currentPrice'] < $minPrice) {
            //     continue;
            // }
            
            // Guard clause 4: Example - Skip if specific seller
            // if ($item['sellerId'] == $excludeSellerId) {
            //     continue;
            // }
            
            // Item passed all filters, add it to valid items
            $validItemsOnPage[] = $item;
        }
        
        // Add valid items to our result array
        $allFilteredItems = array_merge($allFilteredItems, $validItemsOnPage);
        
        // Stop pagination if all items on this page were too old
        if ($allItemsTooOld) {
            $hasMoreItems = false;
        } else {
            $page++;
        }
    }
    
    return $allFilteredItems;
}

$sessionId = getSessionId("jere.karppinen", "@qguWDe8tv!39I#i");

$allItems = getAllItemsInDateRange(110, '2025-04-13', $sessionId);

// $closedItemsWrapper = fetchClosedItemsWrapper($sessionId);

// $closedItems = $closedItemsWrapper['items'];

// print_r($closedItems);

// $filteredItems = [];

for($i = 0, $n = sizeof($allItems); $i < $n; $i++) {
    $currentItem = $allItems[$i];

    $title = $currentItem['title'];
    $seller = $currentItem['seller'];
    $currentPrice = $currentItem['currentPrice'];
    $altenativeLink = $currentItem['links']['alternative'];

    print_r($altenativeLink . "\r\n");
    print_r("---" . $title . "\r\n");
    print_r("---" . $seller . "\r\n");
    print_r("---" . $currentPrice . "\r\n\r\n");
}



?>