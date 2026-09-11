<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // The account salaries are paid from. It belongs to the company
            // rather than to global settings for the same reason the payslip's
            // letterhead does: two legal entities do not share a bank account.
            $table->string('bank_name')->nullable()->after('currency');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number', 64)->nullable()->after('bank_account_name');
            $table->string('bank_ifsc', 32)->nullable()->after('bank_account_number');
            $table->string('bank_file_format', 32)->nullable()->after('bank_ifsc');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name', 'bank_account_name', 'bank_account_number',
                'bank_ifsc', 'bank_file_format',
            ]);
        });
    }
};
