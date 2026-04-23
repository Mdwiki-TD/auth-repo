<?php

namespace OAuth\AccessHelps;
/*
Usage:
use function OAuth\AccessHelps\get_access_from_db;
use function OAuth\AccessHelps\del_access_from_db;
use function OAuth\AccessHelps\add_access_to_db;
*/

use function OAuth\MdwikiSql\execute_query;
use function OAuth\MdwikiSql\fetch_query;
use function OAuth\Helps\decode_value;
use function OAuth\Helps\encode_value;

function add_access_to_db($user, $access_key, $access_secret)
{
    $user = trim($user);
    //---
    $t = [
        $user,
        hash('sha256', $user),
        encode_value($access_key),
        encode_value($access_secret)
    ];
    //---
    // SET user_name_hash = SHA2(user_name, 256)
    //---
    $query = <<<SQL
        INSERT INTO access_keys (user_name, user_name_hash, access_key, access_secret)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            access_key = VALUES(access_key),
            access_secret = VALUES(access_secret),
            updated_at = NOW();
    SQL;
    //---
    execute_query($query, $t);
};

function get_access_from_db($user)
{
    $user = trim($user);

    $query = <<<SQL
        SELECT access_key, access_secret
        FROM access_keys
        WHERE user_name = ? or user_name_hash = ?;
    SQL;

    $result = fetch_query($query, [$user, hash('sha256', $user)]);

    if ($result) {
        return [
            'access_key' => decode_value($result[0]['access_key']),
            'access_secret' => decode_value($result[0]['access_secret'])
        ];
    }
    return [];
}

function del_access_from_db($user)
{
    $user = trim($user);

    $query = <<<SQL
        DELETE FROM access_keys WHERE user_name = ? or user_name_hash = ?;
    SQL;

    execute_query($query, [$user, hash('sha256', $user)], "access_keys");
}

function sql_add_user($user_name)
{
    $qua = <<<SQL
        INSERT INTO users (username) SELECT ?
        WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = ?)
    SQL;
    $params = [$user_name, $user_name];

    $results = execute_query($qua, $params);

    return $results;
}
