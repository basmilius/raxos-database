<?php
declare(strict_types=1);

use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Database\Migration\Migration;

/**
 * Example migration: creates a basic `users` table.
 */
return new class extends Migration
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.9.0
     */
    public function up(ConnectionInterface $connection): void
    {
        $connection->execute(<<<SQL
            create table if not exists `users` (
                `id` bigint unsigned not null auto_increment,
                `name` varchar(255) not null,
                `email` varchar(255) not null,
                `created_at` datetime not null default current_timestamp,
                primary key (`id`),
                unique key `users_email_unique` (`email`)
            )
            SQL
        );
    }

};
