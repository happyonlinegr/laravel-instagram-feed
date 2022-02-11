<?php

return [
    'client_id'           => 'YOUR INSTAGRAM CLIENT ID',
    'client_secret'       => 'YOUR INSTAGRAM CLIENT SECRET',
    'base_url' => null,
    'auth_callback_route' => 'instagram/auth/callback',
    'success_redirect_to' => 'instagram-auth-success',
    'failure_redirect_to' => 'instagram-auth-failure',
    'ignore_video' => false,
    'notify_on_error' => null,
];