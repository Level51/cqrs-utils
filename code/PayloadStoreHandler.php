<?php

abstract class PayloadStoreHandler {

    private static $instance = null;

    private function __clone() { }

    private function __wakeup() { }

    public static function inst() {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    abstract protected function __construct();

    /**
     * @param string $key
     *
     * @return array
     */
    abstract public function read(string $key): array;

    /**
     * @param string $key
     * @param array  $payload
     */
    abstract public function write(string $key, array $payload);

    /**
     * @param string|null $option
     *
     * @return array
     */
    abstract public function info(string $option = null): array;

    /**
     * @return string
     */
    abstract public function getName(): string;
}