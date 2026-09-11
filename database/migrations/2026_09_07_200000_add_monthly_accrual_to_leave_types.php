<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave that is earned month by month rather than granted for the year.
     *
     * A year's entitlement handed over on the first of January is simple, and
     * wrong for anybody who leaves in March. Most Indian employers credit a
     * fixed number of days each month, and credit less while somebody is on
     * probation — 1.5 days once confirmed, 1 day before that.
     *
     * `accrual` stays 'yearly' for every existing type, so nothing changes
     * until a type is deliberately switched over.
     */
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('accrual', 16)->default('yearly')->after('days_per_year');
            $table->decimal('days_per_month', 5, 2)->default(0)->after('accrual');
            $table->decimal('probation_days_per_month', 5, 2)->nullable()->after('days_per_month');
            // Credited at the end of the month it was earned in, unless the
            // employer prefers to hand it over on the first.
            $table->boolean('accrue_in_advance')->default(false)->after('probation_days_per_month');
            // Where to start crediting from. Switching a type to monthly in
            // September should not silently hand everybody January to August;
            // this says what to fill in and what to leave alone.
            $table->date('accrual_starts_on')->nullable()->after('accrue_in_advance');
        });

        // Every credit, so a balance can be explained and the job that writes
        // them can be run twice without paying twice.
        Schema::create('leave_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            $table->decimal('days', 6, 2);
            $table->decimal('rate', 5, 2);
            // Which rate was used, frozen: somebody confirmed in June should
            // still show as having earned the probation rate in May.
            $table->string('basis', 16)->default('permanent');
            $table->decimal('worked_fraction', 5, 4)->default(1);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year', 'month'], 'leave_accrual_month_unique');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_accruals');

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn([
                'accrual', 'days_per_month', 'probation_days_per_month',
                'accrue_in_advance', 'accrual_starts_on',
            ]);
        });
    }
};
