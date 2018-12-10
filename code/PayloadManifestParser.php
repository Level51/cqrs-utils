<?php

/**
 * Class PayloadManifestParser
 */
class PayloadManifestParser {

    const TYPE_FIELD = 'field';

    const TYPE_METHOD = 'method';

    const TYPE_RELATION = 'relation';

    /**
     * @var string
     */
    private static $key_transformer = 'LowerCamelCaseKeyTransformer';

    /**
     * @var KeyTransformer
     */
    private $keyTransformer;

    /**
     * @var array
     */
    private $validationErrors;

    public function __construct() {
        $this->keyTransformer = Config::inst()->get(PayloadManifestParser::class, 'key_transformer');
        $this->validationErrors = [];
    }

    /**
     * Obtains the read payload manifest for the given class.
     *
     * @param $class
     *
     * @return array|scalar
     */
    public function getManifest($class) {
        return Config::inst()->get($class, 'read_payload');
    }

    /**
     * @return array
     */
    public function getValidationErrors() {
        return $this->validationErrors;
    }

    /**
     * Resets errors array
     */
    public function clearValidationErrors() {
        $this->validationErrors = [];
    }

    /**
     * Lists a validation error if not present yet.
     *
     * @param $error
     */
    private function validationError($error) {
        if (!in_array($error, $this->validationErrors)) {
            $this->validationErrors[] = $error;
        }
    }

    /**
     * Generates a human-readable error message if a value is missing.
     *
     * @param $record
     * @param $field
     */
    private function required($record, $field) {
        $this->validationError(_t('PayloadManifestParser.ERR_REQUIRED', '"{field}" fehlt für {class} "{title}"', '', [
            'field' => $record->fieldLabel($field),
            'class' => _t($record->class . '.SINGULARNAME'),
            'title' => $record->getTitle()
        ]));
    }

    /**
     * Generates a human-readable error message if entries in a relation list are missing.
     *
     * @param $record
     * @param $field
     */
    private function missing($record, $field) {
        $this->validationError(_t('PayloadManifestParser.ERR_MISSING', 'Keine {field}-Einträge für {class} "{title}" gefunden', '', [
            'field' => _t($record->$field()->ClassName . '.PLURALNAME'),
            'class' => _t($record->class . '.SINGULARNAME'),
            'title' => $record->getTitle()
        ]));
    }

    /**
     * Checks if payload can be commited.
     * NOTE: commit() has be to executed first.
     *
     * @return bool
     */
    public function canCommit() {
        return count($this->validationErrors) < 1;
    }

    /**
     * Applies the transformer rules to parsed payload.
     *
     * @param $payload
     *
     * @return array
     */
    private function transform($payload) {

        // Transform keys
        $preparedPayload = [];
        array_walk($payload, function ($field, $key, $transformer) use (&$preparedPayload) {
            if (is_int($key)) {
                $key = $field;
            }

            if (is_array($key)) {
                $key = array_keys($key)[0];
            }

            $preparedPayload[$transformer::transform($key)] = $field;
        }, $this->keyTransformer);

        return $preparedPayload;
    }

    /**
     * Parses one entry of the read payload manifest.
     * This is the main algorithm implementation.
     *
     * @param $record        DataObject
     * @param $key           int|string
     * @param $value         string|bool|array
     * @param $collectErrors bool
     *
     * @return array
     */
    private function parseEntry($record, $key, $value, $collectErrors = true) {

        /**
         * 1. Normalize NVP, e.g. 'ID' --> 'ID' => true
         */
        if (is_int($key)) {
            $key = $value;
            $value = true;
        }

        /**
         * 2. Determine required and payload value
         *  - if: Check if value is bool --> Field, Method or Relation
         *  - elseif 1: Check if value is string --> Custom value mapping via method
         *  - elseif 2: Check if value is array --> Extended config with value and required flag
         */
        $required = false;
        $payload = null;
        $type = null;

        // Check if value is bool --> simple required check
        if (is_bool($value)) {
            $required = $value;

            /**
             * Check if key is:
             *  - ID
             *  - Relation (has_one)
             *  - Relation (has_many, many_many, belongs_many_many)
             *  - Method
             */
            if ($record->hasField($key)) {
                $type = self::TYPE_FIELD;
                $payload = $record->$key;
            } elseif ($record->hasOneComponent($key)) {
                $type = self::TYPE_RELATION;
                if ($record->$key()->exists()) {
                    $payload = $this->commit($record->$key(), $required);
                }
            } elseif ($record->hasManyComponent($key) ||
                $record->manyManyComponent($key)) {
                $type = self::TYPE_RELATION;

                // Recursively collect relation record payload
                if ($record->$key()->exists()) {
                    $payload = [];
                    foreach ($record->$key() as $relationRecord) {
                        $payload[] = $this->commit($relationRecord, $required);
                    }
                }
            } elseif ($record->hasMethod($key)) {
                $type = self::TYPE_METHOD;
                $payload = $record->$key();
            }
        } elseif (is_string($value)) {
            if (!$record->hasMethod($value)) {
                trigger_error("The class {$record->class} must define the method \"{$value}\"", E_USER_ERROR);
            }

            $type = self::TYPE_METHOD;
            $required = true;
            $methodPayload = $record->$value();
            if ($methodPayload instanceof DataList) {
                $payload = [];
                foreach ($methodPayload as $payloadRecord) {
                    $payload[] = $this->commit($payloadRecord);
                }
            } else {
                $payload = $methodPayload;
            }
        } elseif (is_array($value)) {
            if (!key_exists('required', $value) ||
                !key_exists('mapping', $value)) {
                trigger_error("CQRS definition of \"$key\" needs to specify \"required\" (true/false) and \"mapping\" fields (record method name).", E_USER_ERROR);
            }

            if (!$record->hasMethod($value['mapping'])) {
                trigger_error("The class {$record->class} must define the method \"{$value['mapping']}\"", E_USER_ERROR);
            }

            $type = self::TYPE_METHOD;
            $required = $value['required'];
            $method = $value['mapping'];
            $methodPayload = $record->$method();
            if ($methodPayload instanceof DataList) {
                $payload = [];
                foreach ($methodPayload as $payloadRecord) {
                    $payload[] = $this->commit($payloadRecord);
                }
            } else {
                $payload = $methodPayload;
            }
        }

        /**
         * 3. Do error reporting
         */
        if ($required &&
            empty($payload) &&
            $collectErrors === true) {
            if ($type === self::TYPE_RELATION) {
                $this->missing($record, $key);
            } else {
                $this->required($record, $key);
            }
        }

        return [
            $key,
            $payload
        ];
    }

    /**
     * Generates commit-ready data.
     *
     * @param      $record
     * @param bool $collectErrors
     *
     * @return array
     */
    public function commit($record, $collectErrors = true) {
        $payload = [];
        $class = $record->class;

        foreach ($this->getManifest($class) as $key => $value) {
            list($key, $data) = $this->parseEntry($record, $key, $value, $collectErrors);

            $payload[$key] = $data;
        }

        return $this->transform($payload);
    }
}
