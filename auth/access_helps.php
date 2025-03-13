<?php

namespace OAuth\AccessHelps;
/*
Usage:
include_once __DIR__ . '/access_helps.php';
use function OAuth\AccessHelps\get_access_from_dbs;
use function OAuth\AccessHelps\del_access_from_dbs;
use function OAuth\AccessHelps\add_access_to_dbs;
*/

include_once __DIR__ . '/mdwiki_sql.php';
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/helps.php';

use function OAuth\MdwikiSql\execute_queries;
use function OAuth\MdwikiSql\fetch_queries;
use function OAuth\Helps\de_code_value;
use function OAuth\Helps\en_code_value;

function add_access_to_dbs($user, $access_key, $access_secret)
{
    $t = [
        trim($user),
        en_code_value($access_key),
        en_code_value($access_secret)
    ];
    //---
    $query = <<<SQL
        INSERT INTO access_keys (user_name, access_key, access_secret)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            access_key = VALUES(access_key),
            access_secret = VALUES(access_secret);
    SQL;
    //---
    execute_queries($query, $t);
};

function get_access_from_dbs($user)
{
    // Validate and sanitize username
    $user = trim($user);

    // Query to get access_key and access_secret for the user
    $query = <<<SQL
        SELECT access_key, access_secret
        FROM access_keys
        WHERE user_name = ?;
    SQL;

    // تنفيذ الاستعلام وتمرير اسم المستخدم كمعامل
    $result = fetch_queries($query, [$user]);

    // التحقق مما إذا كان قد تم العثور على نتائج

    if (!$result) {
        // إذا لم يتم العثور على نتيجة، إرجاع null أو يمكنك تخصيص رد معين
        return null;
    }

    $result = $result[0];
    // ---
    return [
        'access_key' => de_code_value($result['access_key']),
        'access_secret' => de_code_value($result['access_secret'])
    ];
}

function del_access_from_dbs($user)
{
    $user = trim($user);

    $query = <<<SQL
        DELETE FROM access_keys WHERE user_name = ?;
    SQL;

    $result = execute_queries($query, [$user]);
}

function sql_add_user($user_name)
{
    $qua = <<<SQL
        INSERT INTO users (username) SELECT ?
        WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = ?)
    SQL;
    $params = [$user_name, $user_name];

    $results = execute_queries($qua, $params);

    return $results;
}
