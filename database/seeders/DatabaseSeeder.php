<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $sqlPath = 'C:/Users/NazurahHafiq/Downloads/optitask.sql';

        if (!File::exists($sqlPath)) {
            $this->command->error("Backup SQL file not found at: {$sqlPath}");
            return;
        }

        $this->command->info("Reading backup SQL file...");
        $sqlContent = File::get($sqlPath);

        $this->command->info("Disabling foreign key checks and truncating old tables...");
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $tables = ['departments', 'users', 'tasks', 'notifications', 'audit_logs', 'skills', 'employee_skills'];
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        $this->command->info("Parsing and running INSERT statements from SQL file...");
        
        // Find all INSERT INTO statements (supporting multi-line blocks)
        preg_match_all('/INSERT\s+INTO\s+`[^`]+`[^;]+;/is', $sqlContent, $matches);
        
        $insertCount = 0;
        if (isset($matches[0]) && is_array($matches[0])) {
            foreach ($matches[0] as $query) {
                try {
                    DB::unprepared($query);
                    $insertCount++;
                } catch (\Exception $e) {
                    $this->command->warn("Failed to run insert: " . substr(trim($query), 0, 80) . "... Error: " . $e->getMessage());
                }
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info("Database seeded successfully with {$insertCount} insert batches from backup SQL!");
    }
}
