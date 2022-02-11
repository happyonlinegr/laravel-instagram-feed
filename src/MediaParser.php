<?php


namespace HappyOnlineGr\InstagramFeed;


class MediaParser
{
    public static function parseItem($media, $ignoreVideo = false)
    {

        $type = $media['media_type'];

        switch ($type) {
            case 'IMAGE':
                return static::parseAsImage($media);

            case 'VIDEO':
                return static::parseAsVideo($media, $ignoreVideo);

            case 'CAROUSEL_ALBUM':
                return static::parseAsCarousel($media, $ignoreVideo);

            default:
                return null;
        }
    }

    private static function parseAsImage($media)
    {
        return [
            'type' => 'image',
            'url' => $media['media_url'],
            'id' => $media['id'],
            'caption' => (array_key_exists('caption', $media) ? $media['caption'] : null),
            'permalink' => $media['permalink'],
            'thumbnail_url' => $media['media_url'],
            'timestamp' => $media['timestamp'] ?? '',
            'is_carousel' => false,
            'children' => [],
        ];
    }

    private static function parseAsVideo($media, $ignoreVideo)
    {
        if ($ignoreVideo) {
            return;
        }

        return [
            'type' => 'video',
            'url' => $media['media_url'],
            'id' => $media['id'],
            'caption' => (array_key_exists('caption', $media) ? $media['caption'] : null),
            'permalink' => $media['permalink'],
            'thumbnail_url' => $media['thumbnail_url'] ?? '',
            'timestamp' => $media['timestamp'] ?? '',
            'is_carousel' => false,
            'children' => [],

        ];
    }

    private static function parseAsCarousel($media, $ignoreVideo)
    {

        $children = collect($media['children']['data'])
             ->filter(function ($child) use ($ignoreVideo) {
                 return $child['media_type'] === 'IMAGE' || (!$ignoreVideo);
             });

        if (!$children) {
            return;
        }

        $use = $children->first();

        return [
            'type' => strtolower($use['media_type']),
            'url' => $use['media_url'],
            'id' => $media['id'],
            'caption' => (array_key_exists('caption', $media) ? $media['caption'] : null),
            'permalink' => $media['permalink'],
            'thumbnail_url' => $use['thumbnail_url'] ?? '',
            'timestamp' => $media['timestamp'] ?? '',
            'is_carousel' => $children->count() > 0,
            'children' => $children->map(function ($child) {
                return [
                   'type' => strtolower($child['media_type']),
                   'url' => $child['media_url'],
                   'id' => $child['id'],         
                ];
            })->values()->all(),
        ];
    }


}