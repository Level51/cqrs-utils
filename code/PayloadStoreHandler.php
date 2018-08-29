<?php

abstract class PayloadStoreHandler {

    /**
     * PayloadStoreHandler constructor.
     *
     * @param array $config
     */
    abstract public function __construct($config);

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
     * @param string $key
     *
     * @return mixed
     */
    abstract public function delete(string $key);

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
