<?php

class LowerCamelCaseKeyTransformer implements KeyTransformer {

    private static $dict = [
        'ID' => 'id'
    ];

    public static function transform(string $value): string {

        // Check for dictionary entry
        if (array_key_exists($value, self::$dict)) {
            return self::$dict[$value];
        }

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