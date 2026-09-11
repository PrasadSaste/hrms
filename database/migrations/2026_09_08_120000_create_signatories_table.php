<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who is allowed to sign for a company.
 *
 * A company had room for exactly one name, which is fine until a director is
 * away and somebody else has to sign an offer letter. This gives each company a
 * list, one of them the default, and lets the person issuing a letter choose.
 * The old single name is carried over as the first entry so nothing that was
 * already set is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('email')->nullable();
            // The specimen signature, on the private disk: an image of somebody's
            // signature is not something to leave on a public URL.
            $table->string('signature_path')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->foreignId('signatory_id')->nullable()->after('company_id')
                ->constrained('signatories')->nullOnDelete();
            // Frozen with the words: a letter keeps the signature it went out
            // with even if that person later leaves and their record is removed.
            $table->string('signature_path')->nullable()->after('signatory_designation');
        });

        $this->carryOverTheSingleName();
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signatory_id');
            $table->dropColumn('signature_path');
        });

        Schema::dropIfExists('signatories');
    }

    /** Whatever was typed on the company record becomes its first signatory. */
    protected function carryOverTheSingleName(): void
    {
        $now = now();

        $rows = DB::table('companies')
            ->whereNotNull('signatory_name')
            ->where('signatory_name', '!=', '')
            ->get(['id', 'signatory_name', 'signatory_designation']);

        foreach ($rows as $company) {
            DB::table('signatories')->insert([
                'company_id' => $company->id,
                'name' => $company->signatory_name,
                'designation' => $company->signatory_designation,
                'is_default' => true,
                'sort_order' => 0,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
