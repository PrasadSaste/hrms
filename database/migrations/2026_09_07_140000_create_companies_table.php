<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The legal entities that actually pay people.
     *
     * A group runs several companies — Beyond Sure Private Limited, Shrigoda
     * Insurance Brokers Limited — and an employee is employed by exactly one of
     * them. The entity decides whose name, registration numbers and logo appear
     * on the salary slip, so payroll is run per company rather than across the
     * whole group.
     *
     * A branch is where someone sits; a company is who employs them. The two
     * are independent: one office can hold staff of two companies.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('code', 12)->unique();

            // Statutory identity: these print on the salary slip and differ per
            // entity, which is the whole reason for separating them.
            $table->string('registration_number', 64)->nullable();
            $table->string('tax_id', 64)->nullable();
            $table->string('gst_number', 64)->nullable();
            $table->string('pf_number', 64)->nullable();
            $table->string('esi_number', 64)->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 96)->nullable();
            $table->string('state', 96)->nullable();
            $table->string('country', 96)->nullable();
            $table->string('postal_code', 24)->nullable();

            $table->string('logo_path')->nullable();
            $table->string('currency', 8)->default('INR');
            $table->string('payslip_prefix', 12)->default('PS');
            $table->string('signatory_name')->nullable();
            $table->string('signatory_designation')->nullable();

            $table->string('status', 16)->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('status');
        });

        // The organisation already has details in settings, so the first
        // company inherits them rather than starting blank.
        $companyId = DB::table('companies')->insertGetId([
            'name' => Setting::get('company_name', config('app.name')) ?: 'Head company',
            'code' => 'CO1',
            'tax_id' => Setting::get('company_tax_id'),
            'email' => Setting::get('company_email'),
            'phone' => Setting::get('company_phone'),
            'website' => Setting::get('company_website'),
            'address_line1' => Setting::get('company_address'),
            'logo_path' => Setting::get('company_logo'),
            'currency' => Setting::get('currency', 'INR'),
            'payslip_prefix' => 'PS',
            'status' => 'active',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')
                ->constrained('companies')->nullOnDelete();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('reference')
                ->constrained('companies')->nullOnDelete();
        });

        Schema::table('payslips', function (Blueprint $table) {
            // Kept on the slip itself: transferring somebody later must not
            // rewrite who paid them last March.
            $table->foreignId('company_id')->nullable()->after('employee_id')
                ->constrained('companies')->nullOnDelete();
        });

        DB::table('employees')->update(['company_id' => $companyId]);
        DB::table('payrolls')->update(['company_id' => $companyId]);
        DB::table('payslips')->update(['company_id' => $companyId]);

        // A period is now unique per company as well as per branch, so two
        // entities can both run March without colliding.
        //
        // MySQL was leaning on the old composite index to support the branch
        // foreign key, and refuses to drop an index a key still needs, so the
        // branch gets an index of its own first.
        Schema::table('payrolls', function (Blueprint $table) {
            $table->index('branch_id', 'payrolls_branch_id_index');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique('payroll_period_unique');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->unique(['company_id', 'branch_id', 'month', 'year'], 'payroll_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique('payroll_period_unique');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->unique(['branch_id', 'month', 'year'], 'payroll_period_unique');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex('payrolls_branch_id_index');
        });

        foreach (['employees', 'payrolls', 'payslips'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }

        Schema::dropIfExists('companies');
    }
};
