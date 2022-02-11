<?php

namespace HappyOnlineGr\InstagramFeed;

use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{
    protected $guarded = [];

    protected $table = 'instagram_feed_tokens';

    /**
     * @param $profile
     * @param $tokenDetails
     * @return $this
     */
    public static function createFromResponseArray($profile, $tokenDetails)
    {
        return static::create([
            'profile_id' => $profile->id,
            'access_code' => $tokenDetails['access_token'],
            'user_id' => $tokenDetails['id'],
            'username' => $tokenDetails['username'],
            'user_fullname' => 'NOT_AVAILABLE',
            'user_profile_picture' => 'NOT_AVAILABLE',
        ]);
    }
}