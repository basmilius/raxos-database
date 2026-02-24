<?php
declare(strict_types=1);

namespace Raxos\Database\Migration;

use Raxos\Contract\Database\{ConnectionInterface, DatabaseExceptionInterface};
use Raxos\Database\Migration\Error\MigrationException;
use Throwable;
use function array_column;
use function basename;
use function glob;
use function in_array;
use function max;
use function preg_match;
use function sort;

/**
 * Class Migrator
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Migration
 * @since 1.9.0
 */
class Migrator
{

    /**
     * Migrator constructor.
     *
     * @param ConnectionInterface $connection
     * @param string $table
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'migrations'
    )
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->table)) {
            throw new MigrationException($this->table, "Invalid migrations table name '{$this->table}'.");
        }
    }

    /**
     * Runs all pending migrations found in the given path.
     *
     * @param string $path
     *
     * @return string[]
     * @throws MigrationException
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    public function run(string $path): array
    {
        $this->ensureTable();

        $ran = $this->ran();
        $batch = count($ran) > 0 ? max(array_column($ran, 'batch')) + 1 : 1;
        $executed = [];

        foreach ($this->pending($path) as $name => $migration) {
            try {
                $migration->up($this->connection);
            } catch (DatabaseExceptionInterface $err) {
                throw new MigrationException($name, "Migration '{$name}' failed: {$err->getMessage()}", $err);
            }

            $this->record($name, $batch);
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Returns the pending migrations from the given path.
     *
     * @param string $path
     *
     * @return Migration[]
     * @throws MigrationException
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    public function pending(string $path): array
    {
        $this->ensureTable();

        $ran = array_column($this->ran(), 'migration');
        $files = glob("{$path}/*.php") ?: [];
        sort($files);

        $pending = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = require $file;

            if (!$migration instanceof Migration) {
                throw new MigrationException($name, "Migration file '{$name}' must return an instance of " . Migration::class . '.');
            }

            $pending[$name] = $migration;
        }

        return $pending;
    }

    /**
     * Ensures the migrations table exists.
     *
     * @return void
     * @throws MigrationException
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    private function ensureTable(): void
    {
        try {
            if ($this->connection->tableExists($this->table)) {
                return;
            }

            $this->connection->execute(<<<SQL
                create table `{$this->table}` (
                    `id` bigint unsigned not null auto_increment,
                    `migration` varchar(255) not null,
                    `batch` int not null,
                    primary key (`id`)
                )
                SQL
            );
        } catch (Throwable $err) {
            throw new MigrationException($this->table, "Failed to create migrations table '{$this->table}'.", $err);
        }
    }

    /**
     * Returns all recorded migrations from the database.
     *
     * @return array{migration: string, batch: int}[]
     * @throws MigrationException
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    private function ran(): array
    {
        try {
            return $this->connection
                ->query(prepared: false)
                ->select(['migration', 'batch'])
                ->from($this->table)
                ->array();
        } catch (DatabaseExceptionInterface $err) {
            throw new MigrationException($this->table, "Failed to retrieve ran migrations from '{$this->table}'.", $err);
        }
    }

    /**
     * Records the given migration as run in the database.
     *
     * @param string $migration
     * @param int $batch
     *
     * @return void
     * @throws MigrationException
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    private function record(string $migration, int $batch): void
    {
        try {
            $this->connection
                ->query()
                ->insertIntoValues($this->table, [
                    'migration' => $migration,
                    'batch' => $batch
                ])
                ->run();
        } catch (DatabaseExceptionInterface $err) {
            throw new MigrationException($migration, "Failed to record migration '{$migration}'.", $err);
        }
    }

}
