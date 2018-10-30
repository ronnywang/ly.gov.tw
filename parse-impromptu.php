<?php

include(__DIR__ . '/library.php');
class InvalidFileException extends Exception {}

if (!file_exists(__DIR__ . '/impromptu')) {
    mkdir(__DIR__ . '/impromptu');
}

$parse_impromptu = function($input, $file) {
    // 先找到「立法院議案關係文書」
    $found = false;
    while (true) {
        $line = fgets($input);
        if (false === $line) {
            throw new InvalidFileException("不合法 pdf");
        }
        if (trim($line) == '') {
            continue;
        }

        $line = str_replace('　', '', $line);

        if (strpos(trim($line), '立法院議案關係文書') !== false) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        throw new Exception("{$file} 找不到「立法院議案關係文書]");
    }

    // 再來應該要有 中華民國101年5月30日印發
    $published_date = false;
    while (true) {
        if (false === $line) {
            throw new Exception("找不到印發日");
        }
        if (trim($line) == '') {
            $line = fgets($input);
            continue;
        }

        $line = str_replace('　', '', $line);

        if (preg_match('#中華民國(\d*)年(\d*)月(\d*)日?印發#u', str_replace(' ', '', $line), $matches)) {
            $published_date = sprintf("%04d-%02d-%02d", $matches[1] + 1911, $matches[2], $matches[3]);
            break;
        }

        $line = fgets($input);
    }
    if (!$published_date) {
        throw new Exception("{$file} 找不到印發時間");
    }

    // 再來應該是案由： 開頭
    $reason = '';
    while (true) {
        $line = fgets($input);
        if (trim($line) == '') {
            continue;
        }

        $line = str_replace('　', '', $line);
        $line = trim($line);
        // http://lci.ly.gov.tw/LyLCEW/agenda1/02/pdf/09/04/01/LCEWA01_090401_00109.pdf
        $line = str_replace('〆', '：', $line);

        if (strpos(str_replace(' ', '', $line), '案由：') === false) {
            throw new Exception("預期要有 案由： 開頭 ，結果出現 {$line}");
        }
        $reason = explode('：', $line, 2)[1];
        break;
    }

    // 再來應該要有連署人、提案人，才會跑到 | 開頭
    $state = '案由';
    $proposer = '';
    $follower = '';
    while (true) {
        $line = fgets($input);
        if (false === $line) {
            break;
        }
        if (trim($line) == '') {
            continue;
        }
        // 只有數字，當作頁碼
        if (preg_match('#^\x0c?\d+$#', trim($line))) {
            continue;
        }

        if (preg_match('#^[委討]\s*[0-9]+$#u', trim($line))) {
            continue; // 頁碼
        } elseif (preg_match('#立法院第 \d* 屆第 \d* 會期第 \d* 次(臨時會第 \d* 次)?會議議案關係文書#', trim($line))) {
            // 立法院第9屆第2會期第1次臨時 會第1次會議議案關係文書
            continue;
        } elseif (trim($line) == ',') {
            continue;
        }

        if (in_array($state, array('提案人', '連署人'))) {
            foreach (array(
                '說明',
                '通則',
                '條例',
                '對照表',
                '草案',
                '條文',
                '增訂',
                '施行法',
                '規程',
                '附圖一：', // https://lci.ly.gov.tw/LyLCEW/agenda1/02/pdf/09/01/16/LCEWA01_090116_00027.pdf
                '附錄',
                '附表',
                '附件',
                '註 1：',
                '促進轉型正義', // https://lci.ly.gov.tw/LyLCEW/agenda1/02/pdf/09/01/14/LCEWA01_090114_00065.pdf
            ) as $keyword) {
                if (strpos($line, $keyword) !== false) {
                    break 2;
                }
            }
            if (in_array(str_replace(' ', '', trim($line)), array('監試法', '工廠法'))) {
                // https://lci.ly.gov.tw/LyLCEW/agenda1/02/pdf/09/01/15/LCEWA01_090115_00021.pdf
                // https://lci.ly.gov.tw/LyLCEW/agenda1/02/pdf/09/04/08/LCEWA01_090408_00027.pdf
                break;
            }
            if (preg_match('#^[^ ]*法$#', trim($line))) {
                break;
            }
            if (preg_match('#第[一二三四五六七八九十百千零]+[章條]#u', $line)) {
                break;
            }
        }

        $line = str_replace('聯署人：', '連署人：', $line);
        // https://lci.ly.gov.tw/LyLCEW/agenda1/02/pdf/09/04/13/LCEWA01_090413_00029.pdf
        $line = str_replace('連罫人：', '連署人：', $line);
        $line = str_replace('人〆', '人：', $line);

        $line = str_replace('　', '', $line);
        $line = str_replace(chr(12), '', $line); // page break

        if (strpos(trim($line), '|') === 0) {
            if (in_array($state, array('提案人', '連署人'))) {
                break;
            }
        }

        $line = trim($line);
        switch ($state) {
        case '案由':
            if (strpos($line, '提案人：') === 0) {
                $state = '提案人';
                $proposer = trim(explode('：', $line, 2)[1]);
                break;
            } elseif (strpos($line, '連署人：') === 0) {
                print_r($reason);
                throw new Exception("{$file} 案由完後沒有提案人就跑到連署人了？");
            }
            $reason .= $line;
            break;

        case '提案人':
            if (strpos($line, '連署人：') === 0) {
                $state = '連署人';
                $follower = trim(explode('：', $line, 2)[1]);
                break;
            }
            $proposer .= $line;
            break;
        case '連署人':
            if (preg_match('#^--+$#', trim($line))) {
                break 2;
            }
            $follower .= $line;
            break;
        default:
            throw new Exception("{$file} 未知的 state: " . $state);
        }
    }

    $proposer = Helper::check_persons($proposer);
    $follower = Helper::check_persons($follower);

    return array(
        '案由' => $reason,
        '提案人' => $proposer,
        '連署人' => $follower,
        'published_date' => $published_date,
    );

};

