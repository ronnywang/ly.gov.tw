<?php

class InvalidFileException extends Exception {}

if (!file_exists(__DIR__ . '/proposal')) {
    mkdir(__DIR__ . '/proposal');
}

$fp = fopen(__DIR__ . '/data/history-legislator.csv', 'r');
fgetcsv($fp);
$names = array();
while ($rows = fgetcsv($fp)) {
    if (array_key_exists($rows[1], $names)) {
        //throw new Exception("{$rows[1]} 名字出現兩次");
    }
    $names[$rows[1]] = true;
}
fclose($fp);

$check_persons = function($person) use ($names){
    $origin_person = $person;
    $persons = array();
    $person = str_replace('　', '', $person);
    $person = str_replace(' ', '', $person);
    $person = str_replace(' ', '', $person);
    $person = str_replace('‧', '．', $person);
    // https://lci.ly.gov.tw/LyLCEW/agenda1/02/pdf/09/04/01/LCEWA01_090401_00013.pdf
    $person = str_replace('〄', '．', $person);
    $person = str_replace('〃', '．', $person);
    $person = str_replace("\r", '', $person);
    $person = str_replace("\x0c", '', $person);
    while (strlen($person)) {
        $person = trim($person);
        foreach (array_keys($names) as $n) {
            if (stripos($person, $n) === 0) {
                $person = substr($person, strlen($n));
                $persons[] = $n;
                continue 2;
            }
        }

        // 特例
        foreach (array(
            '民主進步黨立法院黨團' => '民主進步黨立法院黨團',
            '親民黨立法院黨團' => '親民黨立法院黨團',
            '親民黨立院黨團' => '親民黨立法院黨團',
            '親民黨黨團立法院黨團' => '親民步黨立法院黨團',
            '時代力量立法院黨團' => '時代力量立法院黨團',
            '中國國民黨立法院黨團' => '中國國民黨立法院黨團',

            'Kolasotaka' => '谷辣斯．尤達卡Kolas Yotaka',
            'Kolas Yotaka' => '谷辣斯．尤達卡Kolas Yotaka',
            'kolas Yotaka' => '谷辣斯．尤達卡Kolas Yotaka',
            'Kolas  Yotaka' => '谷辣斯．尤達卡Kolas Yotaka',
            'Kolas  Yotaka' => '谷辣斯．尤達卡Kolas Yotaka',
            'KolasYotaka' => '谷辣斯．尤達卡Kolas Yotaka',
            'Kolas   Yotaka' => '谷辣斯．尤達卡Kolas Yotaka',
            'Kolas' => '谷辣斯．尤達卡Kolas Yotaka',
            'Yotaka' => '谷辣斯．尤達卡Kolas Yotaka',
            'Yotak' => '谷辣斯．尤達卡Kolas Yotaka',
            '．Yotaka' => '谷辣斯．尤達卡Kolas Yotaka',

            '高潞以用巴魕剌Kawlo．Iyun．Pacidal' => '高潞．以用．巴魕剌Kawlo．Iyun．Pacidal',
            '高潞．以用．巴魕剌Kawlo．Iyun．acidal' => '高潞．以用．巴魕剌Kawlo．Iyun．Pacidal',
            '高潞．以用．巴魕剌KawloIyunPacidal' => '高潞．以用．巴魕剌Kawlo．Iyun．Pacidal',
            '高潞以用巴魕剌kawlo．Iyun．Pacidal' => '高潞．以用．巴魕剌Kawlo．Iyun．Pacidal',
            '高潞以用巴魕剌Kawlo．Iyun．Pacida' => '高潞．以用．巴魕剌Kawlo．Iyun．Pacidal',
            '高潞以用巴魕剌Kawlo．Iyun．acidal' => '高潞．以用．巴魕剌Kawlo．Iyun．Pacidal',
            '高潞．以用．巴魕剌Kawlo．Iyun．Pacida' => '高潞．以用．巴魕剌Kawlo．Iyun．Pacidal',

            '鄭天財' => '鄭天財Sra．Kacaw',
            'Sra Kacaw' => '鄭天財Sra．Kacaw',
            'SraKacaw' => '鄭天財Sra．Kacaw',
            'Sra  Kacaw' => '鄭天財Sra．Kacaw',

            '簡東明' => '簡東明Uliw．Qaljupayare',

            '廖國棟' => '廖國棟Sufin．Siluko',
            'Sufin．Siluko' => '廖國棟Sufin．Siluko',

            // 錯字系列
            '王惠' => '王惠美',
            '鐘孔炤' => '鍾孔炤',
            '趙正孙' => '趙正宇',
            '王定孙' => '王定宇',
            '王金帄' => '王金平',
            '蔡宜津' => '葉宜津',
            '陳賴素' => '陳賴素美',
            '孔文卲' => '孔文吉',
            '陳 瑩' => '陳瑩',
            '楊 曜' => '楊曜',
            '（柯代）' => '',
            '（代）' => '',

            '[pic]' => '',
            '\\' => '',
            '=' => '',
        ) as $n => $t) {
            if (stripos($person, $n) === 0) {
                $person = substr($person, strlen($n));
                if ($t) {
                    $persons[] = $t;
                }
                continue 2;
            }
        }

        if (preg_match('#^[^ ]*總說明#u', $person)) {
            break;
        }
        var_dump('剩下:' . urlencode($person));
        var_dump('原文:' . $origin_person);
        throw new Exception("{$person} 被中斷了");
    }
    return array_values(array_unique($persons));
};

