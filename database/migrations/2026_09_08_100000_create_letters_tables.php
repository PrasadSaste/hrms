<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The letters a company issues, and the wording they are written from.
     *
     * Two tables for two different lifetimes. A template is edited over the
     * years and only ever describes what the *next* letter will say. An issued
     * letter carries the words that were actually sent, frozen at the moment it
     * was issued — an experience letter from 2024 must not change because
     * somebody reworded the template in 2026.
     */
    public function up(): void
    {
        // An administrator's changes to one letter type. Anything null falls
        // back to the wording shipped in LetterTypes.
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->unique();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // The entity whose letterhead it went out on. Moving somebody to
            // another company later must not rewrite who signed for them.
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 64);
            $table->string('reference', 64)->unique();
            $table->string('subject');
            $table->text('body');

            // What the placeholders were filled with, and what the issuer
            // typed, kept so a letter can be explained or reissued.
            $table->json('data')->nullable();
            $table->json('fields')->nullable();

            $table->string('signatory_name')->nullable();
            $table->string('signatory_designation')->nullable();

            $table->date('issued_on');
            $table->date('effective_from')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'type']);
            $table->index(['type', 'issued_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_templates');
    }
};
