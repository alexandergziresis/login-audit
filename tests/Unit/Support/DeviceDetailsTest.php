<?php

declare(strict_types=1);

use Iresis\LoginAudit\Support\DeviceDetails;

it('detects a desktop Chrome on Windows user agent', function () {
    $device = DeviceDetails::fromUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    );

    expect($device->browser)->toBe('Chrome')
        ->and($device->platform)->toBe('Windows')
        ->and($device->deviceType)->toBe('desktop');
});

it('detects a mobile Safari on iOS user agent', function () {
    $device = DeviceDetails::fromUserAgent(
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    );

    expect($device->browser)->toBe('Safari')
        ->and($device->platform)->toBe('iOS')
        ->and($device->deviceType)->toBe('mobile');
});

it('detects a tablet Safari on iPadOS user agent', function () {
    $device = DeviceDetails::fromUserAgent(
        'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    );

    expect($device->deviceType)->toBe('tablet');
});

it('detects Android mobile Chrome', function () {
    $device = DeviceDetails::fromUserAgent(
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36',
    );

    expect($device->browser)->toBe('Chrome')
        ->and($device->platform)->toBe('Android')
        ->and($device->deviceType)->toBe('mobile');
});

it('detects Firefox and Edge distinctly from Chrome', function () {
    $firefox = DeviceDetails::fromUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:126.0) Gecko/20100101 Firefox/126.0');
    $edge = DeviceDetails::fromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36 Edg/125.0.0.0');

    expect($firefox->browser)->toBe('Firefox')
        ->and($firefox->platform)->toBe('Linux')
        ->and($edge->browser)->toBe('Edge');
});

it('returns nulls for an empty or missing user agent', function () {
    expect(DeviceDetails::fromUserAgent(null)->browser)->toBeNull()
        ->and(DeviceDetails::fromUserAgent('')->userAgent)->toBeNull()
        ->and(DeviceDetails::fromUserAgent('  ')->platform)->toBeNull();
});

it('hashes the same device signature consistently', function () {
    $a = DeviceDetails::fromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0.0.0 Safari/537.36');
    $b = DeviceDetails::fromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0.0.0 Safari/537.36');
    $c = DeviceDetails::fromUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:126.0) Gecko/20100101 Firefox/126.0');

    expect($a->hash())->toBe($b->hash())
        ->and($a->hash())->not->toBe($c->hash());
});
