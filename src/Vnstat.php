<?php

declare(strict_types=1);

namespace Tricarte\Pvnstat;

use Tricarte\Pvnstat\Helpers\Utils as U;

final class Vnstat {

    public $iface;
    public $iflist_arr;
    public $other_ifaces_links;
    public $is_monitored;
    public $created;
    public $updated;
    public $updated_time;
    public $data;

    public function __construct() {

        exec('vnstat --iflist 1', $this->iflist_arr);

        // Different versions of vnstat may output different things
        if (U::strStartsWith($this->iflist_arr[0], 'Available interfaces:')) {
            $this->iflist_arr = explode(
                ' ',
                trim(str_replace('Available interfaces:', '', $this->iflist_arr[0]))
            );
        }

        // Get the currently active interface this system uses
        // if no interface is requested specifically.
        if (! isset($_REQUEST['if'])) {
            $active_iface = trim((string) shell_exec('ip route | grep default | cut -d" " -f5'));
            if (! in_array($active_iface, $this->iflist_arr, true)) {
                $active_iface = null;
            }
        }

        // Set the interface to be displayed
        $this->iface = $_REQUEST['if'] ?? $active_iface ?? $this->iflist_arr[0] ?? null;
        if (null === $this->iface) {
            exit('Could not get interfaces list...');
        }

        // Check that determined interface is monitored by vnstat
        if (! U::strStartsWith(trim(shell_exec("vnstat -i {$this->iface}")), 'Error:')) {
            $this->is_monitored = true;
        } else {
            $this->is_monitored = false;
        }

        if ($this->is_monitored) {
            if (1 === apcu_enabled()) {
                $this->data = apcu_fetch("vnstat_data_{$iface}");
            }

            if (! $this->data) {
                $vnstat = 'vnstat -i '
                    . \trim($this->iface)
                    . ' --json;vnstat -i '
                    . \trim($this->iface)
                    . ' --oneline b';

                $output_arr = null;
                exec($vnstat, $output_arr);

                $this->data[0] = json_decode($output_arr[0]); // vnstat -i iface --json
                // Reverse day and hour order ( I like it this way... )
                $this->data[0]->interfaces[0]->traffic->day
                    = array_reverse($this->data[0]->interfaces[0]->traffic->day);

                $this->data[0]->interfaces[0]->traffic->hour
                    = array_reverse($this->data[0]->interfaces[0]->traffic->hour);

                // Filter out hours that are before the last 24 hours
                $hours     = $this->data[0]->interfaces[0]->traffic->hour;
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
                $this->data[0]->interfaces[0]->traffic->hour = $hours_filtered;

                $this->data[1] = explode(';', $output_arr[1]); // vnstat -i iface --oneline b

                if (apcu_enabled()) {
                    apcu_store("vnstat_data_{$this->iface}", $this->data, 300);
                }
            }
        }

        // Create links to other interfaces other than current one.
        $other_ifaces       = array_diff($this->iflist_arr, [$this->iface]);
        $this->other_ifaces_links = [];
        foreach ($other_ifaces as $if) {
            $this->other_ifaces_links[] = "<a href=\"./?if={$if}\">{$if}</a>";
        }

        // Interface datetime data
        $this->created = sprintf(
            '%d-%02d-%02d',
            $this->data[0]->interfaces[0]->created->date->year,
            $this->data[0]->interfaces[0]->created->date->month,
            $this->data[0]->interfaces[0]->created->date->day
        );
        $this->updated = sprintf(
            '%d-%02d-%02d',
            $this->data[0]->interfaces[0]->updated->date->year,
            $this->data[0]->interfaces[0]->updated->date->month,
            $this->data[0]->interfaces[0]->updated->date->day
        );
        $this->updated_time = sprintf(
            '%02d:%02d',
            $this->data[0]->interfaces[0]->updated->time->hour,
            $this->data[0]->interfaces[0]->updated->time->minute
        );

    } // End of construct

}
