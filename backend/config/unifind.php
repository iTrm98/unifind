<?php

return [

    /*
    | Passwords for the two accounts created by the database seeder.
    */
    'admin_password' => env('ADMIN_PASSWORD'),
    'student_password' => env('STUDENT_PASSWORD'),

    /*
    | Filesystem disk for item photos: "public" (storage/app/public) locally,
    | "s3" (Cloudflare R2) in production.
    */
    'uploads_disk' => env('UPLOADS_DISK', 'public'),

    /*
    | Lifetime of an API token, in hours.
    */
    'token_hours' => 12,
];
