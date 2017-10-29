<?php
include_once('SafeMySQL.php');

class Reviews
{

    const MAX_REVIEW_PAGES = 10;

    private static $db;
    private static $reviewsUrl = 'https://itunes.apple.com/{{countryCode}}/rss/customerreviews/page={{page}}/id={{applicationId}}/sortBy=mostRecent/json';

    public static function getApplicationCountryInfo($applicationId, $countryCode)
    {

        $sql = "SELECT  a.`id`,
                        d.`id` as `developer_id`,
                        d.`name` as `developer_name`,
                        d.`rating` as `developer_rating`
                FROM `applications` as a
                LEFT JOIN `developers` as d ON a.`developer_id` = d.`id`
                WHERE a.`id` = ?i";

        $applicationArray = self::db()->getRow($sql, $applicationId);

        if (!$applicationArray)
            return [];

        $sql = "SELECT `count_fake_reviews`, `count_reviews`, `real_rating`
                FROM `application_country`
                WHERE `application_id` = ?i
                AND `country_code` = ?s";

        $acArray = self::db()->getRow($sql, $applicationId, $countryCode);

        return [
            'application' => [
                'id'                 => (isset($applicationArray['id'])) ? $applicationArray['id'] : 0,
                'count_fake_reviews' => (isset($acArray['count_fake_reviews'])) ? $acArray['count_fake_reviews'] : 0,
                'count_reviews'      => (isset($acArray['count_reviews'])) ? $acArray['count_reviews'] : 0,
                'real_rating'        => (isset($acArray['real_rating'])) ? $acArray['real_rating'] : 0,
            ],
            'developer'   => [
                'id'     => (isset($applicationArray['developer_id'])) ? $applicationArray['developer_id'] : '',
                'name'   => (isset($applicationArray['developer_name'])) ? $applicationArray['developer_name'] : '',
                'rating' => (isset($applicationArray['developer_rating'])) ? $applicationArray['developer_rating'] : '',
            ]
        ];
    }

    public static function getApplicationInfoById($applicationId, $countryCode)
    {
        $sql = "SELECT `id`, `status`
                FROM `applications`
                WHERE `id` = ?i";

        $application = self::db()->getRow($sql, $applicationId);

        if (!$application)
            $application = self::parseApplicationInfo($applicationId);

        if (!$application)
            return [];

        if ($application['status'] == 'done') {
            return self::getApplicationCountryInfo($applicationId, $countryCode);
        }


        // get all reviews for this app and set isFake
        self::saveByAppId($application['id']);

        // update the applications table and count count fake reviews
        $sql = "UPDATE
                    `application_country` as ac
                LEFT JOIN (
                    SELECT
                        `application_id`,
                        `country_code`,
                        COUNT(`id`) AS `count_reviews`,
                        SUM(IF(`isFake` = 1,1, 0)) AS `count_fake_reviews`,
                        SUM(IF(`isFake` = 0,`rating`, 0)) / SUM(IF(`isFake` = 0,1, 0)) AS `real_rating`
                    FROM `reviews`
                    WHERE `application_id` = ?i
                    GROUP BY `country_code`
                ) AS r ON r.`application_id` = ac.`application_id` AND r.`country_code` = ac.`country_code`
                SET
                    ac.`count_fake_reviews` = r.`count_fake_reviews`,
                    ac.`count_reviews` = r.`count_reviews`,
                    ac.`real_rating` = r.`real_rating`";

        self::db()->query($sql, $application['id']);

        return self::getApplicationCountryInfo($applicationId, $countryCode);
    }

