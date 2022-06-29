<?php
# https://www.php.net/manual/tr/function.date-diff.php#115065
function dateDifference($date_1 , $date_2)
{
    $datetime1 = date_create($date_1);
    $datetime2 = date_create($date_2);

    $interval = date_diff($datetime1, $datetime2);
    $formatArr = [];

    switch($interval->y) {
        case 0:
            break;
        case 1:
            $formatArr[] = '%y Year';
        default:
            $formatArr[] = '%y Years';
    }

    switch($interval->m) {
        case 0:
            break;
        case 1:
            $formatArr[] = '%m month';
        default:
            $formatArr[] = '%m months';
    }

    switch($interval->d) {
        case 0:
            break;
        case 1:
            $formatArr[] = '%d day';
        default:
            $formatArr[] = '%d days';
    }

    return $interval->format(implode(' ', $formatArr));

}

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
if( ! isset($_REQUEST['if']) ) {
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

# Create links to other interfaces other than current one.
$otherIfaces = array_diff($iflist_arr, [$iface]);
$otherIfacesLinks = [];
foreach($otherIfaces as $oIface) {
    $otherIfacesLinks[] = "<a href=\"./?if={$oIface}\">{$oIface}</a>";
}

?>

<!doctype html>

<html lang="en">
<head>
  <meta charset="utf-8">

  <title>Bandwidth Usage - vnStat</title>
  <meta name="description" content="Bandwidth usage using vnStat data">
  <meta name="author" content="tricarte">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="./css/watercss-dark.min.css">

<style>

section {
    padding: 0.1rem;
    margin-top: 2.0rem;
}

header h2, header p {
    text-align: center;
}

nav {
    display: flex;
    justify-content: space-evenly;
    flex-wrap: wrap;
}

nav a {
    text-decoration: underline;
}

footer {
    text-align: center;
}

td {
    font-size: 0.9em;
    white-space: nowrap;
}

table td.date {
    text-align: center;
}

</style>

</head>

<body>

<header>
<h2 id="iftitle">Viewing network interface: <mark id="current_iface"><?= $iface; ?></mark></h2>
<?php if(! empty($otherIfacesLinks)): ?>
<p>Other Interfaces: <?= implode(' ', $otherIfacesLinks); ?></p>
<?php endif; ?>

<!-- Links to tables -->
<?php if(! $ifstatus): ?>
<nav>
<a href="#summary">Summary</a>
<a href="#topdays">Top</a>
<a href="#days">Days</a>
<a href="#months">Months</a>
<a href="#hours">Hours</a>
<a href="#years">Years</a>
</nav>
<?php endif; ?>
</header>

<main>
<?php if($ifstatus): ?>
<p>Interface <mark><?= $iface; ?></mark> is not monitored by vnstat.</p>
<?php else: # Begin the rendering ?>

<?php
// All time period
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

<!-- Date Time Info -->
<section>
<table>
<thead>
<tr>
<th>Interface Creation Date</th>
<th>Last Update</th>
</tr>
</thead>
<tbody>
<tr>
<td><?=$created;?><br /><small><mark><?=dateDifference($updated, $created);?></mark></small></td>
<td><?=$updated?><br /><small><mark><?=$updatedtime;?></mark></small></td>
</tr>
</tbody>
</table>
</section>

<!-- Summary -->
<section>
<table id="summary">
<caption><mark>Summary</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
<tr>
    <td></td>
    <th>Today</th>
    <th>This Month</th>
    <th>All Time</th>
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
</section>

<!-- TOP 10 DAYS -->
<section>
<table id="topdays">
<caption><mark>Top Days</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
<thead>
<tr>
<th>Day</th>
<th>Rx</th>
<th>Tx</th>
<th>Total</th>
</tr>
</thead>
<tbody>
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
</tbody>
</table> <!-- End of TOP 10 DAYS -->
</section>

<!-- Last 30 Days -->
<section>
<table id="days">
<caption><mark>Days</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
<thead>
<tr>
<th>Day</th>
<th>Rx</th>
<th>Tx</th>
<th>Total</th>
</tr>
</thead>
<tbody>
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
</tbody>
</table> <!-- End of Last 30 Days -->
</section>

<!-- MONTHS -->
<section>
<table id="months">
<caption><mark>Months</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
<thead>
<tr>
<th>Month</th>
<th>Rx</th>
<th>Tx</th>
<th>Total</th>
</tr>
</thead>
<tbody>
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
</tbody>
</table> <!-- End of MONTHS -->
</section>

<!-- HOURS -->
<section>
<table id="hours">
<caption><mark>Hours</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
<thead>
<tr>
<th>Hour</th>
<th>Rx</th>
<th>Tx</th>
<th>Total</th>
</tr>
</thead>
<tbody>
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
<?php
if ( $lastdate != $date ): ?>
    <td colspan="4" class="date"><?= $date; ?></td>
</tr>
<?php endif; $lastdate = $date; ?>
    <td><?= $time; ?></td>
    <td><?= $rx; ?></td>
    <td><?= $tx; ?></td>
    <td><?= $total; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table> <!-- End of HOURS -->
</section>

<!-- YEARS -->
<section>
<table id="years">
<caption><mark>Years</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
<thead>
<tr>
<th>Year</th>
<th>Rx</th>
<th>Tx</th>
<th>Total</th>
</tr>
</thead>
<tbody>
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
</tbody>
</table> <!-- End of YEARS -->
</section>

<?php endif; # Check current interface is monitored by vnstat ?>
</main>

<footer>
<small>vnStat</small>
</footer>

</body>
</html>
