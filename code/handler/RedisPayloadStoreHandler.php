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
     * APICacheHandler constructor.
     * Creates a new instance of the Redis handler and connects to the local server.
     */
    protected function __construct() {
        $this->redis = new Redis();

        // TODO: Retry and failed connection logic
        $this->redis->pconnect(self::$host, self::$port);

        // Select default DB
        $this->redis->select(self::$default_db);
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
     * @return string
     */
    public function getName(): string {
        return 'redis';
    }
}