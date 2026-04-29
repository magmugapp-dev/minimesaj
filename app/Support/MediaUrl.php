<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUrl
{
    public static function buildUrl(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $trimmed = self::extractAbsoluteUrl(trim($value));
        if (self::looksLikeAbsoluteUrl($trimmed)) {
            return self::rewriteLoopbackUrl($trimmed);
        }
        $relativePath = ltrim($trimmed, '/');
        if ($relativePath === '') {
            return null;
        }
        return self::normalizeGeneratedUrl(Storage::disk('public')->url($relativePath));
    }

    public static function resolve(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = self::extractAbsoluteUrl(trim($value));

        if (self::looksLikeAbsoluteUrl($trimmed)) {
            return self::resolveAbsoluteUrl($trimmed);
        }

        $relativePath = ltrim($trimmed, '/');
        if (!self::storageFileExists($relativePath)) {
            return null;
        }

        return self::appendVersion(
            self::normalizeGeneratedUrl(Storage::disk('public')->url($relativePath)),
            $relativePath,
        );
    }

    private static function buildPublicUrl(string $path): string
    {
        return rtrim(self::publicBaseUrl(), '/').'/'.ltrim($path, '/');
    }

    private static function normalizeGeneratedUrl(string $url): string
    {
        $normalized = self::extractAbsoluteUrl(trim($url));

        if (self::looksLikeAbsoluteUrl($normalized)) {
            return self::rewriteLoopbackUrl($normalized);
        }

        return self::buildPublicUrl($normalized);
    }

    private static function resolveAbsoluteUrl(string $url): ?string
    {
        $rewritten = self::rewriteLoopbackUrl($url);
        $relativePath = self::storageRelativePathFromUrl($rewritten);

        if ($relativePath !== null && !self::storageFileExists($relativePath)) {
            return null;
        }

        return $relativePath === null ? $rewritten : self::appendVersion($rewritten, $relativePath);
    }

    private static function rewriteLoopbackUrl(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if (!is_string($host) || !self::isLoopbackHost($host)) {
            return $url;
        }

        $base = parse_url(self::publicBaseUrl());
        $baseHost = $base['host'] ?? null;

        if (!is_string($baseHost) || $baseHost === '') {
            return $url;
        }

        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        $scheme = $base['scheme'] ?? 'http';
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        return $scheme.'://'.$baseHost.$port.$path.$query.$fragment;
    }

    private static function extractAbsoluteUrl(string $value): string
    {
        $positions = [];

        foreach (['http://', 'https://'] as $scheme) {
            $offset = stripos($value, $scheme);

            while (is_int($offset)) {
                $positions[] = $offset;
                $offset = stripos($value, $scheme, $offset + strlen($scheme));
            }
        }

        if ($positions === []) {
            return $value;
        }

        sort($positions);
        $targetPosition = count($positions) > 1 ? end($positions) : $positions[0];

        if ($targetPosition === 0) {
            return $value;
        }

        return substr($value, $targetPosition);
    }

    private static function looksLikeAbsoluteUrl(string $value): bool
    {
        return preg_match('/^https?:\/\//i', $value) === 1;
    }

    private static function storageRelativePathFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? null;

        if (!is_string($host) || !is_string($path) || !Str::startsWith($path, '/storage/')) {
            return null;
        }

        if (!self::isApplicationHost($host)) {
            return null;
        }

        $relativePath = ltrim(Str::after($path, '/storage/'), '/');

        return $relativePath !== '' ? $relativePath : null;
    }

    private static function storageFileExists(string $path): bool
    {
        return Storage::disk('public')->exists(ltrim($path, '/'));
    }

    private static function appendVersion(string $url, string $relativePath): string
    {
        if (str_contains($url, 'v=')) {
            return $url;
        }

        try {
            $version = Storage::disk('public')->lastModified(ltrim($relativePath, '/'));
        } catch (\Throwable) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$version;
    }

    private static function publicBaseUrl(): string
    {
        $request = app()->bound('request') ? app('request') : null;

        if ($request instanceof Request && !self::isLoopbackHost($request->getHost())) {
            return $request->getSchemeAndHttpHost();
        }

        return (string) config('app.public_url', config('app.url'));
    }

    private static function isApplicationHost(string $host): bool
    {
        $normalizedHost = Str::lower($host);
        if (self::isLoopbackHost($normalizedHost)) {
            return true;
        }

        $knownHosts = [];

        foreach ([self::publicBaseUrl(), (string) config('app.url')] as $url) {
            $parsedHost = parse_url($url, PHP_URL_HOST);
            if (is_string($parsedHost) && $parsedHost !== '') {
                $knownHosts[] = Str::lower($parsedHost);
            }
        }

        $request = app()->bound('request') ? app('request') : null;
        if ($request instanceof Request && $request->getHost() !== '') {
            $knownHosts[] = Str::lower($request->getHost());
        }

        return in_array($normalizedHost, array_values(array_unique($knownHosts)), true);
    }

    private static function isLoopbackHost(?string $host): bool
    {
        if (!is_string($host) || trim($host) === '') {
            return true;
        }

        return in_array(Str::lower($host), ['127.0.0.1', 'localhost', '0.0.0.0', '::1'], true);
    }
}
