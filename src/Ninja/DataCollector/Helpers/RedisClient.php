<?php
namespace Ninja\DataCollector\Helpers;

use Predis\Client;

/**
 * Class RedisClient
 *
 * @package Ninja
 *
 * @method get($key)
 * @method exists($key)
 * @method set($key, string $value)
 * @method del($key)
 * @method setex(string $key, int $ttl, string $value)
 * @method append($key, string $value)
 * @method ping()
 * @method incr($key)
 * @method incrby($key, int $amount)
 * @method decr($key)
 * @method decrby($key, int $amount)
 */
class RedisClient extends Client
{
}
