<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Locked
    |--------------------------------------------------------------------------
    |
    | Set INSTALL_LOCKED=true to say "this system is set up, never show the
    | installer again" without waiting for the lock file to be written. A
    | deployment that provisions from the command line should set it, so the
    | setup wizard can never be reached over the web at all.
    |
    */

    'locked' => env('INSTALL_LOCKED', false),

    /*
    |--------------------------------------------------------------------------
    | Lock file
    |--------------------------------------------------------------------------
    |
    | Written when the wizard finishes. Its presence is what closes the
    | installer, so it lives on disk rather than in the database: a restored
    | backup or a swapped database cannot reopen setup on a live server.
    |
    */

    'lock_file' => storage_path('installed.json'),

    /*
    |--------------------------------------------------------------------------
    | Minimum PHP version
    |--------------------------------------------------------------------------
    |
    | Kept beside composer.json's own constraint rather than parsed from it, so
    | the requirements screen can say the number without loading the lock file.
    |
    */

    'php' => '8.3.0',

];
