<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('db:truncate-all')]
#[Description('Truncate all tables except migrations table')]
class TruncateAllTables extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        Schema::disableForeignKeyConstraints();

        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_'.env('DB_DATABASE');

        foreach ($tables as $table) {
            $tableName = $table->$key;

            if ($tableName !== 'migrations') {
                DB::table($tableName)->truncate();
                $this->info("Truncated: {$tableName}");
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->info('All tables truncated successfully!');
    }
}
