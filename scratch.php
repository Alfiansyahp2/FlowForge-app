<?php
$expression = '0 0 * * *';
$parts = explode(' ', trim($expression));

$validPatterns = [
    '/^[0-9*\/\-,]+$/',  // minute
    '/^[0-9*\/\-,]+$/',  // hour
    '/^[0-9*\/\-,LW?]+$/',  // day
    '/^[0-9*\/\-,]+$/',  // month
    '/^[0-9*\/\-,L?#]+$/',  // day_of_week
];

foreach ($parts as $index => $part) {
    if (! preg_match($validPatterns[$index], $part)) {
        echo "Failed at part $index: $part\n";
    } else {
        echo "Passed part $index: $part\n";
    }
}
