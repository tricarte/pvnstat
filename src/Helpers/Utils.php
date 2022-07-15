<?php

declare(strict_types=1);

namespace Tricarte\Pvnstat\Helpers;

class Utils {
    /**
     * Format date difference in human readable form.
     *
     * https://www.php.net/manual/tr/function.date-diff.php#115065
     *
     * @param  string  $date_1 Date string in 'YYYY-MM-DD' format.
     * @param  string  $date_2 Date string in 'YYYY-MM-DD' format.
     * @return string Date period formatted in human readable form.
     */
    public static function dateDifference(string $date_1, string $date_2): string {
        $datetime1 = \date_create_immutable($date_1);
        $datetime2 = \date_create_immutable($date_2);

        $interval  = \date_diff($datetime1, $datetime2);
        $formatArr = [];

        switch ($interval->y) {
            case 0:
                break;
            case 1:
                $formatArr[] = '%y Year';
                break;
            default:
                $formatArr[] = '%y Years';
        }

        switch ($interval->m) {
            case 0:
                break;
            case 1:
                $formatArr[] = '%m month';
                break;
            default:
                $formatArr[] = '%m months';
        }

        switch ($interval->d) {
            case 0:
                break;
            case 1:
                $formatArr[] = '%d day';
                break;
            default:
                $formatArr[] = '%d days';
        }

        return $interval->format(\implode(' ', $formatArr));
    }

    public static function strStartsWith(string $haystack, string $needle): bool {
        if (\function_exists('\str_starts_with')) {
            return \str_starts_with($haystack, $needle);
        }

        return 0 === \mb_strpos($haystack, $needle) ? true : false;
    }

    /**
     * Return byte size in human readable format.
     *
     * @param  int  $bytes Bytes
     * @param  int  $decimals (optional) Floating point precision. Default 2.
     * @return string Byte size in K,M and G format.
     */
    public static function humanFilesize(int $bytes, int $decimals = 2): string {
        $sz     = 'BKMGTP';
        $factor = \floor((\mb_strlen((string) $bytes) - 1) / 3);

        return \sprintf("%.{$decimals}f", $bytes / \pow(1024, $factor))
            . @$sz[$factor];
    }
}
