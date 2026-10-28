<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        // Exceptions par lecteur : null = valeur globale, 0 = illimité (pour la limite)
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('loan_limit')->nullable()->after('role');
            $table->unsignedSmallInteger('loan_duration_days')->nullable()->after('loan_limit');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['loan_limit', 'loan_duration_days']);
        });

        Schema::dropIfExists('settings');
    }
};
