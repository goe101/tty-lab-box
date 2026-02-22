<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attempt_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->string('node_name');
            $table->string('instance_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_nodes');
    }
};
