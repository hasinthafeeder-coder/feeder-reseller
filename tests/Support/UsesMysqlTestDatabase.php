<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;

trait UsesMysqlTestDatabase
{
    protected function setUpMysqlTestDatabase(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.url' => null,
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'dropshipping',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => 'admin',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        // Match Laravel's DatabaseTransactions trait: afterCommit callbacks run when
        // application code commits back to this wrapping test transaction.
        $connection = DB::connection('mysql');
        $connection->setTransactionManager(new DatabaseTransactionsManager(['mysql']));
        $connection->beginTransaction();
    }

    protected function tearDownMysqlTestDatabase(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
