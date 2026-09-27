<?php

namespace OAuth\MdwikiSql;

use OAuth\MdwikiSql\Database;

function execute_query(string $sql_query, $params = null): bool
{
    // Create a new database object
    $db = new Database('DB_NAME');

    // Execute a SQL query
    if ($params) {
        $results = $db->executequery($sql_query, $params);
    } else {
        $results = $db->executequery($sql_query);
    }

    // Destroy the database object
    $db = null;

    //---
    return $results;
};

function fetch_query(string $sql_query, $params = null): array
{
    // Create a new database object
    $db = new Database('DB_NAME');

    // Execute a SQL query
    if ($params) {
        $results = $db->fetchquery($sql_query, $params);
    } else {
        $results = $db->fetchquery($sql_query);
    }

    // Destroy the database object
    $db = null;

    return $results;
};
