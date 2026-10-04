<?php

namespace App\Support;

use Carbon\Carbon;

class Jalali
{
    public static function fromCarbon(Carbon $date, bool $withTime = false): string
    {
        [$jy, $jm, $jd] = self::gregorianToJalali((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));

        $datePart = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

        if (! $withTime) {
            return self::toPersianDigits($datePart);
        }

        return self::toPersianDigits($datePart . ' - ساعت ' . $date->format('H:i'));
    }

    protected static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];

        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    protected static function toPersianDigits(string $str): string
    {
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

        return str_replace($en, $fa, $str);
    }
}