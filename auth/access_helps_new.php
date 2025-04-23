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

function add_access_to_dbs_new($user, $access_key, $access_secret)
{
    $t = [
        en_code_value(trim($user)),
        en_code_value($access_key),
        en_code_value($access_secret)
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
};

function get_access_from_dbs_new($user)
{
    // Validate and sanitize username
    $user = trim($user);

    // Query to get access_key and access_secret for the user
    $query = <<<SQL
        SELECT a_k, a_s
        FROM keys_new
        WHERE u_n = ?;
    SQL;

    // تنفيذ الاستعلام وتمرير اسم المستخدم كمعامل
    $result = fetch_queries($query, [en_code_value($user)]);

    // التحقق مما إذا كان قد تم العثور على نتائج

    if (!$result) {
        // إذا لم يتم العثور على نتيجة، إرجاع null أو يمكنك تخصيص رد معين
        return null;
    }

    $result = $result[0];
    // ---
    return [
        'access_key' => de_code_value($result['a_k']),
        'access_secret' => de_code_value($result['a_s'])
    ];
}

function del_access_from_dbs_new($user)
{
    $user = trim($user);

    $query = <<<SQL
        DELETE FROM keys_new WHERE u_n = ?;
    SQL;

    execute_queries($query, [en_code_value($user)]);
}
