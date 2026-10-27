<?php

namespace App\Support;

/*
 * One Iranian mobile number, written one way.
 *
 * A child's parent will type 0912…, ۰۹۱۲… in Persian digits, +98912…, or
 * 0098912…, and every one of those is the same phone. Normalising at the
 * edge means the rest of the server never has to know that, and — more to
 * the point — one person does not end up with four accounts and their
 * chapters split between them.
 */
class Phone
{
    /** Persian and Arabic-Indic digits, which Iranian keyboards produce. */
    private const EASTERN = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * The canonical form (+98 followed by ten digits), or null when this is
     * not an Iranian mobile number at all.
     */
    public static function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', strtr(trim($raw), self::EASTERN)) ?? '';

        // 0098912…, +98912… and 98912… all arrive here as 912…
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Every Iranian mobile number is 9 followed by nine more digits.
        if (! preg_match('/^9\d{9}$/', $digits)) {
            return null;
        }

        return '+98'.$digits;
    }

    /** What to show a person: ۰۹۱۲۳۴۵۶۷۸۹ rather than +989123456789. */
    public static function forHumans(string $normalised): string
    {
        return '0'.substr($normalised, 3);
    }
}
