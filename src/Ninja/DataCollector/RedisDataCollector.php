<?php
namespace Ninja\DataCollector;

use Ninja\DataCollector\Helpers\RedisClient;

class RedisDataCollector implements DataCollectorInterface
{
    /**
     * An instance of the RedisClient.
     *
     * @var Helpers\RedisClient
     */
    private static $redis;

    /**
     * Constructor.
     *
     * @param null|RedisClient $redis An instance of RedisClient, if unavailable this will be set automaticacaly.
     */
    public function __construct(RedisClient $redis = null)
    {
        self::$redis = self::$redis ?: $redis ?: new RedisClient();
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($key)
    {
        return unserialize(self::$redis->get($key));
    }

    /**
     * {@inheritdoc}
     */
    public function store($key, $value, $ttl)
    {
        if (isset($ttl)) {
            return self::$redis->set($key, serialize($value), 'EX', (int) $ttl);
        } else {
            return self::$redis->set($key, serialize($value));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        return self::$redis->exists($key);
    }

    /**
     * {@inheritdoc}
     */
    public function purge($key)
    {
        return self::$redis->del($key);
    }
}
