<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('running'); // running, stopped, submitted, expired
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ends_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
