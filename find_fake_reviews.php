<?php

include_once('lib/SafeMySQL.php');

$db = SafeMySQL::getInstance();

// Обновляем количество отзывов у каждого пользователя
$sql = "UPDATE
	`authors` as a
	LEFT JOIN (
		SELECT
			r.`author_id`,
			count(r.`id`)  AS countReviews
		FROM reviews_bk as r
		GROUP BY r.`author_id`
	) AS m ON m.`author_id` = a.id
SET
	a.`count_reviews` = m.countReviews";


for ($i = 0; $i <= 5; $i ++) {
    // Обновляем количество отзывов у каждого пользователя
    $sql = "UPDATE
				`authors` as a
			LEFT JOIN (
				SELECT
					r.`author_id`,
					count(r.`id`)  AS countReviews
				FROM reviews_bk as r
				WHERE r.rating = {$i}
				GROUP BY r.`author_id`
			) AS m ON m.`author_id` = a.id
			SET
				a.`count_{$i}_reviews` = m.countReviews";
}

// определяем фэйковых пользователей
$sql = "update `authors` set `fake_reting` = 1
where (`count_5_reviews` > 15
or `count_4_reviews` > 15 )
and `count_3_reviews` < 4
and `count_2_reviews` < 4
and `count_1_reviews` < 4";


$sql = "update `authors` set `fake_reting` = 1
where (`count_5_reviews` > 7
or `count_4_reviews` > 7 )
and `count_3_reviews` < 3
and `count_2_reviews` < 3
and `count_1_reviews` < 3";

$sql = "update `authors` set `fake_reting` = 1
where (`count_5_reviews` > 4
or `count_4_reviews` > 4 )
and `count_3_reviews` < 2
and `count_2_reviews` < 2
and `count_1_reviews` < 2";

$sql = "update `authors` set `fake_reting` = 1
where (`count_5_reviews` > 3
or `count_4_reviews` > 3 )
and `count_3_reviews` = 0
and `count_2_reviews` = 0
and `count_1_reviews` = 0";

// Определяем фэйковые отзывы
$sql = "update `reviews` as r
join `authors` as a ON r.`author_id` = a.`id`
set r.`isFake` = 1
where r.`rating` IN (4,5)
AND a.`fake_reting` = 1";

/*
 *
select
id,
count_reviews,
#SUM(`count_5_reviews` + `count_4_reviews`) as `count_good`,
SUM(`count_5_reviews` + `count_4_reviews`)*100/`count_reviews` as `count_good_pc`,
#SUM(`count_3_reviews` + `count_2_reviews` + `count_1_reviews`) as `count_bad`
#SUM(`count_3_reviews` + `count_2_reviews` + `count_1_reviews`)*100/`count_reviews` as `count_bad_pc`
from `authors`
where id != 1
and `fake_reting` = 1
group by id
 */

// Определяем количество отзывов в приложении у нас в базе
$sql = "";

// Определяем количество фэйковых отзывов в приложении у нас в базе
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
                    GROUP BY `application_id`, `country_code`
                ) AS r ON r.`application_id` = ac.`application_id` AND r.`country_code` = ac.`country_code`
                SET
                    ac.`count_fake_reviews` = r.`count_fake_reviews`,
                    ac.`count_reviews` = r.`count_reviews`,
                    ac.`real_rating` = r.`real_rating`";

// определяем девелоперов, кто проставлял фэйковые отзывы.

// в идеале еще внимательнее бы просмотреть их остальные приложения.

// приложения с фэйковыми отзывами можно чаще проверять на новые отзывы ради сбора статистики о пользователях кто оставляет эти отзывы


// author fake rating 1,2,3
// 1 - только фэйковые отзывы
// 2 - фэйковые + нормальные
// 3 - только нормальные отзывы

// application fake rating 1,2,3,4,5
// 1 - более 80% фэйковых отзывов
// 2 - более 50% фэйковых отзывов
// 3 - более 20% фэйковых отзывов
// 4 - менее 20% фэйковых отзывов
// 5 - нет фэйковых отзывов

// developer rating
// 1 - много фэйковых отзывов
// 2 - мало фэйковых отзывов
// 3 - нет фэйковых отзывов


// Отсеить пользователей у кого все отзывы меньше 4 звезд и присвоить им рейтинг 5

// Отсеить авторов у корых количество отзывов с 4-5 звезд не больше 2


echo 'done';