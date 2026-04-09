<?php

namespace OAuth\AccessHelpsNew;
/*
Usage:
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;
use function OAuth\AccessHelpsNew\del_access_from_dbs_new;
use function OAuth\AccessHelpsNew\add_access_to_dbs_new;
*/

use function OAuth\MdwikiSql\execute_queries;
use function OAuth\MdwikiSql\fetch_queries;
use function OAuth\Helps\de_code_value;
use function OAuth\Helps\en_code_value;

$query = <<<SQL
CREATE TABLE IF EXISTS `keys_new` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `u_n` text NOT NULL,
    `a_k` text NOT NULL,
    `a_s` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

function get_user_id($user)
{
    static $cache = [];
    // Validate and sanitize username
    $user = trim($user);
    if (isset($cache[$user])) return $cache[$user];

    $query = "SELECT id, u_n FROM keys_new";

    $result = fetch_queries($query);

    if (empty($result)) return null;

    foreach ($result as $row) {
        $user_id = $row['id'];
        $user_db = de_code_value($row['u_n'], 'decrypt');
        if ($user_db == $user) {
            $cache[$user] = $user_id;
            return $user_id;
        }
    }
    return null;
};

function add_access_to_dbs_new($user, $access_key, $access_secret)
{
    $t = [
        en_code_value(trim($user), "decrypt"),
        en_code_value($access_key, "decrypt"),
        en_code_value($access_secret, "decrypt")
    ];
    $user_id = get_user_id($user);
    if ($user_id) {
        $t = [
            en_code_value($access_key, "decrypt"),
            en_code_value($access_secret, "decrypt"),
            $user_id,
        ];

        $query = <<<SQL
            UPDATE keys_new
            SET a_k = ?, a_s = ?, created_at = NOW()
            WHERE id = ?
        SQL;

        execute_queries($query, $t);
        return;
    } else {
        $t = [
            en_code_value(trim($user), "decrypt"),
            en_code_value($access_key, "decrypt"),
            en_code_value($access_secret, "decrypt")
        ];

        $query = <<<SQL
            INSERT INTO keys_new (u_n, a_k, a_s)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                a_k = VALUES(a_k),
                a_s = VALUES(a_s),
                created_at = NOW();
        SQL;

        execute_queries($query, $t);
    }
}

function get_access_from_dbs_new($user)
{
    $user_id = get_user_id(trim($user));
    if (!$user_id) return null;

    $result = fetch_queries(
        "SELECT a_k, a_s FROM keys_new WHERE id = ? LIMIT 1",
        [$user_id]
    );

    // check the results
    if (empty($result)) return null;

    return [
        'access_key'    => de_code_value($result[0]['a_k'], 'decrypt'),
        'access_secret' => de_code_value($result[0]['a_s'], 'decrypt'),
    ];
}

function del_access_from_dbs_new($user)
{
    $user_id = get_user_id(trim($user));
    if (!$user_id) return;

    execute_queries("DELETE FROM keys_new WHERE id = ?", [$user_id]);
}
