<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();
$application->addCommands([
  new \App\Commands\PatchSummary(),
  new \App\Commands\IssueSummary(),
]);
$application->run();
