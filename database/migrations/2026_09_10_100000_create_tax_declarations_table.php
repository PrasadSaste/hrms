<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Named for the year it starts in, as Support\FinancialYear does.
            $table->unsignedSmallInteger('financial_year');
            $table->string('regime', 8)->default('new');

            // Whether the employee lives somewhere the higher house rent
            // exemption applies. It changes the arithmetic, not the wording,
            // so it sits on the declaration rather than in a note.
            $table->boolean('metro')->default(false);

            $table->string('status', 24)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            // One declaration per person per year. A correction edits it;
            // there is no second declaration for the same year to disagree.
            $table->unique(['employee_id', 'financial_year']);
        });

        Schema::create('tax_declaration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_declaration_id')->constrained()->cascadeOnDelete();

            // A key from Support\TaxDeductionSections, not a free-text label.
            $table->string('section', 32);

            $table->decimal('declared_amount', 12, 2)->default(0);

            // What HR accepted after seeing the proof. Null means nobody has
            // looked yet, which is not the same as accepting nothing — the
            // service reads the declared figure until this is set.
            $table->decimal('verified_amount', 12, 2)->nullable();

            $table->string('proof_path')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tax_declaration_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_declaration_items');
        Schema::dropIfExists('tax_declarations');
    }
};
