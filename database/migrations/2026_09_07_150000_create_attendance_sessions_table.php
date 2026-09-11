<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A day is now made of sessions and breaks rather than a single pair of
     * times.
     *
     * People leave and come back: a site visit in the morning, a client meeting
     * after lunch. One check-in and one check-out could not describe that, so
     * each punch pair becomes a session and each break is recorded against the
     * day with what the person was doing.
     *
     * The attendances row survives as the day's summary — first in, last out,
     * worked minutes — because everything downstream (payroll, reports, the
     * monthly sheet) reads it.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);

            $table->string('check_in_ip', 45)->nullable();
            $table->string('check_in_location')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_in_accuracy')->nullable();

            $table->string('check_out_ip', 45)->nullable();
            $table->string('check_out_location')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_out_accuracy')->nullable();

            $table->string('source', 24)->default('web');
            $table->timestamps();

            $table->index(['attendance_id', 'started_at']);
            $table->index(['employee_id', 'started_at']);
        });

        Schema::create('attendance_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('reason', 32);
            $table->string('comment', 500)->nullable();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);

            $table->string('source', 24)->default('web');
            $table->timestamps();

            $table->index(['attendance_id', 'started_at']);
            $table->index(['employee_id', 'started_at']);
            $table->index('reason');
        });

        // Every day already recorded becomes a single session, so the history
        // reads the same way as anything punched from today onwards.
        DB::table('attendances')
            ->whereNotNull('check_in')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $sessions = [];

                foreach ($rows as $row) {
                    $sessions[] = [
                        'attendance_id' => $row->id,
                        'employee_id' => $row->employee_id,
                        'started_at' => $row->check_in,
                        'ended_at' => $row->check_out,
                        'duration_minutes' => $row->check_out
                            ? max(0, (int) round(
                                (strtotime($row->check_out) - strtotime($row->check_in)) / 60
                            ))
                            : 0,
                        'check_in_ip' => $row->check_in_ip,
                        'check_in_location' => $row->check_in_location,
                        'check_in_latitude' => $row->check_in_latitude,
                        'check_in_longitude' => $row->check_in_longitude,
                        'check_in_accuracy' => $row->check_in_accuracy,
                        'check_out_ip' => $row->check_out_ip,
                        'check_out_location' => $row->check_out_location,
                        'check_out_latitude' => $row->check_out_latitude,
                        'check_out_longitude' => $row->check_out_longitude,
                        'check_out_accuracy' => $row->check_out_accuracy,
                        'source' => $row->source ?? 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($sessions) {
                    DB::table('attendance_sessions')->insert($sessions);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_breaks');
        Schema::dropIfExists('attendance_sessions');
    }
};
