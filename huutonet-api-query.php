<?php
require 'vendor/autoload.php';

$env = parse_ini_file('.env');

define("VHS", 110);
define("DVD", value: 87);
define("BLURAY", value: 845);
define("JULISTEET", value: 213);

function getConstantNameByValue($value): ?string {
    $constants = get_defined_constants(true);
    
    // We're looking in user-defined constants
    if (isset($constants['user'])) {
        foreach ($constants['user'] as $name => $constValue) {
            if ($constValue === $value) {
                return $name;
            }
        }
    }
    
    return null; // Return null if no matching constant is found
}

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

function getAllItemsInDateRange(
    $category,
    $maxDate,
    $sessionId,
    $minPrice = null,
    $maxPrice = null
    ): array {
    $url = "https://api.huuto.net/1.1/items";
    $client = new GuzzleHttp\Client();
    $fromTimestamp = strtotime($maxDate);
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
            // Skip if item is too old
            $itemTimestamp = strtotime($item['listTime']);
            if ($itemTimestamp < $fromTimestamp) {
                continue;
            }
            
            $allItemsTooOld = false;
            
            // Skip if item has no bids
            if ($item['bidderCount'] === 0) {
                continue;
            }
            
            if ($minPrice !== null && $item['currentPrice'] < $minPrice) {
                continue;
            }

            if ($maxPrice !== null && $item['currentPrice'] > $maxPrice) {
                continue;
            }
            
            
            // Item passed all filters, add it to valid items
            $item['category'] = $category;
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

$sessionId = getSessionId($env['username'], $env['password']);

$categories = [VHS, DVD, BLURAY, JULISTEET];

$allItems = [];

$start_time = microtime(true);

$maxDate = '2025-04-13';
$minPrice = 100;
$maxPrice = 1000;

foreach($categories as $category) {
    $categoryItems = getAllItemsInDateRange($category, $maxDate, $sessionId, $minPrice, $maxPrice);
    $allItems = array_merge($allItems, $categoryItems);
}

$end_time = microtime(true);

$execution_time = ($end_time - $start_time);

// Convert seconds to hours, minutes, and seconds
$hours = floor($execution_time / 3600);
$minutes = floor((fmod($execution_time, 3600)) / 60); // Use fmod() for float modulo
$seconds = fmod($execution_time, 60); // Use fmod() here as well


$n = sizeof($allItems);

$file = "results.csv";

$header = "title;price;category;closingTime;seller;link" . PHP_EOL;

file_put_contents($file, $header);

for($i = 0; $i < $n; $i++) {
    $currentItem = $allItems[$i];

    $title = $currentItem['title'];
    $seller = $currentItem['seller'];
    $currentPrice = $currentItem['currentPrice'];
    $category = $currentItem['category'];
    $closingTime = $currentItem['closingTime'];
    $altenativeLink = $currentItem['links']['alternative'];

    $date = new DateTime($closingTime);
    $categoryName = getConstantNameByValue($category);


    file_put_contents(
        $file, $title . ";" . $currentPrice . ";" . $categoryName . ";" . $date->format('Y.m.d') . ";" . $seller . ";" . $altenativeLink . PHP_EOL,
        FILE_APPEND
    );

    print_r($altenativeLink . "\r\n");
    print_r("closed:" . $closingTime . "\r\n");
    print_r("category:" . $category . "\r\n");
    print_r("title:" . $title . "\r\n");
    print_r("seller:" . $seller . "\r\n");
    print_r("price:" . $currentPrice . "\r\n\r\n");

    
}

echo "\r\n\r\nScript Execution Time = " . $hours . " hours, " . $minutes . " minutes, " . number_format($seconds, 2) . " seconds";

?>