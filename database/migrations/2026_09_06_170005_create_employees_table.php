<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 32)->unique();

            // Personal
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email')->unique();
            $table->string('personal_email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('alternate_phone', 32)->nullable();
            $table->string('gender', 16)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('blood_group', 8)->nullable();
            $table->string('nationality', 64)->nullable();
            $table->string('photo_path')->nullable();

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->string('emergency_contact_relation', 64)->nullable();

            // Employment
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->unsignedBigInteger('reporting_to')->nullable()->index();
            $table->string('employment_type', 32)->default('full_time');
            $table->string('employment_status', 32)->default('probation');
            $table->date('date_of_joining');
            $table->date('date_of_confirmation')->nullable();
            $table->date('date_of_exit')->nullable();
            $table->text('exit_reason')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->default(30);

            // Bank & statutory
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 64)->nullable();
            $table->string('bank_ifsc', 32)->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('pan_number', 32)->nullable();
            $table->string('national_id', 64)->nullable();
            $table->string('pf_number', 64)->nullable();
            $table->string('esi_number', 64)->nullable();
            $table->string('uan_number', 64)->nullable();

            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index('date_of_joining');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
