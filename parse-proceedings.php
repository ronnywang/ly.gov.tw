<?php

if (!file_exists(__DIR__ . '/proceedings')) {
    mkdir(__DIR__ . '/proceedings');
}

$parseContent = function($content){
    if (strpos(trim($content[0]), '：') === 0) {
        $content[0] = substr(trim($content[0]), strlen('：'));
    }

    if (trim($content[0]) == '行政院答復部分') {
        // TODO: https://lci.ly.gov.tw/LyLCEW/agendarec1/02/pdf/09/04/04/LCEWC03_090404.pdf
        array_shift($content);
    }
    if (strpos(trim($content[0]), '一、') === 0) {
    } elseif (strpos(trim($content[0]), '甲、') === 0) {
    } elseif (strpos(trim($content[0]), '壹、') === 0) {
    } else {
        var_dump($content);
        exit;
    }
    return $content;
};

$parse_proceed = function($input, $file) use ($parseContent){
    $content = '';
    $info = new StdClass;
    while (false !== ($line = fgets($input))) {
        $content .= $line;
    }
    $pages = explode(chr(12), $content);
    $lines = array();
    foreach ($pages as $pageno => $page) {
        $pageno = $pageno + 1;
        if (trim($page) == '') {
            continue;
        }

        $page_lines = explode("\n", trim($page));

        // 第一行一定是 立法院第 x 屆第 x 會期第 x 次會議議事錄
        if (preg_match('#^立法院.*議事錄$#', trim($page_lines[0]))) {
            array_shift($page_lines);
            //throw new Exception("第 {$pageno} 頁開頭第一行沒有頁首 xxx 議事錄");
        }

        // 最後一行一定是頁碼
        if (preg_match('#' . intval($pageno) . '$#', trim($page_lines[count($page_lines) - 1]))) {
            array_pop($page_lines);
            //throw new Exception("第 {$pageno} 頁最後一行沒有頁碼");
        }

        $lines = array_merge($lines, $page_lines);
    }

    // 前面一定有「議事錄」開頭
    while (true) {
        $line = array_shift($lines);
        if (trim($line) == '') {
            continue;
        }
        if (str_replace(' ', '', $line) != '議事錄') {
            var_dump($line);
            throw new Exception("第一行應該要是「議事錄」大標題");
        }
        $line = array_shift($lines);
        if (!preg_match('#立法院.*(全院委員會|談話會|會議)議事錄#', $line, $matches)) {
            throw new Exception("議事錄大標題後，接下來應該要有「立法院第x屆xxx議事錄」");
        }
        $info->title = trim($line);
        $type = $matches[1];
        break;
    }
    // require: 是否必要, alone: 標題獨自一行
    $sections = array(
        '會議' => array(
            array('step' => '時間', 'require' => true, 'alone' => false),
            array('step' => '地點', 'require' => true, 'alone' => false),
            array('step' => '出席委員', 'require' => true, 'alone' => false),
            array('step' => '請假委員', 'require' => false, 'alone' => false),
            array('step' => '列席官員', 'require' => false, 'alone' => false),
            array('step' => '主席', 'require' => true, 'alone' => false),
            array('step' => '列席', 'require' => true, 'alone' => false),
            array('step' => '記錄', 'require' => false, 'alone' => false), 
            array('step' => '紀錄', 'require' => false, 'alone' => false), 
            array('step' => '報告事項', 'require' => false, 'alone' => true),
            array('step' => '質詢事項', 'require' => false, 'alone' => true),
            array('step' => '討論事項', 'require' => false, 'alone' => true),
            array('step' => '其他事項', 'require' => false, 'alone' => true),
        ),
        '談話會' => array(
            array('step' => '時間', 'require' => true, 'alone' => false),
            array('step' => '地點', 'require' => true, 'alone' => false),
            array('step' => '出席委員', 'require' => true, 'alone' => false),
            array('step' => '請假委員', 'require' => false, 'alone' => false),
            array('step' => '列席官員', 'require' => false, 'alone' => false),
            array('step' => '主席', 'require' => true, 'alone' => false),
            array('step' => '列席', 'require' => true, 'alone' => false),
            array('step' => '記錄', 'require' => false, 'alone' => false), 
            array('step' => '紀錄', 'require' => false, 'alone' => false), 
            array('step' => '決定', 'require' => true, 'alone' => false),
        ),
    );
    $sections['全院委員會'] = $sections['會議'];

    for ($step_seq = 0; $step_seq < count($sections[$type]); $step_seq ++) {
        $step_info = $sections[$type][$step_seq];
        $step = $sections[$type][$step_seq]['step'];

        if (!count($lines)) {
            break;
        }
        $line = array_shift($lines);
        if (strpos(str_replace(' ', '', trim($line)), $step) !== 0) {
            print_r($info);
            throw new Exception("不是 {$step} 開頭");
        }
        if (!$step_info['alone']) { // step 後面還有東西
            for ($i = 0; $i < mb_strlen($step); $i ++) {
                $line = mb_substr(ltrim($line), 1, null, 'UTF-8');
            }
            $info->{$step} = array($line);
        } else {
            $info->{$step} = array();
        }

        $is_next_step = function($line) use ($sections, $step_seq, $type) {
            for ($i = $step_seq + 1; $i < count($sections[$type]); $i ++) {
                $next_step_info = $sections[$type][$i];
                $next_step = $next_step_info['step'];
                if ($next_step_info['alone'] and str_replace(' ', '', trim($line)) == $next_step) {
                    return $i;
                } elseif (!$next_step_info['alone'] and strpos(str_replace(' ', '', trim($line)), $next_step) === 0) {
                    return $i;
                }

                if ($next_step_info['require']) {
                    return false;
                }
            }
            return false;
        };

        while (count($lines)) {
            $line = $lines[0];
            if (false !== ($next_step_seq = $is_next_step($line))) {
                $step_seq = $next_step_seq - 1;
                break;
            }
            array_shift($lines);
            if (trim($line) != '') {
                $info->{$step}[] = $line;
            }
        }
    }

    for (; $step_seq < count($sections[$type]); $step_seq ++) {
        if ($sections[$type][$step_seq]['require']) {
            print_r($step);
            print_r($lines);
            print_r($info);
            throw new Exception("缺少 require 欄位");
        }
    }

    // TODO: 時間
    $info->{'地點'} = trim($info->{'地點'}[0]);
    foreach (array('出席委員', '請假委員') as $step) {
    }
    // TODO: 列席官員
    // TODO: 主席
    // TODO: 列席
    // TODO: 記錄
    if (property_exists($info, '紀錄') and !property_exists($info, '記錄')) {
        $info->{'記錄'} = $info->{'紀錄'};
        unset($info->{'紀錄'});
    }
    foreach (array('報告事項', '質詢事項', '討論事項', '其他事項', '決定') as $step) {
        if (property_exists($info, $step)) {
            $info->{$step} = $parseContent($info->{$step});
        }
    }

    return $info;
};

$fp = fopen(__DIR__ . '/data/proceedings.csv', 'r');
$columns = fgetcsv($fp);
$columns[0] = 'term';
while ($rows = fgetcsv($fp)) {
    $values = array_combine($columns, $rows);
    if ($values['term'] != '09') {
        continue;
    }

    $pdf_url = str_replace('/word/', '/pdf/', str_replace('.doc', '.pdf', $values['docUrl']));
    $target = __DIR__ . '/data/proceedings/' . basename($pdf_url);
    if (!file_exists($target)) {
        system(sprintf("wget -O %s %s", escapeshellarg($target), escapeshellarg($pdf_url)));
    }

    error_log($target . ' ' . $pdf_url);
    $pdf_input = popen(sprintf('pdftotext -layout %s /dev/stdout', escapeshellarg($target)), 'r');
    $info = $parse_proceed($pdf_input, $target);
    file_put_contents(__DIR__ . '/proceedings/' . basename($pdf_url) . '.json', json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}
