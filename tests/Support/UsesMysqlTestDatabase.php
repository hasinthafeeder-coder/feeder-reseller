<?php

namespace Tests\Support;

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
        DB::beginTransaction();
    }

    protected function tearDownMysqlTestDatabase(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
