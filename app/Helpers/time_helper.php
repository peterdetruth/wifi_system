<?php

if (!function_exists('calculate_expiry')) {
    /**
     * Calculates expiry datetime based on duration length & unit.
     *
     * @param string|null $startDate (default: now)
     * @param int $length
     * @param string $unit
     * @return string Y-m-d H:i:s
     */
    function calculate_expiry(?string $startDate, int $length, string $unit): string
    {
        $start = $startDate ? strtotime($startDate) : time();

        switch (strtolower($unit)) {
            case 'minute':
            case 'minutes':
                $expiry = strtotime("+{$length} minutes", $start);
                break;

            case 'hour':
            case 'hours':
                $expiry = strtotime("+{$length} hours", $start);
                break;

            case 'day':
            case 'days':
                $expiry = strtotime("+{$length} days", $start);
                break;

            case 'week':
            case 'weeks':
                $expiry = strtotime("+{$length} weeks", $start);
                break;

            case 'month':
            case 'months':
                $expiry = strtotime("+{$length} months", $start);
                break;

            default:
                $expiry = strtotime("+7 days", $start);
                break;
        }

        return date('Y-m-d H:i:s', $expiry);
    }
}

if (!function_exists('remaining_time')) {
    /**
     * Returns human-readable remaining time until expiry.
     *
     * @param string|null $expiresOn
     * @return string
     */
    function remaining_time(?string $expiresOn): string
    {
        if (!$expiresOn) {
            return 'No expiry set';
        }

        $now = time();
        $expiry = strtotime($expiresOn);
        $diff = $expiry - $now;

        if ($diff <= 0) {
            return 'Expired';
        }

        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);

        if ($days > 0) {
            return $days . ' day' . ($days > 1 ? 's' : '') . ' left';
        } elseif ($hours > 0) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' left';
        } elseif ($minutes > 0) {
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' left';
        }

        return 'Less than a minute left';
    }
}

