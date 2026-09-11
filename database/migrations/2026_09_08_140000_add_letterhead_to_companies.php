<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each company's own document pad.
 *
 * A salary slip and a letter go out on the letterhead of the entity that
 * employs the person. Until now that meant its logo and address; a real pad
 * also carries a line at the foot — the registered office, a CIN — and a
 * watermark, so a photocopied payslip is visibly a copy of something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // A print logo, kept apart from the one on screen: the interface
            // wants something small on a coloured bar, a document wants
            // something that survives being printed in black and white.
            $table->string('letterhead_logo_path')->nullable()->after('logo_path');
            $table->string('letterhead_footer', 500)->nullable()->after('letterhead_logo_path');
            $table->boolean('watermark_enabled')->default(true)->after('letterhead_footer');
            $table->string('watermark_text', 60)->nullable()->after('watermark_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'letterhead_logo_path',
                'letterhead_footer',
                'watermark_enabled',
                'watermark_text',
            ]);
        });
    }
};
