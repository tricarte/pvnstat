<?php
if(! function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0 ? true : false;
    }
}

function human_filesize($bytes, $decimals = 2) {
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}


# Interfaces monitored by vnstat
$iflist_arr = null;
exec( 'vnstat --iflist 1', $iflist_arr );

# Different versions of vnstat may output different things
if(str_starts_with($iflist_arr[0], 'Available interfaces:')) {
    $iflist_arr = explode(' ', trim(str_replace('Available interfaces:', '', $iflist_arr[0] )));
}

# Get the currently active interface this system uses
if( 'Linux' == PHP_OS && ! isset($_REQUEST['if']) ) {
    $activeiface = trim(shell_exec( 'ip route | grep default | cut -d" " -f5' ));
    if(! in_array($activeiface, $iflist_arr)) {
        $activeiface = null;
    }
}

# Set the interface to be displayed
$iface = $_REQUEST['if'] ?? $activeiface ?? $iflist_arr[0] ?? null;
if( null == $iface ) {
    exit("Could not get interfaces list...");
}

# Check that determined interface is monitored by vnstat
$ifstatus = trim(shell_exec("vnstat -i $iface"));
$ifstatus = str_starts_with($ifstatus, 'Error:');

if(! $ifstatus) {

    if(apcu_enabled() == 1) {
        $data = apcu_fetch("vnstat_data_{$iface}");
    }

    if(! $data) {
        $vnstat = 'vnstat -i ' . trim($iface) . ' --json;vnstat -i ' . trim($iface) . ' --oneline b';

        $output_arr = null;
        exec($vnstat, $output_arr);

        $data[0] = json_decode($output_arr[0]); # vnstat -i iface --json
		# Reverse day and hour order ( I like it this way... )
		$data[0]->interfaces[0]->traffic->day = array_reverse($data[0]->interfaces[0]->traffic->day);
		$data[0]->interfaces[0]->traffic->hour = array_reverse($data[0]->interfaces[0]->traffic->hour);

        # Filter out hours that are before the last 24 hours
        $hours = $data[0]->interfaces[0]->traffic->hour;
        $beginDate = date('Y-m-d H:00', strtotime('-1 day'));
        $hours_filtered = array_filter($hours, function($h) use ($beginDate) {
            $hDate = sprintf(
                '%d-%02d-%02d %02d:%02d'
                , $h->date->year
                , $h->date->month
                , $h->date->day
                , $h->time->hour
                , $h->time->minute
            );

            return $hDate > $beginDate;

        });
        $data[0]->interfaces[0]->traffic->hour = $hours_filtered;

        $data[1] = explode( ';', $output_arr[1] ); # vnstat -i iface --oneline b

        if(apcu_enabled()) {
            apcu_store("vnstat_data_{$iface}", $data, 300);
        }
    }
}

?>
<!doctype html>

<html lang="en">
<head>
  <meta charset="utf-8">

  <title>VNSTAT</title>
  <meta name="description" content="vnStat Statistics">
  <meta name="author" content="tricarte">

  <link rel="stylesheet" href="css/styles.css">

</head>

<body>

<?php
$otherIfaces = array_diff($iflist_arr, [$iface]);
$otherIfacesLinks = "";
foreach($otherIfaces as $oIface) {
    $otherIfacesLinks .= "<a href=\"./?if={$oIface}\">{$oIface}</a> ";
}
?>

<div class="header">
<h2 id="iftitle">Viewing network interface: <span id="current_iface"><?= $iface; ?></h2>
<p style="text-align: center;">Other Interfaces: <?= $otherIfacesLinks; ?></p>
</div>

<?php if($ifstatus): ?>
<p class="error">This interface is not monitored by vnstat.</p>
<?php else: # Begin the rendering ?>

<!-- Links to tables -->
<p class="anchors">
<a href="#summary">Summary</a>
&bull;
<a href="#topdays">Top</a>
&bull;
<a href="#days">Days</a>
&bull;
<a href="#months">Months</a>
&bull;
<a href="#hours">Hours</a>
&bull;
<a href="#years">Years</a>
</p>

<!-- Summary -->
<?php
$created = sprintf(
    '%d-%02d-%02d'
    , $data[0]->interfaces[0]->created->date->year
    , $data[0]->interfaces[0]->created->date->month
    , $data[0]->interfaces[0]->created->date->day
);
$updated = sprintf(
    '%d-%02d-%02d'
    , $data[0]->interfaces[0]->updated->date->year
    , $data[0]->interfaces[0]->updated->date->month
    , $data[0]->interfaces[0]->updated->date->day
);
$updatedtime = sprintf(
    '%02d:%02d'
    , $data[0]->interfaces[0]->updated->time->hour
    , $data[0]->interfaces[0]->updated->time->minute
);
?>
<table>
<caption>Summary <a href="#top" class="gotop">&uarr;</a></caption>
<tr>
    <td></td>
    <th>Today</th>
    <th>This Month</th>
    <th>All Time<br />( <?=$created;?> - <?=$updated,' ',$updatedtime;?> )</th>
</tr>
<tr>
    <th>Rx:</th>
    <td><?= human_filesize($data[1][3]); ?></td>
    <td><?= human_filesize($data[1][8]); ?></td>
    <td><?= human_filesize($data[1][12]); ?></td>
</tr>
<tr>
    <th>Tx:</th>
    <td><?= human_filesize($data[1][4]); ?></td>
    <td><?= human_filesize($data[1][9]); ?></td>
    <td><?= human_filesize($data[1][13]); ?></td>