$parse_proposal = function($input, $file) use ($check_persons) {
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
        $line = fgets($input);
        if (false === $line) {
            throw new Exception("找不到印發日");
        }
        if (trim($line) == '') {
            continue;
        }

        $line = str_replace('　', '', $line);

        if (preg_match('#中華民國(\d*)年(\d*)月(\d*)日?印發#u', str_replace(' ', '', $line), $matches)) {
            $published_date = sprintf("%04d-%02d-%02d", $matches[1] + 1911, $matches[2], $matches[3]);
            break;
        }
    }
    if (!$published_date) {
        throw new Exception("{$file} 找不到印發時間");
    }

    // 再來應該要有院總第xxx號?
    $found = false;
    $proposal_nos = array();
    $yun_number = null;
    while (true) {
        $line = fgets($input);
        if (trim($line) == '') {
            continue;
        }

        $line = str_replace('　', '', $line);

        if (preg_match('#^\d+$#', trim($line))) {
            $proposal_nos[] = trim($line);
            continue;
        } elseif (preg_match('#^政府\s+(\d+)$#', trim($line), $matches)) {
            $proposal_nos[] = $matches[1];
            continue;
        } elseif (trim($line) == '政府') {
            continue;
        }

        if (preg_match('#院總第\s*(\d+)\s*號#', $line, $matches)) {
            $yun_number = $matches[1];
            $found = true;
            break;
        }
        $proposal_nos = array();
    }

    if (!$found) {
        throw new Exception("{$file} 找不到院總第開頭");
    }
    if (preg_match('#政府提案第(\d+)號#', str_replace(' ', '',$line), $matches)) {
        return array(
            'published_date' => $published_date,
            '提案號' => $matches[1],
            '院總第' => $yun_number,
        );
    }
    if (!preg_match('#委員提案第(\d+)號#', str_replace(' ', '',$line), $matches)) {
        while (true) {
            $line = fgets($input);
            if (preg_match('#^\d+$#', trim($line))) {
                $proposal_nos[] = trim($line);
            } elseif (preg_match('#提案第\s*(\d+)\s*號#', $line, $matches)) {
                $proposal_nos[] = $matches[1];
            } elseif (trim($line) == '提案第') {
                continue;
            } elseif (strpos(trim($line), '號之') === 0) {
                continue;
            } else {
                break;
            }
        }
        return array(
            'published_date' => $published_date,
            '提案號' => implode(',', $proposal_nos),
            '院總第' => $yun_number,
        );
    } else {
        $proposal_no = $matches[1];
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
            $follower .= $line;
            break;
        default:
            throw new Exception("{$file} 未知的 state: " . $state);
        }
    }

    $proposer = $check_persons($proposer);
    $follower = $check_persons($follower);

    return array(
        '案由' => $reason,
        '提案人' => $proposer,
        '連署人' => $follower,
        'published_date' => $published_date,
        '提案號' => $proposal_no,
        '院總第' => $yun_number,
    );

};

// 處理提案
$fp = fopen('data/proposal.csv', 'r');
$columns = fgetcsv($fp);
$c = 0;
while ($rows = fgetcsv($fp)) {
    $values = array_combine($columns, $rows);
    unset($values['']);
    if ($values['term'] != '09') {
        continue;
    }

    $pdfTarget = __DIR__ . '/data/proposal/' . $values['billNo'] . '.pdf';
    if (!$values['pdfUrl']) 
        continue;

    if (!file_exists($pdfTarget) or !filesize($pdfTarget)) {
        $values['pdfUrl'] = str_replace('http://', 'https://', $values['pdfUrl']);
        error_log("geting {$pdfTarget}");
        system(sprintf("wget --timeout 3 -O %s %s", escapeshellarg($pdfTarget), escapeshellarg($values['pdfUrl'])));
    }

    $c ++;
    if (array_key_exists(1, $_SERVER['argv']) and $_SERVER['argv'][1] > $c) {
        continue;
    }
    error_log("{$c} {$rows[0]} {$rows[1]} {$rows[2]} " . $pdfTarget . ' ' . $values['pdfUrl']);

    $cmd = 'pdftotext -layout ' . escapeshellarg($pdfTarget) . ' /dev/stdout';
    $pdf_input = popen($cmd, 'r');
    try {
        $ret = $parse_proposal($pdf_input, $pdfTarget);
    } catch (InvalidFileException $e) {
        error_log("{$pdfTarget} 不合法 pdf: $cmd");
        unlink($pdfTarget);
        continue;
    }

    file_put_contents('proposal/' . $values['billNo'] . '.json', json_encode(array('csv' => $values, 'pdf' => $ret), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    //echo json_encode(array('csv' => $values, 'pdf' => $ret), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
