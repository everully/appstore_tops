<?php

include_once('lib/SafeMySQL.php');

$db = SafeMySQL::getInstance();

$maxPage = 10;
$countries = $db->getCol("SELECT code FROM countries");

$i = 1;
while ($applicationArray = $db->getRow("SELECT id FROM applications WHERE `status` = 'new'")) {

    $applicationId = $applicationArray['id'];
    // Change to the process
    $db->query("UPDATE `applications` SET `status` = 'inprogress' WHERE id = ?i", $applicationId);

    foreach ($countries as $countryCode) {

        // Current review ids
        $reviewIds = $db->getCol("SELECT id FROM `reviews` WHERE `application_id` = ?i", $applicationId);

        for ($page = 1; $page <= $maxPage; $page ++) {

            if (!saveApplicationReviews($applicationId, $countryCode, $page, $reviewIds))
                break;
        }

        //sleep(1);
    }

    // Change to the done
    $db->query("UPDATE `applications` SET `status` = 'done' WHERE id = ?i", $applicationId);

    echo $i . '. Done Application ID: ' . $applicationId . "\n";
    $i ++;
}


function saveApplicationReviews($applicationId, $countryCode, $page, &$reviewIds)
{
    global $db;

    $url = 'https://itunes.apple.com/' . $countryCode . '/rss/customerreviews/page=' . $page . '/id=' . $applicationId . '/sortBy=mostRecent/json';

    $result = json_decode(file_get_contents($url), true);

    if (!$result)
        return false;

    if (!isset($result['feed']['entry'][0]))
        return false;

    // Save info about app
    $countryApplicationArray = $db->getRow("SELECT * FROM `application_country` WHERE `application_id` = ?i AND `country_code` = ?s", $applicationId, $countryCode);
    $saveApp = ($countryApplicationArray) ? false : true;

    // Save reviews
    foreach ($result['feed']['entry'] as $k => $entry) {

        if (!$k) {
            if ($saveApp) {
                $objects = [
                    'application_id' => $applicationId,
                    'country_code'   => $countryCode,
                    'name'           => $entry['im:name']['label'],
                    'price_amount'   => $entry['im:price']['attributes']['amount'],
                    'price_currency' => $entry['im:price']['attributes']['currency'],
                ];

                // Save new application_country
                $db->query("INSERT INTO `application_country` SET ?u", $objects);

                //$releaseDate = $entry['im:releaseDate']['label'];
                // Add to application ?
            }
            continue;
        }

        $reviewId = $entry['id']['label'];

        // Check if review already exist
        if (in_array($reviewId, $reviewIds))
            continue;

        $authorName = $entry['author']['name']['label'];

        // Add a new author
        $sql = "SELECT `id`, `fake_reting` FROM `authors` WHERE `name` = ?s";

        $authorArray = $db->getRow($sql, $authorName);

        if (!$authorArray) {

            $sql = "INSERT INTO `authors` SET `name` = ?s";
            $db->query($sql, $authorName);

            $authorArray = [
                'id'          => $db->insertId(),
                'fake_reting' => 0,
            ];
        }

        // Add a new review
        $array = [
            'id'             => $reviewId,
            'application_id' => $applicationId,
            'country_code'   => $countryCode,
            'author_id'      => $authorArray['id'],
            'rating'         => $entry['im:rating']['label'],
            'isFake'         => ($authorArray['fake_reting']) ? 1 : 0,
        ];

        // Save new review
        $db->query("INSERT INTO `reviews` SET ?u", $array);

        $reviewIds [] = $reviewId;
    }

    return (count($result['feed']['entry']) > 1) ? true : false;
}


echo 'done';