<?php

declare(strict_types=1);

use Tests\TestCase;

$dirs = ['Feature', 'Pipeline', 'Rule', 'Support'];
$paths = array_map(fn(string $d): string => __DIR__ . DIRECTORY_SEPARATOR . $d, $dirs);

uses(TestCase::class)->in(...$paths);
