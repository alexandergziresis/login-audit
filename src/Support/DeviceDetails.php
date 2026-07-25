<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Support;

final class DeviceDetails
{
    private function __construct(
        public readonly ?string $userAgent,
        public readonly ?string $browser,
        public readonly ?string $platform,
        public readonly ?string $deviceType,
    ) {}

    public static function fromUserAgent(?string $userAgent): self
    {
        $userAgent = $userAgent !== null && trim($userAgent) !== '' ? $userAgent : null;

        return new self(
            userAgent: $userAgent,
            browser: self::detectBrowser($userAgent),
            platform: self::detectPlatform($userAgent),
            deviceType: self::detectDeviceType($userAgent),
        );
    }

    public function hash(): string
    {
        return hash('sha256', implode('|', [
            $this->browser ?? '',
            $this->platform ?? '',
            $this->deviceType ?? '',
        ]));
    }

    private static function detectBrowser(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/Edg\//i', $userAgent) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $userAgent) => 'Opera',
            (bool) preg_match('/Firefox\//i', $userAgent) => 'Firefox',
            (bool) preg_match('/CriOS\//i', $userAgent) => 'Chrome',
            (bool) preg_match('/Chrome\//i', $userAgent) => 'Chrome',
            (bool) preg_match('/Version\/.*Safari\//i', $userAgent) => 'Safari',
            (bool) preg_match('/Safari\//i', $userAgent) => 'Safari',
            (bool) preg_match('/MSIE |Trident\//i', $userAgent) => 'Internet Explorer',
            default => null,
        };
    }

    private static function detectPlatform(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/Windows/i', $userAgent) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod|iOS/i', $userAgent) => 'iOS',
            (bool) preg_match('/Mac OS X|Macintosh/i', $userAgent) => 'macOS',
            (bool) preg_match('/Android/i', $userAgent) => 'Android',
            (bool) preg_match('/CrOS/i', $userAgent) => 'ChromeOS',
            (bool) preg_match('/Linux/i', $userAgent) => 'Linux',
            default => null,
        };
    }

    private static function detectDeviceType(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/iPad|Tablet(?!.*Mobile)/i', $userAgent) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android.*Mobile/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }
}
