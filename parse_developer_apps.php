<?php

include_once('lib/SafeMySQL.php');

$db = SafeMySQL::getInstance();

/*
$sql = "SELECT id, developer_link FROM `applications` WHERE `developer_id` = 0";

$results = $db->getAll($sql);

if (!$results)
    die('no results');

foreach ($results as $application) {
    preg_match('/id[0-9]+/s', $application['developer_link'], $matches);
    $developerId = (isset($matches[0])) ? substr($matches[0], 2) : null;

    if(!$developerId)
        continue;

    $sql = "UPDATE `applications` SET `developer_id` = ?i WHERE `id` = ?i";
    $db->query($sql, $developerId, $application['id']);
}

// Добавляем разработчиков в таблицу `developers`
$sql = "insert into `developers` (id, name, link)
select a.developer_id, a.developer_name, a.developer_link
from `applications` as a
where a.developer_id != 0
and a.developer_id not in( select `id` from developers )
group by a.developer_id";

$db->query($sql);
*/

// поиск у новых разработчиков других приложений

while($developerArray = $db->getRow("SELECT id FROM developers WHERE `status` = 'new'"))
{
    $developerId = $developerArray['id'];

    $url = 'https://itunes.apple.com/lookup?id=' . $developerId . '&entity=software&limit=200';

    $result = json_decode(file_get_contents($url), true);

    if (!isset($result['results']) && !$result['results']) {
        devAsDone($developerId);
        die;
    }

    $countAdd = 0;

    foreach ($result['results'] as $k => $appArray) {

        if (!$k) {
            continue;
        }

        // Если не приложение - дальше
        if ($appArray['kind'] != 'software')
            continue;

        // проверяем наличие в базе
        $sql = "SELECT id FROM `applications` WHERE id = ?i";
        $appInDB = $db->getRow($sql, $appArray['trackId']);

        if ($appInDB)
            continue;

        // Добавляем новое приложение в базу
        $applicationObjects = [
            'id'             => $appArray['trackId'],
            'status'         => 'new',
            'name'           => isset($appArray['trackName']) ? $appArray['trackName'] : '',
            'developer_name' => isset($appArray['artistName']) ? $appArray['artistName'] : '',
            'developer_id'   => isset($appArray['artistId']) ? $appArray['artistId'] : 0,
            'link'           => isset($appArray['trackViewUrl']) ? $appArray['trackViewUrl'] : '',
            'category_id'    => isset($appArray['primaryGenreId']) ? $appArray['primaryGenreId'] : 0,
            'real_rating'    => isset($appArray['averageUserRating']) ? $appArray['averageUserRating'] : '',
            'release_date'   => isset($appArray['releaseDate']) ? $appArray['releaseDate'] : '',
        ];

        $db->query("INSERT INTO `applications` SET ?u", $applicationObjects);
        $countAdd++;
    }

    devAsDone($developerId);

    echo "done developer ID: " . $developerId . ' Add apps: ' .$countAdd ."\n";
}

function devAsDone($id)
{
    global $db;
    $db->query("UPDATE `developers` SET `status` = 'done' WHERE `id` = ?i", $id);
}