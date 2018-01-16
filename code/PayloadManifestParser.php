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
            'field' => _t($record->$field()->dataClass() . '.PLURALNAME'),
            'class' => _t($record->class . '.SINGULARNAME'),
            'title' => $record->getTitle()
        ]));
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
     * @param      $record
     * @param bool $collectErrors
     *
     * @return array
     */
    public function commit($record, $collectErrors = true) {
        $payload = [];
        $class = $record->class;

        foreach ($this->getManifest($class) as $key => $value) {

            // key is int: field is class variable or method and may not be null
            if (is_int($key) ||
                (is_string($key) && is_string($value))) {
                if (is_int($key)) {
                    $key = $value;
                }

                // Check for DB field
                if ($record->hasField($value)) {
                    $value = $record->$value;
                    if ($value !== null &&
                        $value !== '') {
                        $payload[$key] = $value;
                    } else {
                        if ($collectErrors)
                            $this->required($record, $key);
                    }
                } else {

                    // Check for method
                    if ($record->hasMethod($value)) {
                        $value = $record->$value();
                        if ($value !== null &&
                            $value !== '') {
                            $payload[$key] = $value;
                        } else {
                            if ($collectErrors)
                                $this->required($record, $key);
                        }
                    } else {
                        user_error("No method \"$value\" found on \"$class\".");
                    }
                }
            } elseif (array_key_exists($key, $record->hasMany()) ||
                array_key_exists($key, $record->manyMany())) {
                $relationRecords = $record->$key();
                $isRequired = $value;

                if ($isRequired === true &&
                    !$relationRecords->exists()) {
                    $this->missing($record, $key);
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