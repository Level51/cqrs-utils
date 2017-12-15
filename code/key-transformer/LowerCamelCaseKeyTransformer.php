<?php

class LowerCamelCaseKeyTransformer implements KeyTransformer {

    public static function transform(string $value): string {

        // Trim trailing whitespace
        $value = trim($value);

        // uc first char of each word
        $value = ucwords($value);

        // Remove whitespaces
        $value = str_replace(" ", "", $value);

        // lc first char
        return lcfirst($value);
    }
}