<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('type', 20)->default('earning');
            $table->string('calculation_type', 20)->default('fixed');
            $table->string('percentage_of', 20)->nullable();
            $table->decimal('default_value', 12, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_statutory')->default(false);
            $table->boolean('affects_gross')->default(true);
            $table->boolean('prorate_on_lop')->default(true);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
