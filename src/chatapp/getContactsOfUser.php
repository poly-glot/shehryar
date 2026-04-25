<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/bootstrap.php';

Http::requirePost();

['unique_id' => $uniqueId] = Http::require(
    Http::jsonBody(),
    ['unique_id'],
);

$rows = Database::all(
    "SELECT u.user_id, u.unique_id, u.fname, u.lname, u.email, u.img, u.status,
            MAX(c.contactID) AS last_contact_id
     FROM contacts c
     INNER JOIN users u
        ON u.unique_id = IF(c.user1_ID = :me_join, c.user2_ID, c.user1_ID)
     WHERE c.user1_ID = :me_w1 OR c.user2_ID = :me_w2
     GROUP BY u.user_id
     ORDER BY last_contact_id DESC",
    ['me_join' => $uniqueId, 'me_w1' => $uniqueId, 'me_w2' => $uniqueId],
);

$results = array_map(static function (array $row): array {
    $row['imageUri'] = $row['img'];
    return $row;
}, $rows);

Http::json(['results' => $results]);
