<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->text('description')->nullable();
            $table->decimal('days_per_year', 5, 2)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('carry_forward')->default(false);
            $table->decimal('max_carry_forward_days', 5, 2)->default(0);
            $table->unsignedSmallInteger('max_consecutive_days')->default(0);
            $table->unsignedSmallInteger('min_notice_days')->default(0);
            $table->string('applicable_gender', 16)->default('any');
            $table->unsignedSmallInteger('applicable_after_months')->default(0);
            $table->boolean('requires_attachment')->default(false);
            $table->string('color', 16)->default('#2563eb');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
