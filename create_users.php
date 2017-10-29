<?php

include_once('lib/SafeMySQL.php');

$db = SafeMySQL::getInstance();
/*
$start = 954700;
$count = 100;

$i = 1;
while ($developers = $db->getAll("SELECT `id`, `name` FROM developers LIMIT ?i, ?i", $start, $count)) {
    echo 'Start: ' . $start . ' End' . ($start + $count) . "\n";

    foreach ($developers as $developer)
        $db->query("UPDATE `reviews` SET `author_id` = ?i WHERE `author_id` =0 AND `author_name` = ?s", $developer['id'], $developer['name']);
    $start = $start + $count;
}


echo 'done';

die;


echo '<pre>';
print_r($developers);
echo '<pre>';


die;
*/
echo 'in progress' . "\n";

while ($review = $db->getRow("SELECT `id`, `author_name` FROM `reviews` WHERE `author_id` = 0 LIMIT 1")) {

    $author = $db->getRow("SELECT `id` FROM `authors` WHERE `name` = ?s", $review['author_name']);

    if (!$author) {
        $db->query("INSERT INTO `authors` SET `name` = ?s", $review['author_name']);
        $author = [
            'id'   => $db->insertId(),
        ];
    }

    $db->query("UPDATE `reviews` SET `author_id` = ?i WHERE `id` = ?s", $author['id'], $review['id']);
}

echo 'done';
/*


$sql = "update `reviews` as r
        join `developers` as d ON r.author_name = d.name
        set r.`author_id` = d.id
        where r.author_id = 0";

$sql = "insert into `developers` (name)
          select r.author_name
          from `reviews` as r
          where author_id = 0
          and r.author_name not in( select `name` from developers )";

*/