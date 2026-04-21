#!/usr/bin/env php
<?php
$php = 'C:\\Users\\dex360\\AppData\\Local\\Programs\\Local\\resources\\extraResources\\lightning-services\\php-8.2.29+0\\bin\\win64\\php.exe';
$extDir = 'C:\\Users\\dex360\\AppData\\Local\\Programs\\Local\\resources\\extraResources\\lightning-services\\php-8.2.29+0\\bin\\win64\\ext';

$args = array_merge(
    [
        $php,
        '-d', 'extension_dir=' . $extDir,
        '-d', 'extension=mbstring',
        '-d', 'extension=dom',
        '-d', 'extension=json',
        '-d', 'extension=libxml',
        '-d', 'extension=tokenizer',
        '-d', 'extension=xml',
        '-d', 'extension=xmlwriter',
        'vendor\\bin\\phpunit'
    ],
    array_slice($argv, 1)
);

$process = proc_open(
    $args,
    [0 => STDIN, 1 => STDOUT, 2 => STDERR],
    $pipes
);

exit(proc_close($process));