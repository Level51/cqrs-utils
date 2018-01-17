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

## Maintainer
- Julian Scheuchenzuber <js@lvl51.de>