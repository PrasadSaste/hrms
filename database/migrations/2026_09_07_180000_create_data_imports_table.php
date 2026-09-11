<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every spreadsheet somebody has fed into the system.
     *
     * An import is two steps: the file is checked, and only then applied. The
     * row that is kept between those two steps is this one, which is also why
     * it survives afterwards — a migration from an old HRMS is a thing people
     * come back to and ask "what did we actually load, and when".
     */
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('status', 24)->default('checked');

            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_valid')->default(0);
            $table->unsignedInteger('rows_invalid')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);

            // Row number, the values that came in, and what was wrong with them.
            $table->json('errors')->nullable();
            $table->json('options')->nullable();
            $table->text('failure_reason')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
