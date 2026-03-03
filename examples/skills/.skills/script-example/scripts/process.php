#!/usr/bin/env php
<?php

echo "=== PHP Processing Script ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Arguments received: " . (count($argv) - 1) . "\n\n";

if (count($argv) > 1) {
    echo "Processing arguments:\n";
    foreach (array_slice($argv, 1) as $index => $arg) {
        echo "  " . ($index + 1) . ". Processing: $arg\n";
        echo "     Length: " . strlen($arg) . " characters\n";
        echo "     Uppercase: " . strtoupper($arg) . "\n";
    }
} else {
    echo "No arguments to process.\n";
}

echo "\nProcessing complete!\n";
