<?php

namespace HappyOnlineGr\InstagramFeed\Mail;

use Illuminate\Mail\Mailable;

class FeedRefreshFailed extends Mailable
{
    public $profile;
    public $errorMessage;

    public function __construct($profile, $errorMessage = '')
    {
        $this->profile = $profile;
        $this->errorMessage = $errorMessage;
    }

    public function build()
    {
        return $this->subject('Unable to refresh Instagram feed for profile' . $this->profile->username)
                    ->markdown('instagram-feed::emails.feed-refresh-failed', [
                        'has_auth' => $this->profile->fresh()->hasInstagramAccess(),
                        'error_message' => $this->errorMessage,
                    ]);
    }
}