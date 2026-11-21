<?php

namespace Psalm\Shepherd;

use function preg_replace;

final class Badge
{
    /**
     * The badges are drawn in the shields.io "flat" style. "flat-square" drops the rounded corners, the gradient
     * and the text shadow; any other style keeps "flat".
     *
     * @psalm-pure
     */
    public static function applyStyle(string $svg, mixed $style) : string
    {
        if ($style !== 'flat-square') {
            return $svg;
        }

        return (string) preg_replace(
            [
                '/<linearGradient\b.*?<\/linearGradient>/',
                '/<(?:path|rect)\b[^>]* fill="url\(#\w+\)"[^>]*\/>/',
                '/<text\b[^>]* fill="#010101"[^>]*>[^<]*<\/text>/',
                '/ rx="3"/',
            ],
            '',
            $svg
        );
    }
}
