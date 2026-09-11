<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // The punch-out was supplied by the system, not made by a person.
            // Kept on the session rather than the day because a day can hold
            // several, and only the abandoned one is in question.
            $table->timestamp('auto_closed_at')->nullable()->after('ended_at');
        });

        Schema::table('attendances', function (Blueprint $table) {
            // Surfaced on the day so a roster, a calendar and a report can
            // show it without loading every session behind every row.
            $table->boolean('needs_correction')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn('auto_closed_at');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('needs_correction');
        });
    }
};
