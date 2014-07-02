<?php
namespace Ninja;

use Ninja\DataCollector\DataCollectorInterface;
use Ninja\DataCollector\RedisDataCollector;
use Ninja\Exception\NotInitializedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Ninja
 *
 * @author  Jeroen Visser <jeroenvisser101@gmail.com>
 * @package Ninja
 * @license MIT, for a full license, please veiw the LICENSE file that was distributed with this source code.
 */
class Ninja
{
    /**
     * The prefix for a Redis key
     */
    const REDIS_PREFIX = 'ninja';

    /**
     * Types of blockages
     */
    const BLOCKAGE_TYPE_WHITELIST   = 'whitelisted';
    const BLOCKAGE_TYPE_BLACKLISTED = 'blacklisted';
    const BLOCKAGE_TYPE_BLOCKED     = 'blocked';
    const BLOCKAGE_TYPE_THROTTLED   = 'throttled';

    /**
     * Types of hazards
     */
    const HAZARD_TYPE_ATTACK             = 'attack';
    const HAZARD_TYPE_THROTTLE           = 'throttle';
    const HAZARD_TYPE_BLACKLIST          = 'blacklist';
    const HAZARD_TYPE_WHITELIST          = 'whitelist';
    const HAZARD_TYPE_METHOD_NOT_ALLOWED = 'method not allowed';

    /**
     * A list of types that are valid types for a hazard.
     *
     * @var string[]
     */
    private static $hazardTypes = array(
        self::HAZARD_TYPE_ATTACK,
        self::HAZARD_TYPE_THROTTLE,
        self::HAZARD_TYPE_BLACKLIST,
        self::HAZARD_TYPE_WHITELIST
    );

    /**
     * Indicates if the Ninja is ready to fight or not.
     *
     * @var boolean
     */
    private static $isReady = false;

    /**
     * The request that will be protected by the Ninja.
     *
     * @var Request
     */
    private static $request;

    /**
     * An instance of DataCollectorInterface.
     *
     * @var DataCollectorInterface
     */
    private static $dataCollector;

    /**
     * Holds all the hazards the Ninja knows about.
     *
     * @var array[]
     */
    private static $hazards = array();

    /**
     * The time the ninja started.
     *
     * @var float
     */
    private static $start = 0;

    /**
     * The amount of time the system ran.
     *
     * @var float
     */
    private static $runtime;

    /**
     * Prepares a Ninja for combat.
     *
     * @param string                      $configFile    The location of the Ninja hazard config
     * @param Request|null                $request       The Request to protect
     * @param DataCollectorInterface|null $dataCollector The RedisClient to use
     */
    public static function prepare($configFile, Request $request = null, DataCollectorInterface $dataCollector = null)
    {
        if (!self::$isReady) {
            self::$start = self::$start ?: microtime(true);

            require_once $configFile;

            self::$dataCollector = $dataCollector ?: self::$dataCollector ?: new RedisDataCollector();
            self::$request = $request ?: Request::createFromGlobals();

            self::$isReady = true;
        }
    }

    /**
     * Makes the Ninja protect the system.
     *
     * @throws NotInitializedException
     */
    public static function protect()
    {
        if (!self::$isReady) {
            throw new NotInitializedException(
                'The Ninja has not been initialized yet. Please run Ninja::prepare(...) to initialize the Ninja.'
            );
        }

        if ($blockage = self::isBlocked()) {
            self::deflect(self::$hazards[$blockage['name']]['type']);
        }

        foreach (self::$hazards as $hazard) {
            if ($result = $hazard['rule'](self::$request)) {
                // If the whitelist matches, we break the loop
                if ($hazard['type'] === self::HAZARD_TYPE_WHITELIST) {
                    break;
                }

                // If we are blacklisted, we quit immediately
                if ($hazard['type'] === self::HAZARD_TYPE_BLACKLIST) {
                    self::deflect(self::HAZARD_TYPE_BLACKLIST);
                }

                // Check if it is a bucket
                if (isset($hazard['bucket_size']) && isset($hazard['bucket_leak'])) {
                    self::hit($hazard);

                    $bucket = self::getBucket($hazard);
                    if ($bucket['hits'] == $hazard['bucket_size']) {
                        if (isset($hazard['timeout']) && $hazard['timeout'] > 0) {
                            self::block($hazard);
                        } else {
                            self::deflect($hazard['type']);
                        }
                    }
                } elseif ($hazard['type'] === self::HAZARD_TYPE_BLACKLIST) {
                    self::deflect(self::HAZARD_TYPE_BLACKLIST);
                }
            }
        }

        self::$runtime = microtime(true) - self::$start;
    }

    /**
     * Injects Ninja headers into a response.
     *
     * @param Response $response The response to inject.
     * @param bool     $blocked
     */
    public static function inject(Response &$response, $blocked = false)
    {
        self::$runtime = self::$runtime ?: microtime(true) - self::$start;

        $response->headers->add(
            array(
                'X-Ninja' => sprintf(
                    '%s by a Ninja (runtime: %sms)',
                    $blocked ? 'Blocked' : 'Protected',
                    round(self::$runtime * 1000, 2)
                )
            )
        );
    }

