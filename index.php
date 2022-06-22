<?php
# TODO: cache for 5 minutes
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

# Different versions of vnstat
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
    $vnstat = 'vnstat -i ' . trim($iface) . ' --json';
    $data = json_decode(shell_exec($vnstat));

    # Summary
    $onelineOutput = trim(shell_exec("$vnstat --oneline b"));
    $oneline = explode( ';', $onelineOutput );

    # Days
    // $topCommand = $vnstat . ' t --limit 10';
    // $topOutput = trim(shell_exec( $topCommand ));
    // $top = json_decode( $topOutput );

    # Last 30 Days
    # TODO: Highlight the highest value
    // $daysCommand = $vnstat . ' d --limit 30';
    // $daysOutput = trim(shell_exec( $daysCommand ));
    // $days = json_decode( $daysOutput );

    # Last 12 Months
    // $monthsCommand = $vnstat . ' m --limit 12';
    // $monthsOutput = trim(shell_exec( $monthsCommand ));
    // $months = json_decode( $monthsOutput );

    # Last 24 Hours
    // $beginDate = date('Y-m-d H:00', strtotime('-1 day'));
    // $hoursCommand = $vnstat . ' h --begin ' . "\"$beginDate\"";
    // $hoursOutput = trim(shell_exec( $hoursCommand ));
    // $hours = json_decode( $hoursOutput );

    # TODO: Implement
    // $yearsCommand = $vnstat . ' y ';
    // $yearsOutput = trim(shell_exec( $yearsCommand ));
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

<?php if(count($iflist_arr) > 1): ?>
<!-- List of Interfaces -->
<p class="anchors">
<?php foreach ($iflist_arr as $if): ?>
<a href="./?if=<?= $if; ?>"><?= $if; ?></a>
<?php endforeach; ?>
</p>
<?php endif; ?>

<h2 id="iftitle">Interface: <u><?= $iface; ?></u></h2>

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
</p>

<!-- Summary -->
<?php
$created = sprintf(
    '%d-%02d-%02d'
    , $data->interfaces[0]->created->date->year
    , $data->interfaces[0]->created->date->month
    , $data->interfaces[0]->created->date->day
);
$updated = sprintf(
    '%d-%02d-%02d'
    , $data->interfaces[0]->updated->date->year
    , $data->interfaces[0]->updated->date->month
    , $data->interfaces[0]->updated->date->day
);
?>
<table>
<caption>Summary <a href="#top" class="gotop">&uarr;</a></caption>
<tr>
    <td></td>
    <th>Today</th>
    <th>This Month</th>
    <th>All Time<br />( <?=$created;?> - <?=$updated;?> )</th>
</tr>
<tr>
    <th>Rx:</th>
    <td><?= human_filesize($oneline[3]); ?></td>
    <td><?= human_filesize($oneline[8]); ?></td>
    <td><?= human_filesize($oneline[12]); ?></td>
</tr>
<tr>
    <th>Tx:</th>
    <td><?= human_filesize($oneline[4]); ?></td>
    <td><?= human_filesize($oneline[9]); ?></td>
    <td><?= human_filesize($oneline[13]); ?></td>
</tr>
<tr>
    <th>Total:</th>
    <td><?= human_filesize($oneline[5]); ?></td>
    <td><?= human_filesize($oneline[10]); ?></td>
    <td><?= human_filesize($oneline[14]); ?></td>
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
<?php foreach ($data->interfaces[0]->traffic->top as $day):
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
<?php foreach ($data->interfaces[0]->traffic->day as $day):
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
<?php foreach ($data->interfaces[0]->traffic->month as $month):
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
<th>Hour</th>
<th>Received</th>
<th>Transferred</th>
<th>Total</th>
</tr>
<?php foreach ($data->interfaces[0]->traffic->hour as $hour):
$time = sprintf( '%02d:00', $hour->time->hour );
$rx = ( $hour->rx == 0 ) ? '-' :  human_filesize( $hour->rx );
$tx = ( $hour->tx == 0 ) ? '-' :  human_filesize( $hour->tx );
$total = ( $hour->rx + $hour->tx == 0 ) ? '-' : human_filesize( $hour->rx + $hour->tx );
?>
<tr>
    <td><?= $time; ?></td>
    <td><?= $rx; ?></td>
    <td><?= $tx; ?></td>
    <td><?= $total; ?></td>
</tr>
<?php endforeach; ?>
</table> <!-- End of HOURS -->

<?php endif; # Check current interface is monitored by vnstat ?>
  <!-- <script src="js/scripts.js"></script> -->
</body>
</html>
