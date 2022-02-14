<?php

return [
    'client_id'           => 'YOUR INSTAGRAM CLIENT ID',
    'client_secret'       => 'YOUR INSTAGRAM CLIENT SECRET',
    'profile'      => 'YOUR PROFILE',
    'base_url' => null,
    'auth_callback_route' => 'instagram/auth/callback',
    'success_redirect_to' => 'instagram-auth-response?result=success',
    'failure_redirect_to' => 'instagram-auth-response?result=failure',
    'ignore_video' => true,
    'notify_on_error' => null,
];