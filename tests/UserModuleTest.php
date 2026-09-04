<?php

declare(strict_types=1);

namespace ZaloBot\Sdk\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\Modules\UserModule;
use ZaloBot\Sdk\ZaloClient;

final class UserModuleTest extends TestCase
{
    private function client(): MockObject&ZaloClient
    {
        return $this->getMockBuilder(ZaloClient::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetProfileRejectsEmptyUserId(): void
    {
        $this->expectException(ValidationException::class);
        (new UserModule($this->client()))->getProfile('');
    }

    public function testGetProfileAndFollowersDelegateWithFilteredParams(): void
    {
        $client = $this->client();
        $client->expects($this->exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            ['ok' => true, 'result' => ['id' => 'u-1']],
            ['ok' => true, 'result' => []],
        );
        $module = new UserModule($client);

        $profile = $module->getProfile('u-1', ['fields' => 'name,avatar', 'ignored' => true]);
        $followers = $module->getFollowers(['limit' => 25, 'cursor' => 'next', 'fields' => 'name']);

        $this->assertSame('u-1', $profile['result']['id']);
        $this->assertSame([], $followers['result']);
    }

    public function testIsFollowingReturnsFalseForUserNotFound(): void
    {
        $client = $this->client();
        $client->method('get')->willThrowException(new ApiException('Not found', 2003, 404));

        $this->assertFalse((new UserModule($client))->isFollowing('u-1'));
    }

    public function testIsFollowingRethrowsUnrelatedApiErrors(): void
    {
        $client = $this->client();
        $client->method('get')->willThrowException(new ApiException('Server error', 5001, 500));

        $this->expectException(ApiException::class);
        (new UserModule($client))->isFollowing('u-1');
    }

    public function testCachedProfileAvoidsSecondNetworkRequest(): void
    {
        $client = $this->client();
        $client->expects($this->once())
            ->method('get')
            ->with('u-1', [])
            ->willReturn(['ok' => true, 'result' => ['id' => 'u-1']]);
        $module = new UserModule($client);

        $first = $module->getProfileCached('u-1');
        $second = $module->getProfileCached('u-1');

        $this->assertSame($first, $second);
        $this->assertSame(1, $module->getCacheSize());
    }

    public function testForceRefreshReplacesCachedProfile(): void
    {
        $client = $this->client();
        $client->expects($this->exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            ['ok' => true, 'result' => ['version' => 1]],
            ['ok' => true, 'result' => ['version' => 2]],
        );
        $module = new UserModule($client);

        $this->assertSame(1, $module->getProfileCached('u-1')['result']['version']);
        $this->assertSame(2, $module->getProfileCached('u-1', ['forceRefresh' => true])['result']['version']);
        $this->assertSame(1, $module->getCacheSize());
    }

    public function testClearCacheCanClearOneUserOrEverything(): void
    {
        $client = $this->client();
        $client->method('get')->willReturn(['ok' => true, 'result' => []]);
        $module = new UserModule($client);

        $module->getProfileCached('u-1');
        $module->getProfileCached('u-2');
        $this->assertSame(2, $module->getCacheSize());

        $module->clearCache('u-1');
        $this->assertSame(1, $module->getCacheSize());

        $module->clearCache();
        $this->assertSame(0, $module->getCacheSize());
    }
}

