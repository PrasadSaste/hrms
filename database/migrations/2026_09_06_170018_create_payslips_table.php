<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('slip_number', 40)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('working_days', 5, 2)->default(0);
            $table->decimal('present_days', 5, 2)->default(0);
            $table->decimal('paid_leave_days', 5, 2)->default(0);
            $table->decimal('unpaid_leave_days', 5, 2)->default(0);
            $table->decimal('holiday_days', 5, 2)->default(0);
            $table->decimal('weekend_days', 5, 2)->default(0);
            $table->decimal('absent_days', 5, 2)->default(0);
            $table->decimal('lop_days', 5, 2)->default(0);
            $table->decimal('paid_days', 5, 2)->default(0);
            $table->decimal('overtime_hours', 7, 2)->default(0);
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('gross_earnings', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->string('net_pay_words')->nullable();
            $table->string('currency', 8)->default('INR');
            $table->string('payment_mode', 24)->default('bank_transfer');
            $table->string('payment_status', 20)->default('unpaid');
            $table->date('payment_date')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('pdf_path')->nullable();
            $table->dateTime('emailed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id'], 'payslip_run_employee_unique');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
