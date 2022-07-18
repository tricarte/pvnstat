<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Tricarte\Pvnstat\Helpers\Utils as U;

// Interfaces monitored by vnstat
$iflist_arr = null;
exec('vnstat --iflist 1', $iflist_arr);

// Different versions of vnstat may output different things
if (U::strStartsWith($iflist_arr[0], 'Available interfaces:')) {
    $iflist_arr = explode(
        ' ',
        trim(str_replace('Available interfaces:', '', $iflist_arr[0]))
    );
}

// Get the currently active interface this system uses
// if no interface is requested specifically.
if (! isset($_REQUEST['if'])) {
    $active_iface = trim((string) shell_exec('ip route | grep default | cut -d" " -f5'));
    if (! in_array($active_iface, $iflist_arr, true)) {
        $active_iface = null;
    }
}

// Set the interface to be displayed
$iface = $_REQUEST['if'] ?? $active_iface ?? $iflist_arr[0] ?? null;
if (null === $iface) {
    exit('Could not get interfaces list...');
}

// Check that determined interface is monitored by vnstat
if (! U::strStartsWith(trim(shell_exec("vnstat -i {$iface}")), 'Error:')) {
    $is_monitored = true;
} else {
    $is_monitored = false;
}

if ($is_monitored) {
    if (1 === apcu_enabled()) {
        $data = apcu_fetch("vnstat_data_{$iface}");
    }

    if (! $data) {
        $vnstat = 'vnstat -i '
            . trim($iface)
            . ' --json;vnstat -i '
            . trim($iface)
            . ' --oneline b';

        $output_arr = null;
        exec($vnstat, $output_arr);

        $data[0] = json_decode($output_arr[0]); // vnstat -i iface --json
        // Reverse day and hour order ( I like it this way... )
        $data[0]->interfaces[0]->traffic->day
            = array_reverse($data[0]->interfaces[0]->traffic->day);

        $data[0]->interfaces[0]->traffic->hour
            = array_reverse($data[0]->interfaces[0]->traffic->hour);

        // Filter out hours that are before the last 24 hours
        $hours     = $data[0]->interfaces[0]->traffic->hour;
        $beginDate = date('Y-m-d H:00', strtotime('-1 day'));

        $hours_filtered = array_filter(
            $hours,
            function($h) use ($beginDate) {
                $hDate = sprintf(
                    '%d-%02d-%02d %02d:%02d',
                    $h->date->year,
                    $h->date->month,
                    $h->date->day,
                    $h->time->hour,
                    $h->time->minute
                );

                return $hDate > $beginDate;
            }
        );
        $data[0]->interfaces[0]->traffic->hour = $hours_filtered;

        $data[1] = explode(';', $output_arr[1]); // vnstat -i iface --oneline b

        if (apcu_enabled()) {
            apcu_store("vnstat_data_{$iface}", $data, 300);
        }
    }
}

// Create links to other interfaces other than current one.
$other_ifaces       = array_diff($iflist_arr, [$iface]);
$other_ifaces_links = [];
foreach ($other_ifaces as $if) {
    $other_ifaces_links[] = "<a href=\"./?if={$if}\">{$if}</a>";
}

// Interface datetime data
$created = sprintf(
    '%d-%02d-%02d',
    $data[0]->interfaces[0]->created->date->year,
    $data[0]->interfaces[0]->created->date->month,
    $data[0]->interfaces[0]->created->date->day
);
$updated = sprintf(
    '%d-%02d-%02d',
    $data[0]->interfaces[0]->updated->date->year,
    $data[0]->interfaces[0]->updated->date->month,
    $data[0]->interfaces[0]->updated->date->day
);
$updated_time = sprintf(
    '%02d:%02d',
    $data[0]->interfaces[0]->updated->time->hour,
    $data[0]->interfaces[0]->updated->time->minute
);

include 'views/layout.php';
