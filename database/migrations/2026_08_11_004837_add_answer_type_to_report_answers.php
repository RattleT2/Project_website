<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_answers', function (Blueprint $table) {
            $table->enum('answer_type', ['text', 'file', 'url'])->default('text')->after('answer_value');
        });
    }

    public function down(): void
    {
        Schema::table('report_answers', function (Blueprint $table) {
            $table->dropColumn('answer_type');
        });
    }
};
