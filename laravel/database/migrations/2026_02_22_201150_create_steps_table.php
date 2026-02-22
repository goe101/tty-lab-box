<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->string('title');
            $table->text('markdown');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steps');
    }
};
