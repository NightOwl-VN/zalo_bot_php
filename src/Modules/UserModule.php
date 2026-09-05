<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

namespace ZaloBot\Sdk\Modules;

use ZaloBot\Sdk\ZaloClient;
use ZaloBot\Sdk\Exceptions\ValidationException;
use ZaloBot\Sdk\Exceptions\ApiException;

/**
 * User module - Get user information and follower management.
 */
class UserModule
{
    private array $cache = [];
    private array $cacheOrder = [];

    public const CACHE_MAX_SIZE = 1000;
    public const CACHE_TTL = 300; // 5 minutes (seconds)

    public function __construct(protected ZaloClient $client)
    {
    }

    /**
     * Get user profile information.
     *
     * @param array{fields?:string} $options
     */
    public function getProfile(string $userId, array $options = []): array
    {
        if (trim($userId) === '') {
            throw new ValidationException('userId is required', 'userId');
        }

        $params = [];
        if (!empty($options['fields'])) {
            $params['fields'] = $options['fields'];
        }

        return $this->client->get($userId, $params);
    }

    /**
     * Get list of followers.
     *
     * @param array{limit?:int,cursor?:string,fields?:string} $params
     */
    public function getFollowers(array $params = []): array
    {
        $query = ['limit' => $params['limit'] ?? 50];
        if (!empty($params['cursor'])) {
            $query['cursor'] = $params['cursor'];
        }
        if (!empty($params['fields'])) {
            $query['fields'] = $params['fields'];
        }

        return $this->client->get('me/followers', $query);
    }

    /**
     * Check if a user is following the bot.
     */
    public function isFollowing(string $userId): bool
    {
        try {
            $this->getProfile($userId);
            return true;
        } catch (ApiException $e) {
            if ($e->getApiErrorCode() === 2003) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get user profile with caching (TTL 5 minutes, LRU eviction).
     */
    public function getProfileCached(string $userId, array $options = []): array
    {
        $this->evictExpired();
        $fields = $options['fields'] ?? 'default';
        $cacheKey = "user:{$userId}:{$fields}";

        if (empty($options['forceRefresh']) && isset($this->cache[$cacheKey])) {
            // A cache hit is also an access: move the key to the MRU end.
            $this->cacheOrder = array_values(array_filter(
                $this->cacheOrder,
                static fn (string $key): bool => $key !== $cacheKey,
            ));
            $this->cacheOrder[] = $cacheKey;
            return $this->cache[$cacheKey]['data'];
        }

        $profile = $this->getProfile($userId, $options);
        $this->cache[$cacheKey] = [
            'data' => $profile,
            'timestamp' => time(),
        ];
        $this->cacheOrder[] = $cacheKey;
        $this->evictOldest();

        return $profile;
    }

    /**
     * Clear user cache.
     */
    public function clearCache(?string $userId = null): void
    {
        if ($userId === null) {
            $this->cache = [];
            $this->cacheOrder = [];
            return;
        }

        $prefix = "user:{$userId}:";
        foreach ($this->cacheOrder as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->cache[$key]);
            }
        }
        $this->cacheOrder = array_values(array_filter(
            $this->cacheOrder,
            fn (string $k) => isset($this->cache[$k]),
        ));
    }

    public function getCacheSize(): int
    {
        return count($this->cache);
    }

    private function evictExpired(): void
    {
        $now = time();
        foreach ($this->cache as $key => $entry) {
            if ($now - $entry['timestamp'] >= self::CACHE_TTL) {
                unset($this->cache[$key]);
                $idx = array_search($key, $this->cacheOrder, true);
                if ($idx !== false) {
                    unset($this->cacheOrder[$idx]);
                }
            }
        }
        $this->cacheOrder = array_values($this->cacheOrder);
    }

    private function evictOldest(): void
    {
        while (count($this->cacheOrder) > self::CACHE_MAX_SIZE) {
            $oldestKey = array_shift($this->cacheOrder);
            unset($this->cache[$oldestKey]);
        }
    }
}
