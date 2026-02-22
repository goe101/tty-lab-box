<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->string('node_name');
            $table->string('image')->default('images:rockylinux/9');
            $table->integer('cpu')->default(1);
            $table->integer('mem_mb')->default(1024);
            $table->integer('disk_gb')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
