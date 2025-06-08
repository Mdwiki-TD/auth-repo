<?php

namespace OAuth\MdwikiSql;
/*
Usage:
use function OAuth\MdwikiSql\fetch_queries;
use function OAuth\MdwikiSql\execute_queries;
*/

if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};
//---
// use function Actions\Functions\test_print;
use PDO;
use PDOException;
//---
class Database
{

    private $db;
    private $host;
    private $user;
    private $password;
    private $dbname;
    private $groupByModeDisabled = false;

    public function __construct($server_name)
    {
        if ($server_name === 'localhost' || !getenv('HOME')) {
            $this->host = 'localhost:3306';
            $this->dbname = 'mdwiki';
            $this->user = 'root';
            $this->password = 'root11';
        } else {
            $ts_pw = posix_getpwuid(posix_getuid());
            $ts_mycnf = parse_ini_file($ts_pw['dir'] . "/confs/db.ini");
            $this->host = 'tools.db.svc.wikimedia.cloud';
            $this->dbname = $ts_mycnf['db'];
            $this->user = $ts_mycnf['user'];
            $this->password = $ts_mycnf['password'];
            unset($ts_mycnf, $ts_pw);
        }

        try {
            $this->db = new PDO("mysql:host=$this->host;dbname=$this->dbname", $this->user, $this->password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo $e->getMessage();
            exit();
        }
    }

    public function disableFullGroupByMode($sql_query)
    {
        // if the query contains "GROUP BY", disable ONLY_FULL_GROUP_BY, strtoupper() is for case insensitive
        if (strpos(strtoupper($sql_query), 'GROUP BY') !== false && !$this->groupByModeDisabled) {
            $this->db->exec("SET SESSION sql_mode=(SELECT REPLACE(@@SESSION.sql_mode,'ONLY_FULL_GROUP_BY',''))");
            $this->groupByModeDisabled = true;
        }
    }
    public function executequery_old($sql_query)
    {
        try {
            $this->disableFullGroupByMode($sql_query);

            $q = $this->db->prepare($sql_query);
            $q->execute();
            $result = $q->fetchAll(PDO::FETCH_ASSOC);
            return $result;
        } catch (PDOException $e) {
            echo "sql error:" . $e->getMessage() . "<br>" . $sql_query;
            return array();
        }
    }
    public function executequery($sql_query, $params = null)
    {
        try {
            $this->disableFullGroupByMode($sql_query);

            $q = $this->db->prepare($sql_query);
            if ($params) {
                $q->execute($params);
            } else {
                $q->execute();
            }

            // Check if the query starts with "SELECT"
            $query_type = strtoupper(substr(trim((string) $sql_query), 0, 6));
            if ($query_type === 'SELECT') {
                // Fetch the results if it's a SELECT query
                $result = $q->fetchAll(PDO::FETCH_ASSOC);
                return $result;
            } else {
                // Otherwise, return null
                return array();
            }
        } catch (PDOException $e) {
            echo "sql error:" . $e->getMessage() . "<br>" . $sql_query;
            return array();
        }
    }

    public function fetchquery($sql_query, $params = null)
    {
        try {
            $this->disableFullGroupByMode($sql_query);

            $q = $this->db->prepare($sql_query);
            if ($params) {
                $q->execute($params);
            } else {
                $q->execute();
            }

            // Fetch the results if it's a SELECT query
            $result = $q->fetchAll(PDO::FETCH_ASSOC);
            return $result;
        } catch (PDOException $e) {
            echo "sql error:" . $e->getMessage() . "<br>" . $sql_query;
            return array();
        }
    }

    public function __destruct()
    {
        $this->db = null;
    }
}

function execute_queries($sql_query, $params = null)
{

    // Create a new database object
    $db = new Database($_SERVER['SERVER_NAME'] ?? '');

    // Execute a SQL query
    if ($params) {
        $results = $db->executequery($sql_query, $params);
    } else {
        $results = $db->executequery($sql_query);
    }

    // Print the results
    // foreach ($results as $row) echo $row['column1'] . " " . $row['column2'] . "<br>";

    // Destroy the database object
    $db = null;

    //---
    return $results;
};
function fetch_queries($sql_query, $params = null)
{

    // Create a new database object
    $db = new Database($_SERVER['SERVER_NAME'] ?? '');

    // Execute a SQL query
    if ($params) {
        $results = $db->fetchquery($sql_query, $params);
    } else {
        $results = $db->fetchquery($sql_query);
    }

    // Print the results
    // foreach ($results as $row) echo $row['column1'] . " " . $row['column2'] . "<br>";

    // Destroy the database object
    $db = null;

    //---
    return $results;
};
