# CQRS 4 SilverStripe
Utility Module for implementing the CQRS workflow within a SilverStripe application.

:warning: **This is work in progress and not ready to use/play around with at all! We are just testing this in a project and will deliver a stable build as soon as we have reached a sufficient amount of added value.**

## Read Payload Manifests
Read Payload Manifests defines the data to be stored on a class basis.

Example Manifest (`$read_payload`):

```php
class MyObject extends DataObject {

    private static $db = [
        'Title' => 'Varchar',
        'Teaser => 'Text',
        'Content => 'HTMLText'
    ];
    
    private static $has_many = [
        'Categories' => Category::class
    ];
    
    private static $read_payload = [
        'ID',                         // required
        'Title'      => 'CoolTitle'   // required, maps to "CoolTitle()"
        'Teaser'     => false,        // not required
        'Content'    => true,         // required (non-shorthand)
        'Author',                     // required (method value)
        'Categories' => true,         // required recursive relation table
        'Tags'       => [             // not required, maps to "getMyTags()"
            'required' => false,
            'mapping' => 'getMyTags'
        ]
    ];
    
    public function CoolTitle() { ... }
    
    public function Author() { ... }
    
    public function getMyTags() { ... }
}
```

## Handler

### Redis
---

#### Additional Requirements
- ext-redis: PHP Redis extension

#### Example Config

```yaml
CustomDataObject:
  extensions:
    - CQRSExtension('ID', ['store' => 'RedisPayloadStoreHandler', 'db' => 1])

# Optional, defaults to the values below
RedisPayloadStoreHandler:
  host: 127.0.0.1
  port: 6379
  default_db: 0	
```
### MongoDB
---

#### Additional Requirements
- ext-mongodb: [PHP MongoDB extension](http://php.net/manual/en/mongodb.installation.php)
- mongodb/mongodb: [MongoDB PHP Library](https://docs.mongodb.com/php-library/current/)

#### Example Config

```yaml
CustomDataObject:
  extensions:
    - CQRSExtension('ID', ['store' => 'MongoPayloadStoreHandler', 'db' => 'DB_NAME', 'collection' => 'COLLECTION_NAME'])

# Optional, defaults to the values below
MongoPayloadStoreHandler:
  host: 127.0.0.1
  port: 27017	
```

### Elasticsearch
---

#### Additional Requirements
- elasticsearch/elasticsearch: [Elasticsearch PHP Client](https://github.com/elastic/elasticsearch-php)

#### Example Config

```yaml
CustomDataObject:
  extensions:
    - CQRSExtension('ID', ['store' => 'ElasticsearchPayloadStoreHandler', 'index' => 'INDEX_NAME'])

# Optional, defaults to localhost:9200
ElasticsearchPayloadStoreHandler:
  hosts:
    - localhost:9200
    - { host: elastic.domain.tld, port: 443, scheme: https, user: USERNAME, pass: PASS }
```

## Maintainer
- Julian Scheuchenzuber <js@lvl51.de>