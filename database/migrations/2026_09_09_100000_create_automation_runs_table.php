<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the system did on its own, and when.
 *
 * A scheduled job that fails at two in the morning is otherwise invisible
 * until somebody notices the consequence weeks later. One row per run means
 * the automations screen can answer the only questions that matter: did it
 * run, did it work, and what did it do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            // running, succeeded, failed. A row left as running is a job that
            // died without finishing, which is itself worth seeing.
            $table->string('status', 16)->default('running');
            // What it did, in a sentence: "Credited 26 people 1.5 days."
            $table->string('summary', 255)->nullable();
            $table->unsignedInteger('affected')->default(0);
            // The failure, when there was one.
            $table->text('error')->nullable();
            // Whether a person pressed Run now, rather than the scheduler.
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['key', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
