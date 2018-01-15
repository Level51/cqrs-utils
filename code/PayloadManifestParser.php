<?php

class PayloadManifestParser {

    private static $key_transformer = 'LowerCamelCaseKeyTransformer';

    private $keyTransformer;

    private $validationErrors;

    public function __construct() {
        $this->keyTransformer = Config::inst()->get(PayloadManifestParser::class, 'key_transformer');
        $this->validationErrors = [];
    }

    public function getManifest($class) {
        return Config::inst()->get($class, 'read_payload');
    }

    public function getValidationErrors() {
        return $this->validationErrors;
    }

    private function validationError($error) {
        if (!in_array($error, $this->validationErrors)) {
            $this->validationErrors[] = $error;
        }
    }

    public function canCommit() {
        return count($this->validationErrors) < 1;
    }

    private function transform($payload) {

        // Transform keys
        $preparedPayload = [];
        array_walk($payload, function ($field, $key, $transformer) use (&$preparedPayload) {
            if (is_int($key)) {
                $key = $field;
            }

            $preparedPayload[$transformer::transform($key)] = $field;
        }, $this->keyTransformer);

        return $preparedPayload;
    }

    /**
     * Better Error Logging (user readable)
     *
     * @param      $instance
     * @param bool $collectErrors
     *
     * @return array
     */
    public function commit($instance, $collectErrors = true) {
        $payload = [];
        $class = $instance->class;

        foreach ($this->getManifest($class) as $key => $value) {

            // key is int: field is class variable or method and may not be null
            if (is_int($key) ||
                (is_string($key) && is_string($value))) {
                if (is_int($key)) {
                    $key = $value;
                }

                // Check for DB field
                if ($instance->hasField($value)) {
                    $value = $instance->$value;
                    if ($value !== null &&
                        $value !== '') {
                        $payload[$key] = $value;
                    } else {
                        if($collectErrors)
                            $this->validationError("\"$key\" is a required field on \"$class\""); // TODO: i18n
                    }
                } else {

                    // Check for method
                    if ($instance->hasMethod($value)) {
                        $value = $instance->$value();
                        if ($value !== null &&
                            $value !== '') {
                            $payload[$key] = $value;
                        } else {
                            if($collectErrors)
                                $this->validationError("\"$key\" is invalid on \"$class\""); // TODO: i18n
                        }
                    } else {
                        user_error("No method \"$value\" found on \"$class\".");
                    }
                }
            } elseif (array_key_exists($key, $instance->hasMany()) ||
                array_key_exists($key, $instance->manyMany())) {
                $relationRecords = $instance->$key();
                $isRequired = $value;

                if ($isRequired === true &&
                    !$relationRecords->exists()) {
                    $this->validationError("No \"$key\" records on \"$class\".");
                } else {

                    // Commit relation records
                    foreach ($relationRecords as $relationRecord) {
                        $payload[$key] = $this->commit($relationRecord, $isRequired);
                    }
                }
            }
        }

        return $this->transform($payload);
    }
}