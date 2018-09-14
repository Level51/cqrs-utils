<?php

use MongoDB\Client;

class MongoPayloadStoreHandler extends PayloadStoreHandler {

    private static $host = '127.0.0.1';

    private static $port = 27017;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $db;

    /**
     * @var string
     */
    private $collection;

    /**
     * MongoPayloadStoreHandler constructor.
     *
     * @param array $config
     */
    public function __construct($config) {
        $host = Config::inst()->get(MongoPayloadStoreHandler::class, 'host');
        $port = Config::inst()->get(MongoPayloadStoreHandler::class, 'port');
        $this->client = new Client("mongodb://$host:$port");

        $this->db = $config['db'];
        $this->collection = $config['collection'];
    }

    public function read(string $key): array {
        $document = $this->getCollection()->findOne([
            'key' => $key
        ]);

        return $document ? (array)$document : [];
    }

    public function write(string $key, array $payload) {
        $this->getCollection()->updateOne(
            ['key' => $key],
            ['$set' => [
                'payload' => $payload
            ]],
            ['upsert' => true]
        );
    }

    public function delete(string $key) {
        $this->getCollection()->deleteOne([
            'key' => $key
        ]);
    }

    public function info(string $option = null): array {
        return $this->client->getTypeMap();
    }

    public function getName(): string {
        return 'mongodb';
    }

    /**
     * @return \MongoDB\Collection
     */
    private function getCollection() {
        return $this->client->{$this->db}->{$this->collection};
    }
}