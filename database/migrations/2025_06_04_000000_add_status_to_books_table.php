<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->after('loanable')->index();

            // Un brouillon peut être incomplet : seuls titre et auteur restent obligatoires
            $table->string('isbn')->nullable()->change();
            $table->integer('published_year')->nullable()->change();
            $table->string('genre')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
