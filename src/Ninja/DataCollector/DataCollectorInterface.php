<?php
namespace Ninja\DataCollector;

interface DataCollectorInterface
{
    /**
     * Retrieves the value of $key from the datastorage.
     *
     * @param string $key The key to the datastorage
     *
     * @return mixed
     */
    public function fetch($key);

    /**
     * Saves data in the datastorage.
     *
     * @param string $key   The key that refers to a place in the datastorage
     * @param mixed  $value Value that will be written to the datastorage
     * @param int    $ttl   Time to live in seconds
     *
     * @return bool
     */
    public function store($key, $value, $ttl);

    /**
     * Checks if a given $key exists in the datastorage.
     *
     * @param string $key The key that refers to a place in the datastorage
     *
     * @return bool
     */
    public function exists($key);

    /**
     * Deletes data from the datastorage.
     *
     * @param string $key The key that refers to a place in the datastorage
     *
     * @return bool
     */
    public function purge($key);
}
