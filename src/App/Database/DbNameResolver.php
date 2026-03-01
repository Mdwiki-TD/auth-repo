<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Table-to-database mapping helper.
 *
 * Determines which database name environment variable to use based on the
 * table being queried, matching the legacy routing exactly.
 */
final class DbNameResolver
{
    /** Tables that live in the "new" database. */
    private const NEW_DB_TABLES = [
        'missing',
        'missing_by_qids',
        'exists_by_qids',
        'publish_reports',
        'login_attempts',
        'logins',
        'publish_reports_stats',
        'all_qids_titles',
    ];

    /**
     * @return string Environment variable name containing the database name.
     */
    public static function resolve(?string $tableName): string
    {
        if ($tableName !== null && in_array($tableName, self::NEW_DB_TABLES, true)) {
            return 'DB_NAME_NEW';
        }
        return 'DB_NAME';
    }
}
