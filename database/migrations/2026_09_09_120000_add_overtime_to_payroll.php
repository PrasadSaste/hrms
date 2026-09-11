<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Eligible by default, because the feature as a whole is off by
            // default: an installation that never turns overtime on is not
            // asked to tick a box for every employee first.
            $table->boolean('overtime_eligible')->default(true)->after('notice_period_days');
        });

        Schema::table('payslips', function (Blueprint $table) {
            // Frozen onto the slip beside the hours, so a payslip issued last
            // March still explains itself after the rate is changed.
            $table->decimal('overtime_rate', 12, 2)->default(0)->after('overtime_hours');
            $table->decimal('overtime_amount', 12, 2)->default(0)->after('overtime_rate');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('overtime_eligible');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['overtime_rate', 'overtime_amount']);
        });
    }
};
