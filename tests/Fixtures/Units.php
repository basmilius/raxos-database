<?php
declare(strict_types=1);

namespace RaxosTests\Database;

use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Contract\Database\Query\QueryValueInterface;
use Raxos\Database\Connection\{MariaDb, MySql, SQLite};
use Raxos\Database\Db;
use Raxos\Database\Query\Query;
use ReflectionProperty;

function unitConnection(string $driver = 'sqlite'): ConnectionInterface
{
    if ($driver === 'sqlite') {
        $connection = SQLite::createFromInMemory();
    } else {
        $variable = $driver === 'mysql' ? 'RAXOS_MYSQL_DSN' : 'RAXOS_MARIADB_DSN';
        $dsn = getenv($variable);
        if (!$dsn) {
            test()->markTestSkipped("{$variable} is not configured.");
        }
        $class = $driver === 'mysql' ? MySql::class : MariaDb::class;
        $connection = new $class($dsn, getenv('RAXOS_MYSQL_USER') ?: 'root', getenv('RAXOS_MYSQL_PASSWORD') ?: '');
    }
    $connection->connect();
    Db::register($connection);

    return $connection;
}

function unitQuery(): Query
{
    return unitConnection()->query();
}

function compiledExpression(QueryValueInterface $expression): array
{
    $query = unitQuery();
    $query->compile($expression);

    return [preg_replace('/\s+/', ' ', trim($query->toSql())), array_values(new ReflectionProperty(Query::class, 'params')->getValue($query))];
}

function unitOrm(): ConnectionInterface
{
    $connection = unitConnection();
    $connection->execute('CREATE TABLE parents (id INTEGER PRIMARY KEY AUTOINCREMENT, external_key INTEGER, name TEXT)');
    $connection->execute('CREATE TABLE children (id INTEGER PRIMARY KEY, parent_id INTEGER, parent_key INTEGER)');
    $connection->execute('CREATE TABLE soft_items (id INTEGER PRIMARY KEY, group_id INTEGER, deleted_at TEXT)');
    $connection->execute("INSERT INTO parents VALUES (1,100,'first'),(100,999,'other')");
    $connection->execute('INSERT INTO children VALUES (1,1,100),(2,1,100)');
    $connection->execute("INSERT INTO soft_items VALUES (1,1,NULL),(2,2,'2026-01-01'),(3,2,NULL)");

    return $connection;
}