    private static function parseApplicationInfo($applicationId)
    {
        $url = 'https://itunes.apple.com/lookup?id=' . $applicationId . '&entity=software&limit=200';

        $result = json_decode(file_get_contents($url), true);

        if (!isset($result['results'][0]) || $result['results'][0]['trackId'] != $applicationId) {
            return false;
        }

        $appArray = $result['results'][0];

        // Добавляем новое приложение в базу
        $applicationObjects = [
            'id'             => $appArray['trackId'],
            'status'         => 'new',
            'name'           => isset($appArray['trackName']) ? $appArray['trackName'] : '',
            'developer_name' => isset($appArray['artistName']) ? $appArray['artistName'] : '',
            'developer_id'   => isset($appArray['artistId']) ? $appArray['artistId'] : 0,
            'link'           => isset($appArray['trackViewUrl']) ? $appArray['trackViewUrl'] : '',
            'category_id'    => isset($appArray['primaryGenreId']) ? $appArray['primaryGenreId'] : 0,
            'real_rating'    => isset($appArray['averageUserRating']) ? $appArray['averageUserRating'] : '0.0',
            'release_date'   => isset($appArray['releaseDate']) ? $appArray['releaseDate'] : '',
        ];

        self::db()->query("INSERT INTO `applications` SET ?u", $applicationObjects);

        return self::db()->getRow("SELECT * FROM `applications` WHERE `id` = ?i", $applicationId);
    }

    public static function saveByAppId($applicationId)
    {
        self::db()->query("UPDATE `applications` SET `status` = 'inprogress' WHERE `id` = ?i", $applicationId);

        $countries = self::db()->getCol("SELECT code FROM countries");

        $replaceArray = ['{{applicationId}}' => $applicationId];

        foreach ($countries as $countryCode) {

            $replaceArray['{{countryCode}}'] = $countryCode;

            // Current review ids
            $reviewIds = self::db()->getCol("SELECT id FROM `reviews` WHERE `application_id` = ?i", $applicationId);

            for ($page = 1; $page <= self::MAX_REVIEW_PAGES; $page ++) {
                $replaceArray['{{page}}'] = $page;

                if (!self::parseReviewsByUrl($replaceArray, $reviewIds))
                    break;
            }
        }

        self::db()->query("UPDATE `applications` SET `status` = 'done' WHERE `id` = ?i", $applicationId);
    }

    private static function parseReviewsByUrl($replaceArray, &$reviewIds)
    {
        $url = str_replace(array_keys($replaceArray), $replaceArray, self::$reviewsUrl);

        $result = json_decode(file_get_contents($url), true);

        if (!$result)
            return false;

        if (!isset($result['feed']['entry'][0]))
            return false;

        // Save info about app
        $countryApplicationArray = self::db()->getRow("SELECT * FROM `application_country` WHERE `application_id` = ?i AND `country_code` = ?s", $replaceArray['{{applicationId}}'], $replaceArray['{{countryCode}}']);
        $saveApp = ($countryApplicationArray) ? false : true;

        // Save reviews
        foreach ($result['feed']['entry'] as $k => $entry) {

            if (!$k) {
                if ($saveApp) {
                    $objects = [
                        'application_id' => $replaceArray['{{applicationId}}'],
                        'country_code'   => $replaceArray['{{countryCode}}'],
                        'name'           => $entry['im:name']['label'],
                        'price_amount'   => $entry['im:price']['attributes']['amount'],
                        'price_currency' => $entry['im:price']['attributes']['currency'],
                    ];

                    // Save new application_country
                    self::db()->query("INSERT INTO `application_country` SET ?u", $objects);
                }
                continue;
            }

            $reviewId = $entry['id']['label'];

            // Check if review already exist
            if (in_array($reviewId, $reviewIds))
                continue;

            $authorName = $entry['author']['name']['label'];

            $authorArray = self::db()->getRow("SELECT `id`, `fake_reting` FROM `authors` WHERE `name` = ?s", $authorName);

            // Add a new author
            if (!$authorArray) {

                $sql = "INSERT INTO `authors` SET `name` = ?s";
                self::db()->query($sql, $authorName);

                $authorArray = [
                    'id'          => self::db()->insertId(),
                    'fake_reting' => 0,
                ];
            }

            // Add a new review
            $array = [
                'id'             => $reviewId,
                'application_id' => $replaceArray['{{applicationId}}'],
                'country_code'   => $replaceArray['{{countryCode}}'],
                'author_id'      => $authorArray['id'],
                'rating'         => $entry['im:rating']['label'],
                'isFake'         => ($authorArray['fake_reting']) ? 1 : 0,
            ];

            // Save new review
            self::db()->query("INSERT INTO `reviews` SET ?u", $array);

            $reviewIds [] = $reviewId;
        }

    }

    public static function db()
    {
        return (self::$db) ? self::$db : SafeMySQL::getInstance();
    }
}