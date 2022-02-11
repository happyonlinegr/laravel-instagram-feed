<?php


namespace HappyOnlineGr\InstagramFeed;


use HappyOnlineGr\InstagramFeed\Exceptions\BadTokenException;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Config;

class Instagram
{
    const REQUEST_ACCESS_TOKEN_URL = "https://api.instagram.com/oauth/access_token";
    const GRAPH_USER_INFO_FORMAT = "https://graph.instagram.com/%s?fields=id,username&access_token=%s";
    const EXCHANGE_TOKEN_FORMAT = "https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret=%s&access_token=%s";
    const REFRESH_TOKEN_FORMAT = "https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=%s";
    const MEDIA_URL_FORMAT = "https://graph.instagram.com/%s/media?fields=%s&limit=%s&access_token=%s";
    const MEDIA_FIELDS = "caption,id,media_type,media_url,thumbnail_url,permalink,children{media_type,media_url},timestamp";


    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $http;

    public function __construct($config, $client)
    {
        $this->clientId = $config["client_id"];
        $this->clientSecret = $config["client_secret"];
        $this->redirectUri = $config["auth_callback_route"];

        $this->http = $client;
    }

    public function authUrlForProfile($profile)
    {
        $clientId = $this->clientId;
        $redirect = $this->redirectUriForProfile($profile->id);

        return "https://api.instagram.com/oauth/authorize/?client_id=$clientId&redirect_uri=$redirect&scope=user_profile,user_media&response_type=code&state={$profile->id}";
    }

    private function redirectUriForProfile($profile_id)
    {
        $base = Config::get('instagram-feed.base_url') ?: Config::get('app.url');
        $base = rtrim($base, '/');

        return "{$base}/{$this->redirectUri}";
    }

    public function requestTokenForProfile($profile, $authRequest)
    {
        return $this->http->post(static::REQUEST_ACCESS_TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUriForProfile($profile->id),
            'code' => $authRequest->get('code')
        ]);
    }

    public function fetchUserDetails($accessToken)
    {
        $url = sprintf(self::GRAPH_USER_INFO_FORMAT, $accessToken['user_id'], $accessToken['access_token']);
        return $this->http->get($url);
    }

    public function exchangeToken($shortToken)
    {
        $url = sprintf(self::EXCHANGE_TOKEN_FORMAT, $this->clientSecret, $shortToken['access_token']);

        return $this->http->get($url);
    }

    public function refreshToken($token)
    {
        $url = sprintf(self::REFRESH_TOKEN_FORMAT, $token);
        return $this->http->get($url);
    }

    /**
     * @param  AccessToken  $token
     * @param  int  $limit
     * @return array
     * @throws BadTokenException
     */
    public function fetchMedia(AccessToken $token, $limit = 20)
    {
        $url = sprintf(
            self::MEDIA_URL_FORMAT,
            $token->user_id,
            self::MEDIA_FIELDS,
            $this->getPageSize($limit),
            $token->access_code
        );

        $response = $this->fetchResponseData($url);
        $collection = collect($response['data'])->reject(function ($media) {
            return $this->ignoreVideo($media);
        });

        while ($this->shouldFetchNextPage($response, $collection->count(), $limit)) {
            $response = $this->fetchResponseData($response['paging']['next']);
            $collection = $collection->merge($response['data'])
                ->reject(function ($media) {
                    return $this->ignoreVideo($media);
                });
        }

        return $collection
            ->map(function ($media) {
                return MediaParser::parseItem($media, Config::get('instagram-feed.ignore_video', false));
            })
            ->reject(function ($media) {
                return is_null($media);
            })
            ->sortByDesc('timestamp')
            ->take($limit ?? $collection->count())
            ->values()
            ->all();
    }

    private function getPageSize($limit)
    {
        return min($limit, 100);
    }

    /**
     * @param $url
     * @return mixed
     * @throws BadTokenException
     */
    private function fetchResponseData($url)
    {
        try {
            return $response = $this->http->get($url);
        } catch (ClientException $e) {
            $response = json_decode($e->getResponse()->getBody(), true);
            $errorType = $response['meta']['error_type'] ?? 'unknown';
            if ($errorType === 'OAuthAccessTokenException') {
                throw new BadTokenException('The token is invalid');
            } else {
                throw $e;
            }
        }
    }

    public function ignoreVideo($media)
    {
        if (Config::get('instagram-feed.ignore_video', false) && ($media['media_type'] == 'VIDEO')) {
            return $media['media_type'] == 'VIDEO';
        }
        return false;
    }

    private function shouldFetchNextPage($previousResponse, $currentCount, $limit)
    {
        $max = $limit ?? 100;
        return ($previousResponse['paging']['next'] ?? false) && ($currentCount <= $max);
    }
}