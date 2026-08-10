<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('media_type_id')->constrained('media_types')->onDelete('restrict');
            
            // File & Link
            $table->string('file_path')->nullable();
            $table->string('link_url')->nullable();
            
            // Status & Perhitungan
            $table->enum('status', ['pending', 'proses', 'disetujui'])->default('pending');
            $table->integer('total_score')->default(0);
            $table->timestamp('submitted_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};