</tr>
<tr>
    <th>Total:</th>
    <td><?= human_filesize($data[1][5]); ?></td>
    <td><?= human_filesize($data[1][10]); ?></td>
    <td><?= human_filesize($data[1][14]); ?></td>
</tr>
</table>

<!-- TOP 10 DAYS -->
<table id="topdays">
<caption>Top Days <a href="#top" class="gotop">&uarr;</a></caption>
<tr>
<th>Day</th>
<th>Received</th>
<th>Transferred</th>
<th>Total</th>
</tr>
<?php foreach ($data[0]->interfaces[0]->traffic->top as $day):
$date = sprintf( '%d-%02d-%02d', $day->date->year, $day->date->month, $day->date->day);
$rx = ( $day->rx == 0 ) ? '-' :  human_filesize( $day->rx );
$tx = ( $day->tx == 0 ) ? '-' :  human_filesize( $day->tx );
$total = ( $day->rx + $day->tx == 0 ) ? '-' : human_filesize( $day->rx + $day->tx );
?>
<tr>
    <td><?= $date; ?></td>
    <td><?= $rx; ?></td>
    <td><?= $tx; ?></td>
    <td><?= $total; ?></td>
</tr>
<?php endforeach; ?>
</table> <!-- End of TOP 10 DAYS -->

<!-- Last 30 Days -->
<table id="days">
<caption>Days <a href="#top" class="gotop">&uarr;</a></caption>
<tr>
<th>Day</th>
<th>Received</th>
<th>Transferred</th>
<th>Total</th>
</tr>
<?php foreach ($data[0]->interfaces[0]->traffic->day as $day):
$date = sprintf( '%d-%02d-%02d', $day->date->year, $day->date->month, $day->date->day);
$rx = ( $day->rx == 0 ) ? '-' :  human_filesize( $day->rx );
$tx = ( $day->tx == 0 ) ? '-' :  human_filesize( $day->tx );
$total = ( $day->rx + $day->tx == 0 ) ? '-' : human_filesize( $day->rx + $day->tx );
?>
<tr>
    <td><?= $date; ?></td>
    <td><?= $rx; ?></td>
    <td><?= $tx; ?></td>
    <td><?= $total; ?></td>
</tr>
<?php endforeach; ?>
</table> <!-- End of Last 30 Days -->

<!-- MONTHS -->
<table id="months">
<caption>Months <a href="#top" class="gotop">&uarr;</a></caption>
<tr>
<th>Month</th>
<th>Received</th>
<th>Transferred</th>
<th>Total</th>
</tr>
<?php foreach ($data[0]->interfaces[0]->traffic->month as $month):
$date = sprintf( '%d-%02d', $month->date->year, $month->date->month);
$rx = ( $month->rx == 0 ) ? '-' :  human_filesize( $month->rx );
$tx = ( $month->tx == 0 ) ? '-' :  human_filesize( $month->tx );
$total = ( $month->rx + $month->tx == 0 ) ? '-' : human_filesize( $month->rx + $month->tx );
?>
<tr>
    <td><?= $date; ?></td>
    <td><?= $rx; ?></td>
    <td><?= $tx; ?></td>
    <td><?= $total; ?></td>
</tr>
<?php endforeach; ?>
</table> <!-- End of MONTHS -->

<!-- HOURS -->
<table id="hours">
<caption>Hours <a href="#top" class="gotop">&uarr;</a></caption>
<tr>
<th>Date</th>
<th>Hour</th>
<th>Received</th>
<th>Transferred</th>
<th>Total</th>
</tr>
<?php
$lastdate = null;
foreach ($data[0]->interfaces[0]->traffic->hour as $hour):
$date = sprintf( '%d-%02d-%02d', $hour->date->year, $hour->date->month, $hour->date->day);
$time = sprintf( '%02d:00', $hour->time->hour );
$rx = ( $hour->rx == 0 ) ? '-' :  human_filesize( $hour->rx );
$tx = ( $hour->tx == 0 ) ? '-' :  human_filesize( $hour->tx );
$total = ( $hour->rx + $hour->tx == 0 ) ? '-' : human_filesize( $hour->rx + $hour->tx );
?>
<tr>
	<td><?= $lastdate != $date ? $date : ''; $lastdate = $date; ?></td>
    <td><?= $time; ?></td>
    <td><?= $rx; ?></td>
    <td><?= $tx; ?></td>
    <td><?= $total; ?></td>
</tr>
<?php endforeach; ?>
</table> <!-- End of HOURS -->

<!-- YEARS -->
<table id="years">
<caption>Years <a href="#top" class="gotop">&uarr;</a></caption>
<tr>
<th>Year</th>
<th>Received</th>
<th>Transferred</th>
<th>Total</th>
</tr>
<?php
foreach ($data[0]->interfaces[0]->traffic->year as $year):
$rx = ( $year->rx == 0 ) ? '-' :  human_filesize( $year->rx );
$tx = ( $year->tx == 0 ) ? '-' :  human_filesize( $year->tx );
$total = ( $year->rx + $year->tx == 0 ) ? '-' : human_filesize( $year->rx + $year->tx );
?>
<tr>
    <td><?= $year->date->year; ?></td>
    <td><?= $rx; ?></td>
    <td><?= $tx; ?></td>
    <td><?= $total; ?></td>
</tr>
<?php endforeach; ?>
</table> <!-- End of YEARS -->

<?php endif; # Check current interface is monitored by vnstat ?>
  <!-- <script src="js/scripts.js"></script> -->
</body>
</html>
