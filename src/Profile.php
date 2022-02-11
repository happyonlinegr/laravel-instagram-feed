<?php


namespace HappyOnlineGr\InstagramFeed;


use HappyOnlineGr\InstagramFeed\Exceptions\AccessTokenRequestException;
use HappyOnlineGr\InstagramFeed\Exceptions\RequestTokenException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Profile extends Model
{
    const CACHE_KEY_BASE = 'instagram_feed';
    protected $table = 'instagram_basic_profiles';

    protected $guarded = [];

    public function cacheKey()
    {
        return static::CACHE_KEY_BASE . ":" . $this->id;
    }

    public function getInstagramAuthUrl()
    {
        $instagram = App::make(Instagram::class);

        return $instagram->authUrlForProfile($this);
    }

    public function tokens()
    {
        return $this->hasMany(AccessToken::class);
    }

    /**
     * @param  $request
     * @return AccessToken
     * @throws AccessTokenRequestException
     * @throws RequestTokenException
     */
    public function requestToken($request)
    {
        if ($request->has('error') || !$request->has('code')) {
            $message = $this->getRequestErrorMessage($request);
            Log::error(sprintf("Instagram auth error: %s", $message));
            throw new RequestTokenException('Unable to get request token');
        }

        $instagram = App::make(Instagram::class);

        try {
            $tokenDetails = $instagram->requestTokenForProfile($this, $request);
            $userDetails = $instagram->fetchUserDetails($tokenDetails);
            $token = $instagram->exchangeToken($tokenDetails);
        } catch (Exception $e) {
            $message = $this->getRequestErrorMessage($request);
            Log::error(sprintf("Instagram auth error: %s", $message));
            throw new AccessTokenRequestException($e->getMessage());
        }

        return $this->setToken(array_merge(['access_token' => $token['access_token']], $userDetails));
    }

    private function getRequestErrorMessage($request)
    {
        if(!$request->has('error')) {
            return 'unknown error';
        }

        $error = $request->get('error');

        if(is_string($error)) {
            return $error;
        }

        if(is_array($error) && array_key_exists('message', $error)) {
            return $error['message'];
        }
        return 'unknown error';
    }

    public function refreshToken()
    {
        $instagram = App::make(Instagram::class);
        $token = $this->accessToken();
        $newToken = $instagram->refreshToken($token);
        $this->latestToken()->update(['access_code' => $newToken['access_token']]);
    }

    /**
     * @param $tokenDetails
     * @return AccessToken
     */
    protected function setToken($tokenDetails)
    {
        $this->tokens->each->delete();

        return AccessToken::createFromResponseArray($this, $tokenDetails);
    }

    public function hasInstagramAccess()
    {
        return !! $this->latestToken();
    }

    public function latestToken()
    {
        return $this->tokens()->latest()->first();
    }

    public function accessToken()
    {
        return $this->latestToken()->access_code ?? null;
    }

    public function clearToken()
    {
        $this->tokens->each->delete();
    }

    public function feed($limit = 20)
    {
        if(!$this->latestToken()) {
            return collect([]);
        }
        if (Cache::has($this->cacheKey())) {
            return collect(Cache::get($this->cacheKey()));
        }

        $instagram = App::make(Instagram::class);

        try {
            $feed = $instagram->fetchMedia($this->latestToken(), $limit);
            Cache::forever($this->cacheKey(), $feed);

            return collect($feed);
        } catch (Exception $e) {
            return collect([]);
        }
    }

    public function refreshFeed($limit = 20)
    {
        $instagram = App::make(Instagram::class);
        $newFeed = $instagram->fetchMedia($this->latestToken(), $limit);

        Cache::forget($this->cacheKey());
        Cache::forever($this->cacheKey(), $newFeed);

        return $this->feed();
    }

    public function viewData()
    {
        $token = $this->tokens->first();
        return [
            'name'         => $this->username,
            'username'     => $token->username ?? '',
            'fullname'     => $token->user_fullname ?? '',
            'avatar'       => $token->user_profile_picture ?? '',
            'has_auth'     => $this->hasInstagramAccess(),
            'get_auth_url' => $this->getInstagramAuthUrl()
        ];
    }
}