    /**
     * Explains the Ninja about a specific hazard and how to detect one.
     *
     * @param string   $name    The name of the hazard
     * @param string   $type    The type of the hazard
     * @param \Closure $rule    A callable which returns true if the hazard is detected
     * @param array    $options An array containing options such as bucket leak and bucket size.
     *
     * @throws \InvalidArgumentException
     */
    public static function addHazard($name, $type, \Closure $rule, array $options = array())
    {
        if (!in_array($type, self::$hazardTypes)) {
            throw new \InvalidArgumentException(sprintf('Type "%s" is not a valid hazard type.', $type));
        }

        if ((!array_key_exists('timeout', $options) || (int) $options['timeout'] <= 0) && $type === self::HAZARD_TYPE_ATTACK) {
            throw new \InvalidArgumentException(sprintf('For hazard type "%s", %s must be set.', $type, '$options[\'timeout\']'));
        }

        self::$hazards[$name] = array_merge(
            array(
                'name' => (string) $name,
                'type' => (string) $type,
                'rule' => $rule,
            ),
            $options
        );
    }

    /**
     * Blocks an incoming Request with an appropriate message.
     *
     * @param string $type The type to deflect
     *
     * @throws \InvalidArgumentException
     */
    private static function deflect($type)
    {
        switch ($type) {
            case self::HAZARD_TYPE_WHITELIST:
                return;
            case self::HAZARD_TYPE_THROTTLE:
                $response = new Response(
                    '<!doctype html><html><body><h1>429 Too many requests</h1><p>You seem to be doing a lot of requests. You\'re now cooling down.</p></body></html>',
                    Response::HTTP_TOO_MANY_REQUESTS
                );
                self::inject($response, true);
                $response->send();
                exit;

            case self::HAZARD_TYPE_ATTACK:
                $response = new Response(
                    '<!doctype html><html><body><h1>400 Bad request</h1><p>You seem to be doing malicious requests to our server. You\'re now cooling down.</p></body></html>',
                    Response::HTTP_BAD_REQUEST
                );
                self::inject($response, true);
                $response->send();
                exit;

            case self::BLOCKAGE_TYPE_BLACKLISTED:
                $response = new Response(
                    '<!doctype html><html><body><h1>403 Forbidden</h1><p>Your IP seems to be blacklisted. If you believe this is done by mistake, please contact us.</p></body></html>',
                    Response::HTTP_FORBIDDEN
                );
                self::inject($response, true);
                $response->send();
                exit;

            case self::HAZARD_TYPE_METHOD_NOT_ALLOWED:
                $response = new Response(
                    '<!doctype html><html><body><h1>403 Forbidden</h1><p>Your IP seems to be blacklisted. If you believe this is done by mistake, please contact us.</p></body></html>',
                    Response::HTTP_METHOD_NOT_ALLOWED
                );
                self::inject($response, true);
                $response->send();
                exit;
        }
    }

    /**
     * Counts a hit on a specific hazard.
     *
     * @param array $hazard The hazard that was hit
     */
    private static function hit(array $hazard)
    {
        $bucket = array('hits' => 0);

        $redisKey = static::REDIS_PREFIX . ':' . $hazard['name'] . ':' . static::$request->getClientIp();
        $lifetime = (int) $hazard['bucket_size'] / $hazard['bucket_leak'] * 2;

        // If there already is a bucket
        if (self::$dataCollector->exists($redisKey)) {
            $bucket = static::$dataCollector->fetch($redisKey);

            // Decrease the bucket size by $bucketLeak/second
            if (isset($bucket['hits']) && isset($bucket['time'])) {
                $leakage = floor($hazard['bucket_leak'] * (microtime(true) - $bucket['time']));
                $bucket['hits'] = (int) $bucket['hits'] - $leakage;

                if ($bucket['hits'] < 0) {
                    $bucket['hits'] = 0;
                }
            }
        }


        // Redefine the bucket's contents
        $bucket['time'] = microtime(true);
        $bucket['hits']++;

        // Don't overflow
        if ($bucket['hits'] > (int) $hazard['bucket_size']) {
            $bucket['hits'] = (int) $hazard['bucket_size'];
        }

        self::$dataCollector->store($redisKey, $bucket, $lifetime);
    }

    /**
     * Get the LeakyBucket for a specific hazard
     *
     * @param array $hazard The hazard to get the bucket for.
     *
     * @return array|null
     */
    private static function getBucket(array $hazard)
    {
        $redisKey = static::REDIS_PREFIX . ':' . $hazard['name'] . ':' . static::$request->getClientIp();

        if ($bucket = self::$dataCollector->fetch($redisKey)) {
            return $bucket;
        } else {
            return null;
        }
    }

    /**
     * Sets a blockage on the active client.
     *
     * @param array $hazard
     *
     * @throws \InvalidArgumentException
     */
    private static function block(array $hazard)
    {
        if (!isset($hazard['timeout'])) {
            throw new \InvalidArgumentException(sprintf('Parameter "%s" must be set to block a Request', '$hazard[\'timeout\']'));
        }

        $redisKey = static::REDIS_PREFIX . ':blockage:' . self::$request->getClientIp();

        $blockage = array(
            'name' => $hazard['name'],
            'time' => microtime(true),
        );

        self::$dataCollector->store($redisKey, $blockage, $hazard['timeout'] * 2);
    }

    /**
     * Checks if there are any active blockages, returns the active one if present.
     *
     * @return array|bool
     */
    private static function isBlocked()
    {
        $redisKey = static::REDIS_PREFIX . ':blockage:' . self::$request->getClientIp();

        // Check if there is a blockage
        if (self::$dataCollector->exists($redisKey) && $blockage = self::$dataCollector->fetch($redisKey)) {
            // Check if it has expired
            if (microtime(true) >= $blockage['time'] + self::$hazards[$blockage['name']]['timeout']) {
                self::$dataCollector->purge($redisKey);

                return false;
            } else {
                return $blockage;
            }
        } else {
            return false;
        }
    }
}
