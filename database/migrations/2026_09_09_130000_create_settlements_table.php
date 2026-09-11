<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What somebody is owed, or owes, on their last day.
 *
 * The figures are **frozen onto the row** when the settlement is issued, in
 * the same way a payslip keeps its own numbers and a letter keeps its own
 * words. A settlement is the arithmetic behind a payment somebody has already
 * received; recomputing it later from today's salary structure, today's leave
 * balance and today's gratuity cap would answer a different question and
 * quietly contradict a statement that is already in somebody's hands.
 *
 * The bases are frozen too — last drawn basic, years of service, the days the
 * per-day rate was divided by — because the line "Gratuity ₹1,73,076" is
 * indefensible without them, and it is exactly the line people query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->date('date_of_joining');
            $table->date('last_working_day');
            $table->string('exit_reason')->nullable();

            // The bases, frozen. Every computed line is checkable against these
            // without needing anything else to still be true.
            $table->decimal('last_drawn_basic', 12, 2)->default(0);
            $table->decimal('last_drawn_gross', 12, 2)->default(0);
            $table->decimal('service_years', 6, 2)->default(0);
            $table->unsignedSmallInteger('per_day_divisor')->default(26);
            $table->decimal('encashable_days', 6, 2)->default(0);
            $table->unsignedSmallInteger('notice_required_days')->default(0);
            $table->unsignedSmallInteger('notice_served_days')->default(0);
            $table->boolean('notice_waived')->default(false);
            $table->boolean('gratuity_eligible')->default(false);

            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            // Signed on purpose: a settlement can come out negative, and
            // storing that as a positive "recoverable" would lose the sign
            // every report then has to guess at.
            $table->decimal('net_payable', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');

            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->date('settled_on')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'last_working_day']);
            $table->index('employee_id');
        });

        Schema::create('settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type');
            $table->decimal('amount', 12, 2)->default(0);
            // How this number was arrived at, in words, kept with the number.
            $table->string('basis')->nullable();
            $table->boolean('is_computed')->default(false);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['settlement_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_lines');
        Schema::dropIfExists('settlements');
    }
};
