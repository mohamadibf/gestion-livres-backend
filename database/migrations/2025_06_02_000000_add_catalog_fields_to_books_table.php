<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('shelf_mark', 50)->nullable()->after('isbn')->index();
            $table->string('physical_condition', 20)->nullable()->default('excellent')->after('publisher');
            $table->json('keywords')->nullable()->after('physical_condition');
            $table->boolean('loanable')->default(true)->after('keywords');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['shelf_mark']);
            $table->dropColumn(['shelf_mark', 'physical_condition', 'keywords', 'loanable']);
        });
    }
};
