<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Tricarte\Pvnstat\Vnstat;
use Tricarte\Pvnstat\Helpers\Utils as U;

$v = new Vnstat();

include 'views/layout.php';
