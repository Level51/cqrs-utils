<?php

use Elasticsearch\ClientBuilder;

class ElasticsearchPayloadStoreHandler extends PayloadStoreHandler {

    private $client;

    private $index;

    public function __construct($config) {
        $clientBuilder = ClientBuilder::create();

        /* Check for hosts configuration
         * @see https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/_configuration.html#_inline_host_configuration */
        if ($hosts = Config::inst()->get(ElasticsearchPayloadStoreHandler::class, 'hosts'))
            $clientBuilder->setHosts($hosts);

        $this->client = $clientBuilder->build();

        $this->index = $config['index'];
    }

    /**
     * @param string $key
     *
     * @return array
     */
    public function read(string $key): array {
        try {
            return $this->client->get([
                'index' => $this->index,
                'type'  => 'default',
                'id'    => $key
            ]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param string $key
     * @param array  $payload
     */
    public function write(string $key, array $payload) {
        $this->client->index([
            'index' => $this->index,
            'type'  => 'default',
            'id'    => $key,
            'body'  => $payload
        ]);
    }

    /**
     * @param string $key
     */
    public function delete(string $key) {
        $this->client->delete([
            'index' => $this->index,
            'type'  => 'default',
            'id'    => $key,
        ]);
    }

    /**
     * TODO Is there any other, more useful information we could return here?
     *
     * @param string|null $option
     *
     * @return array
     */
    public function info(string $option = null): array {
        return $this->client->indices()->getMapping([
            'index' => $this->index,
            'type'  => 'default'
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string {
        return 'elasticsearch';
    }
}