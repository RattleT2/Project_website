<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            MediaTypeSeeder::class,
            EvaluationQuestionSeeder::class,
            ScoringRuleSeeder::class,
            AdminUserSeeder::class,
            AdditionalAdminSeeder::class,
            \Database\Seeders\DummyDataSeeder::class,
        ]);
    }
}
