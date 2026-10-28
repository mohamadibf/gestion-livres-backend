<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('borrowed_at');
            $table->dateTime('due_at');
            $table->dateTime('returned_at')->nullable();
            $table->unsignedTinyInteger('renewals_count')->default(0);
            // Renseignée uniquement tant que le prêt est en cours : l'unicité garantit,
            // au niveau de la base, un seul prêt actif par livre (les NULL ne se contredisent pas).
            $table->unsignedBigInteger('active_book_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
