<?php

/**
 * Class RedisPayloadStoreHandler
 */
class RedisPayloadStoreHandler extends PayloadStoreHandler {

    private static $host = '127.0.0.1';

    private static $port = 6379;

    private static $default_db = 0;

    private $redis;

    /**
     * RedisPayloadStoreHandler constructor.
     *
     * Creates a new instance of the Redis handler and connects to the local server.
     *
     * @param $config array
     */
    public function __construct($config) {
        $this->redis = new Redis();

        $host = defined('SS_REDIS_HOST') ?
            SS_REDIS_HOST :
            Config::inst()->get(RedisPayloadStoreHandler::class, 'host');
        $port = defined('SS_REDIS_PORT') ?
            SS_REDIS_PORT :
            Config::inst()->get(RedisPayloadStoreHandler::class, 'port');
        $defaultDB = defined('SS_REDIS_DEFAULT_DB') ?
            SS_REDIS_DEFAULT_DB :
            Config::inst()->get(RedisPayloadStoreHandler::class, 'default_db');

        $this->redis->connect($host, $port);

        // Select DB from config or fall back to default
        $this->redis->select(isset($config['db']) ? $config['db'] : $defaultDB);
    }

    /**
     * Outputs a single or all options of the active Redis instance.
     *
     * @param string|null $option
     *
     * @return array
     */
    public function info(string $option = null): array {
        $info = $this->redis->info($option);

        return is_array($info) ? $info : [$option => $info];
    }

    /**
     * @param string $key
     *
     * @return array
     */
    public function read(string $key): array {
        return Convert::json2array($this->redis->get($key)) ?: [];
    }

    /**
     * Writes a NVP to the chosen Redis database.
     * The payload will be converted to JSON.
     *
     * @param string $key
     * @param array  $payload
     */
    public function write(string $key, array $payload) {
        $this->redis->set($key, Convert::array2json($payload, JSON_NUMERIC_CHECK, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Deletes a key from the store.
     *
     * @param string $key
     *
     * @return mixed|void
     */
    public function delete(string $key) {
        $this->redis->del($key);
    }

    /**
     * @return string
     */
    public function getName(): string {
        return 'redis';
    }
}