// 處理提案
$fp = fopen('data/impromptu.csv', 'r');
$columns = fgetcsv($fp);
$c = 0;
$columns[0] = 'term';
while ($rows = fgetcsv($fp)) {
    $values = array_combine($columns, $rows);
    unset($values['']);
    if ($values['term'] != '09') {
        continue;
    }

    $docTarget = __DIR__ . '/data/impromptu/' . basename($values['docUrl']);
    if (!$values['docUrl']) 
        continue;

    if (!file_exists($docTarget) or !filesize($docTarget)) {
        $values['docUrl'] = str_replace('http://', 'https://', $values['docUrl']);
        error_log("geting {$docTarget}");
        system(sprintf("wget --timeout 3 -O %s %s", escapeshellarg($docTarget), escapeshellarg($values['docUrl'])));
    }

    $c ++;
    if (array_key_exists(1, $_SERVER['argv']) and $_SERVER['argv'][1] > $c) {
        continue;
    }
    error_log("{$c} {$rows[0]} {$rows[1]} {$rows[2]} " . $docTarget . ' ' . $values['docUrl']);

    if (strpos($docTarget, '.pdf')) {
        $cmd = 'pdftotext -layout ' . escapeshellarg($docTarget) . ' /dev/stdout';
        $input = popen($cmd, 'r');
    } else {
        $cmd = "./node_modules/.bin/textract " . escapeshellarg($docTarget);
        $input = popen($cmd, 'r');
    }
    try {
        $ret = $parse_impromptu($input, $docTarget);
    } catch (InvalidFileException $e) {
        error_log("{$docTarget} 不合法文件: $cmd");
        unlink($docTarget);
        continue;
    }

    file_put_contents('impromptu/' . basename($values['docUrl']) . '.json', json_encode(array('csv' => $values, 'doc' => $ret), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    //echo json_encode(array('csv' => $values, 'pdf' => $ret), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
