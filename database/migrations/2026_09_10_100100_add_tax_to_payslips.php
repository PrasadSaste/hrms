<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            // Frozen onto the slip like everything else on it: the regime the
            // employee was on that month, and what was actually taken. The
            // year's projection reads these back, so a slip that has gone out
            // governs the months after it rather than being recomputed.
            $table->string('tax_regime', 8)->nullable()->after('overtime_amount');
            $table->decimal('tax_deducted', 12, 2)->default(0)->after('tax_regime');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['tax_regime', 'tax_deducted']);
        });
    }
};
