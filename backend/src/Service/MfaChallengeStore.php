<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Short-lived store for the intermediate "2FA pending" token issued after a
 * successful password check. Backed by Redis (cache.app); expires in 5 min.
 *
 * The token is resolved (not consumed) on each verify attempt so a wrong code
 * can be retried; it is invalidated only once the correct code is provided.
 */
class MfaChallengeStore
{
    private const TTL = 300;
    private const PREFIX = 'mfa_';

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function create(Uuid $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $item = $this->cache->getItem(self::PREFIX.$token);
        $item->set((string) $userId)->expiresAfter(self::TTL);
        $this->cache->save($item);

        return $token;
    }

    public function resolve(string $token): ?Uuid
    {
        if ('' === $token) {
            return null;
        }
        $item = $this->cache->getItem(self::PREFIX.$token);
        if (!$item->isHit()) {
            return null;
        }
        $value = $item->get();

        return \is_string($value) && Uuid::isValid($value) ? Uuid::fromString($value) : null;
    }

    public function invalidate(string $token): void
    {
        $this->cache->deleteItem(self::PREFIX.$token);
    }
}
