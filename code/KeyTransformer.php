<?php

/**
 * Interface KeyTransformer
 */
interface KeyTransformer {

    /**
     * @param string $value
     *
     * @return string
     */
    public static function transform(string $value): string;
}