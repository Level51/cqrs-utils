<?php

interface PayloadProvider {

    /**
     * Checks if all conditions are met so that the object can be written to the API payload store.
     *
     * @return bool
     */
    public function canCommit(): bool;

    /**
     * Generates a flat array structure to be stored in the API payload store.
     * The payload will be encoded as JSON.
     *
     * The keys should be in lowerCamelCase, e.g. myFieldKey.
     *
     * @see Convert::array2json()
     *
     * @return array
     */
    public function commit(): array;
}