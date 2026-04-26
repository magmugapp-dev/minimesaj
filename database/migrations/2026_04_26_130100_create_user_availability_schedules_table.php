<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_availability_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recurrence_type')->default('date');
            $table->date('specific_date')->nullable();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('status');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'recurrence_type', 'specific_date'], 'user_availability_date_lookup');
            $table->index(['user_id', 'recurrence_type', 'day_of_week'], 'user_availability_weekly_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_availability_schedules');
    }
};
