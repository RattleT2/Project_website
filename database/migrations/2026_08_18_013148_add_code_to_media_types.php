<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_types', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('media_types', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
