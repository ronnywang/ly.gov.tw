<?php

if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data');
}
// 委員名單：https://data.ly.gov.tw/getds.action?id=9
$target = __DIR__ . '/data/legislator.csv';
if (!file_exists($target)) {
    system("wget -O " . escapeshellarg($target) . ' ' . escapeshellarg('http://data.ly.gov.tw/odw/usageFile.action?id=9&type=CSV&fname=9_CSV.csv'));
}

// 歷史委員名單: https://data.ly.gov.tw/getds.action?id=16
$target = __DIR__ . '/data/history-legislator.csv';
if (!file_exists($target)) {
    system("wget -O " . escapeshellarg($target) . ' ' . escapeshellarg('http://data.ly.gov.tw/odw/usageFile.action?id=16&type=CSV&fname=16_CSV.csv'));
}

// 報告事項: https://data.ly.gov.tw/getds.action?id=3
$target = __DIR__ . '/data/report.csv';
if (!file_exists($target)) {
    system("wget -O " . escapeshellarg($target) . ' ' . escapeshellarg('http://data.ly.gov.tw/odw/usageFile.action?id=3&type=CSV&fname=3_CSV.csv'));
}

// 提案事項: https://data.ly.gov.tw/getds.action?id=20
$target = __DIR__ . '/data/proposal.csv';
if (!file_exists($target)) {
    system("wget -O " . escapeshellarg($target) . ' ' . escapeshellarg('http://data.ly.gov.tw/odw/usageFile.action?id=20&type=CSV&fname=20_CSV.csv'));
}

// 議事錄: https://data.ly.gov.tw/getds.action?id=45
$target = __DIR__ . '/data/proceedings.csv';
if (!file_exists($target)) {
    system("wget -O " . escapeshellarg($target) . ' ' . escapeshellarg('http://data.ly.gov.tw/odw/usageFile.action?id=45&type=CSV&fname=45_CSV.csv'));
}
