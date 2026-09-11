<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a branch is, and how far from it a punch is allowed to be.
     *
     * A branch now carries the coordinates of the place itself. Every punch
     * already records where the person was standing, so the two together give
     * the distance between them — and a punch made from too far away can be
     * reported instead of quietly counting as a normal day at the office.
     *
     * The radius is optional per branch: leaving it empty falls back to the
     * organisation-wide default on the settings screen, so a site with a large
     * campus can be given more room without loosening the rule everywhere.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('geofence_radius_metres')->nullable()->after('longitude');
        });

        // The distance is frozen onto the session at the moment of the punch.
        // Recomputing it later would give a different answer the day somebody
        // corrects the branch's coordinates or widens the radius, and the
        // record of what was true at the time would be gone.
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedInteger('check_in_distance_metres')->nullable()->after('check_in_accuracy');
            $table->boolean('check_in_outside_geofence')->default(false)->after('check_in_distance_metres');

            $table->unsignedInteger('check_out_distance_metres')->nullable()->after('check_out_accuracy');
            $table->boolean('check_out_outside_geofence')->default(false)->after('check_out_distance_metres');

            $table->index('check_in_outside_geofence', 'sessions_check_in_outside_index');
            $table->index('check_out_outside_geofence', 'sessions_check_out_outside_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('sessions_check_in_outside_index');
            $table->dropIndex('sessions_check_out_outside_index');
            $table->dropColumn([
                'check_in_distance_metres', 'check_in_outside_geofence',
                'check_out_distance_metres', 'check_out_outside_geofence',
            ]);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius_metres']);
        });
    }
};
