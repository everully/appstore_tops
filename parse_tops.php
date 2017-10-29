<?php

include_once('lib/SafeMySQL.php');

$db = SafeMySQL::getInstance();

$categories = $db->getCol("SELECT id FROM categories");
$languages = $db->getCol("SELECT code FROM countries");
$limit = 200;
$format = 'json';
$domain = 'https://itunes.apple.com/';
$rssChannels = [
    'newapplications',
    'newfreeapplications',
    'newpaidapplications',
    'topfreeapplications',
    'topfreeipadapplications',
    'topgrossingapplications',
    'topgrossingipadapplications',
    'toppaidapplications',
    'toppaidipadapplications',
];
$countUrl = count($languages) * count($rssChannels) * count($categories);

$i = 1;
foreach ($languages as $languageCode) {
    foreach ($rssChannels as $rssChannel) {
        foreach ($categories as $categoryId) {

            $url = $domain . $languageCode . '/rss/' . $rssChannel . '/limit=' . $limit . '/genre=' . $categoryId . '/' . $format;

            echo $i . ' from ' . $countUrl . "\n";
            $i ++;
            $response = json_decode(file_get_contents($url), true);

            if (!$response || !isset($response['feed']['entry']))
                continue;

            parse($response['feed']['entry']);
        }
    }
}

echo 'done' . "\n";


function parse($array)
{
    global $db;

    foreach ($array as $appStoreApp) {

        $appStoreId = (isset($appStoreApp['id']['attributes']['im:id'])) ? $appStoreApp['id']['attributes']['im:id'] : null;
        if (!$appStoreId)
            continue;

        $result = $db->getRow("SELECT id FROM `applications` WHERE `id` = ?i", $appStoreId);

        if ($result)
            continue;

        // Developer
        $developer['id'] = 0;

        if (isset($appStoreApp['im:artist']['attributes']['href'])) {
            preg_match('/id[0-9]+/s', $appStoreApp['im:artist']['attributes']['href'], $matches);
            $developerId = (isset($matches[0])) ? substr($matches[0], 2) : null;

            $developer = $db->getRow("SELECT `id` FROM `developers` WHERE `id` = ?i", $developerId);

            if (!$developer) {
                $objects = [
                    'id'   => $developerId,
                    'name' => $appStoreApp['im:artist']['label'],
                    'link' => isset($appStoreApp['im:artist']['attributes']['href']) ? $appStoreApp['im:artist']['attributes']['href'] : '',
                ];
                $db->query("INSERT INTO `developers` SET (?u)", $objects);
            }
        }

        $applicationObjects = [
            'id'           => $appStoreApp['id']['attributes']['im:id'],
            'status'       => 'new',
            'name'         => isset($appStoreApp['im:name']['label']) ? $appStoreApp['im:name']['label'] : '',
            'developer_id' => $developer['id'],
            //'link'         => isset($appStoreApp['link']['attributes']['href']) ? $appStoreApp['link']['attributes']['href'] : '',
            'category_id'  => isset($appStoreApp['category']['attributes']['im:id']) ? $appStoreApp['category']['attributes']['im:id'] : 0,
            'release_date' => isset($appStoreApp['im:releaseDate']['label']) ? $appStoreApp['im:releaseDate']['label'] : null,
        ];

        $db->query("INSERT INTO `applications` SET ?u", $applicationObjects);

        unset($applicationObjects, $appStoreId);
    }
}


