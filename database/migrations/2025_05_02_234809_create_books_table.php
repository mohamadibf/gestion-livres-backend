<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('isbn')->unique();
            $table->integer('published_year');
            $table->string('genre');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->integer('page_count')->nullable();
            $table->string('language')->default('Français');
            $table->string('publisher')->nullable();
            $table->timestamps();

            $table->index('author');
            $table->index('genre');
            $table->index('published_year');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};


