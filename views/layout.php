<?php declare(strict_types=1);

use Tricarte\Pvnstat\Helpers\Utils as U;

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

</head>

<body>

    <header>
        <h2 id="iftitle">Viewing network interface: <mark id="current_iface"><?= $iface ?></mark></h2>
        <?php if (! empty($other_ifaces_links)) { ?>
            <p>Other Interfaces: <?= implode(' ', $other_ifaces_links) ?></p>
        <?php } ?>

        <!-- Links to tables -->
        <?php if ($is_monitored) { ?>
            <nav>
                <a href="#summary">Summary</a>
                <a href="#topdays">Top</a>
                <a href="#hours">Hours</a>
                <a href="#days">Days</a>
                <a href="#months">Months</a>
                <a href="#years">Years</a>
            </nav>
        <?php } ?>
    </header>

    <main>
        <?php if (! $is_monitored) { ?>
            <p>Interface <mark><?= $iface ?></mark> is not monitored by vnstat.</p>
		<?php } else { // Begin the rendering?>

            <!-- Date Time Info -->
            <section>
                <table class="t_info">
                    <thead class="box">
                        <tr>
                            <th scope="col">Monitoring Since</th>
                            <th scope="col">Last Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= $created ?><br><small><mark><?= U::dateDifference($updated, $created) ?></mark></small></td>
                            <td><?= $updated ?><br><small><mark><?= $updated_time ?></mark></small></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Summary -->
            <section>
                <table id="summary">
                    <caption>
                        <mark>Summary</mark> <small><a href="#top" class="gotop">[Top]</a></small>
                    </caption>
                    <thead>
                        <tr>
                            <td></td>
                            <th scope="col">Today</th>
                            <th scope="col">This Month</th>
                            <th scope="col">All Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">Rx:</th>
                            <td><?= U::humanFilesize((int) $data[1][3]) ?></td>
                            <td><?= U::humanFilesize((int) $data[1][8]) ?></td>
                            <td><?= U::humanFilesize((int) $data[1][12]) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Tx:</th>
                            <td><?= U::humanFilesize((int) $data[1][4]) ?></td>
                            <td><?= U::humanFilesize((int) $data[1][9]) ?></td>
                            <td><?= U::humanFilesize((int) $data[1][13]) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Total:</th>
                            <td><?= U::humanFilesize((int) $data[1][5]) ?></td>
                            <td><?= U::humanFilesize((int) $data[1][10]) ?></td>
                            <td><?= U::humanFilesize((int) $data[1][14]) ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- TOP 10 DAYS -->
            <section>
                <table id="topdays">
                    <caption>
                        <mark>Top Days</mark> <small><a href="#top" class="gotop">[Top]</a></small>
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col">Rx</th>
                            <th scope="col">Tx</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($data[0]->interfaces[0]->traffic->top as $day) {
                            $date = sprintf(
                                '%d-%02d-%02d',
                                $day->date->year,
                                $day->date->month,
                                $day->date->day
                            );
                            $rx    = (0 === $day->rx) ? '-' : U::humanFilesize($day->rx);
                            $tx    = (0 === $day->tx) ? '-' : U::humanFilesize($day->tx);
                            $total = (0 === $day->rx + $day->tx) ? '-' : U::humanFilesize(($day->rx + $day->tx)); ?>
                            <tr>
                                <td><?= $date ?></td>
                                <td><?= $rx ?></td>
                                <td><?= $tx ?></td>
                                <td><?= $total ?></td>
                            </tr>
                        <?php
                        } ?>
                    </tbody>
                </table> <!-- End of TOP 10 DAYS -->
            </section>

            <!-- HOURS -->
            <section>
                <table id="hours">
                    <caption><mark>Hours</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
                    <thead>
                        <tr>
                            <th scope="col">Hour</th>
                            <th scope="col">Rx</th>
                            <th scope="col">Tx</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $lastdate = null;
    foreach ($data[0]->interfaces[0]->traffic->hour as $hour) {
        $date = sprintf(
            '%d-%02d-%02d',
            $hour->date->year,
            $hour->date->month,
            $hour->date->day
        );
        $time  = sprintf('%02d:00', $hour->time->hour);
        $rx    = (0 === $hour->rx) ? '-' : U::humanFilesize($hour->rx);
        $tx    = (0 === $hour->tx) ? '-' : U::humanFilesize($hour->tx);
        $total = (0 === $hour->rx + $hour->tx) ? '-' : U::humanFilesize($hour->rx + $hour->tx); ?>
                            <tr>
                                <?php
                                if ($lastdate !== $date) { ?>
                                    <td class="date"><?= $date ?></td>
                                    <td colspan="3"></td>
                            </tr>
                        <?php }
        $lastdate = $date; ?>
                        <td><?= $time ?></td>
                        <td><?= $rx ?></td>
                        <td><?= $tx ?></td>
                        <td><?= $total ?></td>
                        </tr>
                    <?php
    } ?>
                    </tbody>
                </table> <!-- End of HOURS -->
            </section>

            <!-- Last 30 Days -->
            <section>
                <table id="days">
                    <caption><mark>Days</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
                    <thead>
                        <tr>
                            <th scope="col">Day</th>
                            <th scope="col">Rx</th>
                            <th scope="col">Tx</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($data[0]->interfaces[0]->traffic->day as $day) {
                            $date = sprintf(
                                '%d-%02d-%02d',
                                $day->date->year,
                                $day->date->month,
                                $day->date->day
                            );
                            $rx    = (0 === $day->rx) ? '-' : U::humanFilesize($day->rx);
                            $tx    = (0 === $day->tx) ? '-' : U::humanFilesize($day->tx);
                            $total = (0 === $day->rx + $day->tx) ? '-' : U::humanFilesize($day->rx + $day->tx); ?>
                            <tr>
                                <td><?= $date ?></td>
                                <td><?= $rx ?></td>
                                <td><?= $tx ?></td>
                                <td><?= $total ?></td>
                            </tr>
                        <?php
                        } ?>
                    </tbody>
                </table> <!-- End of Last 30 Days -->
            </section>

            <!-- MONTHS -->
            <section>
                <table id="months">
                    <caption><mark>Months</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
                    <thead>
                        <tr>
                            <th scope="col">Month</th>
                            <th scope="col">Rx</th>
                            <th scope="col">Tx</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($data[0]->interfaces[0]->traffic->month as $month) {
                            $date = sprintf(
                                '%d-%02d',
                                $month->date->year,
                                $month->date->month
                            );
                            $rx    = (0 === $month->rx) ? '-' : U::humanFilesize($month->rx);
                            $tx    = (0 === $month->tx) ? '-' : U::humanFilesize($month->tx);
                            $total = (0 === $month->rx + $month->tx) ? '-' : U::humanFilesize($month->rx + $month->tx); ?>
                            <tr>
                                <td><?= $date ?></td>
                                <td><?= $rx ?></td>
                                <td><?= $tx ?></td>
                                <td><?= $total ?></td>
                            </tr>
                        <?php
                        } ?>
                    </tbody>
                </table> <!-- End of MONTHS -->
            </section>

            <!-- YEARS -->
            <section>
                <table id="years">
                    <caption><mark>Years</mark> <small><a href="#top" class="gotop">[Top]</a></small></caption>
                    <thead>
                        <tr>
                            <th scope="col">Year</th>
                            <th scope="col">Rx</th>
                            <th scope="col">Tx</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($data[0]->interfaces[0]->traffic->year as $year) {
                            $rx    = (0 === $year->rx) ? '-' : U::humanFilesize($year->rx);
                            $tx    = (0 === $year->tx) ? '-' : U::humanFilesize($year->tx);
                            $total = (0 === $year->rx + $year->tx) ? '-' : U::humanFilesize($year->rx + $year->tx); ?>
                            <tr>
                                <td><?= $year->date->year ?></td>
                                <td><?= $rx ?></td>
                                <td><?= $tx ?></td>
                                <td><?= $total ?></td>
                            </tr>
                        <?php
                        } ?>
                    </tbody>
                </table> <!-- End of YEARS -->
            </section>

        <?php
} // Check current interface is monitored by vnstat
        ?>
    </main>

    <footer>
        <small>vnStat</small>
    </footer>

</body>

</html>
