<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Background verification: one case per employee, with one row per thing
     * they were asked to produce.
     *
     * The case carries the conversation — invited, submitted, sent back, cleared
     * — and the items carry the evidence. Both are kept after onboarding,
     * because "who checked this and when" is the question that gets asked a
     * year later.
     */
    public function up(): void
    {
        Schema::create('background_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('invited');
            $table->date('due_on')->nullable();

            $table->timestamp('invited_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_remarks')->nullable();

            // Set when the check clears and the employee is taken on properly.
            $table->timestamp('onboarded_at')->nullable();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('background_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('background_check_id')->constrained()->cascadeOnDelete();
            $table->string('requirement', 32);
            $table->string('status', 24)->default('pending');
            $table->boolean('is_required')->default(true);

            // The uploaded evidence. Stored on the private disk: these are
            // passports and bank details, never served from public storage.
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamp('uploaded_at')->nullable();

            // Whatever the requirement asked to be typed alongside the file.
            $table->json('details')->nullable();

            $table->text('remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['background_check_id', 'requirement']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('background_check_items');
        Schema::dropIfExists('background_checks');
    }
};
