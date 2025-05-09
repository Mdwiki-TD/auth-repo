<?php

namespace OAuth\AccessHelpsNew;
/*
Usage:
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;
use function OAuth\AccessHelpsNew\del_access_from_dbs_new;
use function OAuth\AccessHelpsNew\add_access_to_dbs_new;
*/

include_once __DIR__ . '/mdwiki_sql.php';
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/helps.php';

use function OAuth\MdwikiSql\execute_queries;
use function OAuth\MdwikiSql\fetch_queries;
use function OAuth\Helps\de_code_value;
use function OAuth\Helps\en_code_value;

$user_ids_cache = [];

function get_user_id($user)
{
    //---
    // Validate and sanitize username
    $user = trim($user);
    //---
    if (isset($user_ids_cache[$user])) {
        return $user_ids_cache[$user];
    }
    //---
    $query = "SELECT id, u_n FROM keys_new";

    $result = fetch_queries($query);

    if (!$result) {
        return null;
    }
    // ---
    foreach ($result as $row) {
        $user_id = $row['id'];
        $user_db = de_code_value($row['u_n'], 'decrypt');
        if ($user_db == $user) {
            $user_ids_cache[$user] = $user_id;
            return $user_id;
        }
    }
    // ---
    return null;
};

function add_access_to_dbs_new($user, $access_key, $access_secret)
{
    $t = [
        en_code_value(trim($user), $key_type = "decrypt"),
        en_code_value($access_key, $key_type = "decrypt"),
        en_code_value($access_secret, $key_type = "decrypt")
    ];
    //---
    $user_id = get_user_id($user);
    //---
    if ($user_id) {
        $t = [
            en_code_value($access_key, $key_type = "decrypt"),
            en_code_value($access_secret, $key_type = "decrypt"),
            $user_id,
        ];
        //---
        $query = <<<SQL
            UPDATE keys_new
            SET a_k = ?, a_s = ?, created_at = NOW()
            WHERE id = ?
        SQL;
        //---
        execute_queries($query, $t);
        return;
    } else {
        $t = [
            en_code_value(trim($user), $key_type = "decrypt"),
            en_code_value($access_key, $key_type = "decrypt"),
            en_code_value($access_secret, $key_type = "decrypt")
        ];
        //---
        $query = <<<SQL
            INSERT INTO keys_new (u_n, a_k, a_s)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                a_k = VALUES(a_k),
                a_s = VALUES(a_s),
                created_at = NOW();
        SQL;
        //---
        execute_queries($query, $t);
    }
};

function get_access_from_dbs_new($user)
{
    // Validate and sanitize username
    $user = trim($user);

    // Query to get access_key and access_secret for the user
    $query = <<<SQL
        SELECT a_k, a_s
        FROM keys_new
        WHERE id = ?
    SQL;

    $user_id = get_user_id($user);
    //---
    if (!$user_id) {
        return null;
    }

    // تنفيذ الاستعلام وتمرير اسم المستخدم كمعامل
    $result = fetch_queries($query, [$user_id]);

    // التحقق مما إذا كان قد تم العثور على نتائج

    if (!$result) {
        return null;
    }

    $result = $result[0];
    // ---
    return [
        'access_key' => de_code_value($result['a_k'], $key_type = "decrypt"),
        'access_secret' => de_code_value($result['a_s'], $key_type = "decrypt")
    ];
}

function del_access_from_dbs_new($user)
{
    $user = trim($user);

    $query = <<<SQL
        DELETE FROM keys_new WHERE id = ?
    SQL;

    $user_id = get_user_id($user);
    //---
    if (!$user_id) {
        return null;
    }
    //---
    execute_queries($query, [$user_id]);
}
