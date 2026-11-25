<?php

return [
    // Explicit opt-in: the default Laravel log mailer must never receive reset tokens.
    'password_reset_mailer' => env('PASSWORD_RESET_MAILER'),
];
