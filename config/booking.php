<?php

return [
    // Slot 1 jam. Slot terakhir dimulai pukul (close_hour - 1).
    'open_hour' => 8,
    'close_hour' => 23,

    'default_price' => 100_000,

    // Password admin untuk seeder. Wajib diisi di production.
    'admin_password' => env('SEED_ADMIN_PASSWORD'),
];