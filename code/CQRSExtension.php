<?php

class CQRSExtension extends Extension {

    private static $key_transformer = 'LowerCamelCaseKeyTransformer';

    private static $better_buttons_actions = [
        'writeToPayloadStore'
    ];

    private $key;

    private $keyTransformer;

    private $validationErrors;

    public function __construct($key) {
        parent::__construct();

        $this->key = $key;
        $this->keyTransformer = Config::inst()->get($this->class, 'key_transformer');
    }

    private function payloadInterfaceImplemented() {
        return $this->owner instanceof PayloadProvider;
    }

    private function getPayloadStoreKey() {
        return $this->owner->{$this->key} ?: $this->key;
    }

    private function getCommitedPayload() {
        return $this->getActiveHandler()->read($this->getPayloadStoreKey());
    }

    private function getPayloadChecksum($payload) {
        return md5(Convert::array2json($payload));
    }

    private function getManifest() {
        return Config::inst()->get($this->owner->class, 'read_payload');
    }

    public function prepareReadPayload() {

        // Transform keys
        $fields = [];
        array_walk($this->getManifest(), function ($field, $key, $transformer) use (&$fields) {
            if (is_int($key)) {
                $key = $field;
            }

            $fields[$transformer::transform($key)] = $field;
        }, $this->keyTransformer);

        return $fields;
    }

    public function canCommit(&$errors = []) {
        $this->validationErrors = [];
        $err = count($errors) > 0 ? $errors : $this->validationErrors;

        foreach ($this->getManifest() as $key => $value) {

            // key is int: field is class variable or method and may not be null
            if (is_int($key)) {

                // Check for DB field
                if (array_key_exists($value, $this->owner->db())) {
                    if ($this->owner->$value === null ||
                        $this->owner->$value === '')
                        $err[] = [
                            'fieldName'   => $value,
                            'message'     => "\"$value\" is a required field", // TODO: i18n
                            'messageType' => 'bad' // [bad|message|validation|required]
                        ];
                }

                // Check for method
                if ($this->owner->hasMethod($value)) {
                    if ($this->owner->$value() === null ||
                        $this->owner->$value() === '')
                        $err[] = [
                            'fieldName'   => $value,
                            'message'     => "\"$value\" is invalid", // TODO: i18n
                            'messageType' => 'bad' // [bad|message|validation|required]
                        ];
                }
            } elseif (array_key_exists($key, $this->owner->hasMany())) {
                $class = $this->owner->hasMany()[$key];

                // Check for interface on relation class
                if (!(singleton($class) instanceof PayloadProvider)) {
                    user_error('Class "' . $class . '" requires the owner to implement "PayloadProvider" interface');
                }

                // Check if relation records can be commited
                foreach ($this->owner->$key() as $relationRecord) {
                    $relationRecord->canCommit($err);
                }
            } elseif (is_array($value)) {

                // TODO: Parse rules
                /**
                 * [
                 * 'value' => 'MyTitle',
                 * 'rules' => false, // bool | []
                 * ]
                 */
            }
        }

        return count($err) < 1;
    }

    public function getValidationErrors() {
        return $this->validationErrors;
    }

    public function isInSync() {
        return $this->getPayloadChecksum($this->getCommitedPayload()) === $this->getPayloadChecksum($this->owner->commit());
    }

    public function writeToPayloadStore() {
        var_dump($this->owner->getEditForm());die;

        Session::set(
            "FormInfo.Form_ItemEditForm.formError.message",
            "Testing" // TODO: Message here
        );
        Session::set("FormInfo.Form_ItemEditForm.formError.type", "bad");

        if ($this->owner->canCommit()) {
            $handler = $this->getActiveHandler();
            $payload = $this->owner->commit();
            $handler->write($this->getPayloadStoreKey(), $payload);
        }
    }

    public function getActiveHandler() {
        return RedisPayloadStoreHandler::inst();
    }

    public function updateBetterButtonsActions($actions) {
        if (!$this->payloadInterfaceImplemented()) {
            user_error('CQRSExtension requires the owner to implement "PayloadProvider" interface');
        }

        if ($this->owner->canCommit() &&
            !$this->owner->isInSync()) {
            $actions->push(
                BetterButtonCustomAction::create(
                    'writeToPayloadStore',
                    _t('CQRSExtension.WRITE', 'Lesedatenbank aktualisieren')
                )
            );
        }
    }
}