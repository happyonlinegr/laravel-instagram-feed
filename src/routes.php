<?php

Route::get(config('instagram-feed.auth_callback_route'), 'HappyOnlineGr\InstagramFeed\AccessTokenController@handleRedirect');