<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the company owns, and who is holding each of it.
 *
 * Two tables rather than a `current_holder_id` column on the asset, because
 * the question worth answering is not only "where is this laptop" but "what
 * did we give this person, when, and what state did it come back in". A column
 * answers the first and forgets the moment it is overwritten; a row per
 * assignment answers both and is what a dispute is settled with.
 *
 * `assets.status` is the item's own condition — in stock, issued, in repair,
 * retired — and is derived from the assignments rather than set by hand, so
 * the two cannot disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            // The sticker on the side. Unique across the company so a tag can
            // be scanned or typed and land on exactly one thing.
            $table->string('asset_tag')->unique();
            $table->string('type');
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            // What the type's catalogue entry asked for beside the item —
            // an IMEI, a mobile number, a registration number.
            $table->json('details')->nullable();

            // An asset belongs to the entity that bought it and sits at a
            // place; both matter for who may see it.
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->date('purchased_on')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->string('condition')->default('good');
            $table->string('status')->default('in_stock');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
            $table->index(['company_id', 'branch_id']);
            $table->index('serial_number');
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->date('issued_on');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_out')->default('good');
            $table->text('issue_remarks')->nullable();

            $table->date('returned_on')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_in')->nullable();
            $table->text('return_remarks')->nullable();

            $table->timestamps();

            // The open assignment is the one with no return date, and this is
            // what every "who has it" and "what do they still hold" question
            // reads, so it is worth indexing both ways round.
            $table->index(['asset_id', 'returned_on']);
            $table->index(['employee_id', 'returned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
    }
};
