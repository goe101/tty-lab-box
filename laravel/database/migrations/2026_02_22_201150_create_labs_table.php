<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->integer('duration')->default(60)->comment('Duration in minutes');
            $table->boolean('published')->default(false);
            $table->text('description')->nullable();
            $table->text('grader_script')->nullable()->comment('Bash script to run on evaluation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labs');
    }
};
