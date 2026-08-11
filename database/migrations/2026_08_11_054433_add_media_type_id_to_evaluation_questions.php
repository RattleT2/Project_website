<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_questions', function (Blueprint $table) {
            $table->foreignId('media_type_id')->nullable()->after('id')->constrained('media_types')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_questions', function (Blueprint $table) {
            $table->dropForeign(['media_type_id']);
            $table->dropColumn('media_type_id');
        });
    }
};
