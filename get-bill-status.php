<?php

include(__DIR__ . '/library.php');
$fp = fopen(__DIR__ . '/data/proposal.csv', 'r');
$columns = fgetcsv($fp);
$columns[0] = 'term';

if (!file_exists(__DIR__ . '/data/bill-detail')) {
    mkdir(__DIR__ . '/data/bill-detail');
}

while ($rows = fgetcsv($fp)) {
    $values = array_combine($columns, $rows);
    if ($values['term'] != '09') {
        continue;
    }
    $target = __DIR__ . '/data/bill-detail/'. $values['billNo'] . '.html';
    if (!file_exists($target) or !filesize($target)) {
        system(sprintf("wget -O %s %s", escapeshellarg($target), escapeshellarg('https://misq.ly.gov.tw/MISQ/IQuery/misq5000QueryBillDetail.action?billNo=' . urlencode($values['billNo']))));
    }

    error_log($target);
    $content = file_get_contents($target);
    if (strpos($content, iconv('utf-8', 'big5', '系統錯誤頁'))) {
        continue;
    }
    $doc = new DOMDocument;
    @$doc->loadHTML($content);
    $detail_table_dom = null;
    foreach ($doc->getElementsByTagName('table') as $table_dom) {
        if (strpos($table_dom->getAttribute('class'), 'brown-list') !== false) {
            $detail_table_dom = $table_dom;
            break;
        }
    }

    if (is_null($detail_table_dom)) {
        throw new Exception("找不到 table.brown-list");
    }

    $json_target = __DIR__ . '/bill-detail/' . $values['billNo'] . '.json';
    if (file_exists($json_target)) {
        continue;
    }
    $info = new StdClass;
    foreach ($detail_table_dom->getElementsByTagName('tbody')->item(0)->childNodes as $tr_dom) {
        if ($tr_dom->nodeName != 'tr') {
            continue;
        }
        $th_dom = $tr_dom->getElementsByTagName('th')->item(0);
        $title = $th_dom->nodeValue;

        $td_dom = $tr_dom->getElementsByTagName('td')->item(0);
        if ($title == '相關附件') {
            $attachments = array();
            foreach ($td_dom->getElementsByTagName('a') as $a_dom) {
                $attachment = new StdClass;
                $attachment->{'連結'} = $a_dom->getAttribute('href');
                $attachment->{'名稱'} = trim($a_dom->getElementsByTagName('span')->item(0)->nodeValue);
                $attachments[] = $attachment;
            }
            $info->{$title} = $attachments;
        } elseif ($title == '關連議案') {
            $bills = array();
            foreach ($td_dom->getElementsByTagName('a') as $a_dom) {
                if (!preg_match("#queryBillDetail\('(\d+)','(\d+)'\)#", $a_dom->getAttribute('onclick'), $matches)) {
                    continue;
                }
                $bill = new StdClass;
                $bill->{'billNo'} = $matches[1];
                $bill->{'連結'} = 'https://misq.ly.gov.tw/MISQ/IQuery/misq5000QueryBillDetail.action?billNo=' . $matches[1];
                $bill->{'名稱'} = preg_replace('#\s#', '', trim($a_dom->nodeValue));
                $bills[] = $bill;
            }
            $info->{$title} = $bills;
        } elseif (in_array($title, array('提案人', '連署人'))) {
            if (!preg_match("#getLawMakerName\('(proposal|petition)', '([^']*)'#", $td_dom->nodeValue, $matches)) {
                $info->{$title} = array();
                continue;
            }
            $persons = str_replace('&nbsp;', '', $matches[2]);

            // https://misq.ly.gov.tw/MISQ/IQuery/misq5000QueryBillDetail.action?billNo=1050923070200500
            // https://misq.ly.gov.tw/MISQ/IQuery/misq5000QueryBillDetail.action?billNo=1051109070200400
            if (preg_match('#「(.*)」，請審議案。#', $info->{'議案名稱'}, $matches)) {
                $persons = str_replace($matches[1], '', $persons);
            }

            //https://misq.ly.gov.tw/MISQ/IQuery/misq5000QueryBillDetail.action?billNo=1051201070200100
            $persons = preg_replace('#附錄：.*#', '', $persons);

            $info->{$title} = Helper::check_persons($persons);
        } elseif ('議案流程' == $title) {
            $flow_table = $td_dom->getElementsByTagName('tbody')->item(0);
            $flows = array();
            foreach ($flow_table->getElementsByTagName('tr') as $tr_dom) {
                $td_doms = $tr_dom->getElementsByTagName('td');

                $flow = new StdClass;
                $flow->{'會期'} = trim($td_doms->item(0)->nodeValue);
                $flow->{'日期'} = array();
                foreach ($td_doms->item(1)->getElementsByTagName('a') as $a_dom) {
                    if (preg_match("#queryMeetingDetail(ByTerm)?\('([^']*)','(\d+)/(\d+)/(\d+)'#", $a_dom->getAttribute('onclick'), $matches)) {
                        $flow->{'日期'}[] = array($matches[2], sprintf("%04d-%02d-%02d", $matches[3] + 1911, $matches[4], $matches[5]));
                    } elseif (preg_match("#queryMeetingDetail(ByTerm)?\('\d*-\d*-',''#", $a_dom->getAttribute('onclick'))) {
                    } else {
                        echo $a_dom->getAttribute('onclick') . "\n";
                        print_r($doc->saveHTML($td_doms->item(1)));
                        throw new Exception("找不到日期");
                    }
                }

                if (!$flow->{'日期'}) {
                    $yyymmdd = trim($td_doms->item(1)->getElementsByTagName('div')->item(0)->childNodes->item(0)->nodeValue);
                    if (preg_match('#\d+/\d+/\d+#', $yyymmdd)) {
                        $yyymmdd = explode('/', $yyymmdd);
                        $flow->{'日期'}[] = array('', sprintf("%04d-%02d-%02d", $yyymmdd[0] + 1911, $yyymmdd[1], $yyymmdd[2]));
                    }
                }

                $flow->{'院會/委員會'} = trim($td_doms->item(2)->nodeValue);
                $flow->{'狀態'} = trim($td_doms->item(3)->nodeValue);
                $flow->{'狀態'} = preg_replace('/\s+/', '', $flow->{'狀態'});
                $flow->{'狀態'} = preg_replace("#//changeName\('\d+'\);#", '', $flow->{'狀態'});
                $flows[] = $flow;
            }
            $info->{$title} = $flows;
        } else {
            $info->{$title} = trim($td_dom->nodeValue);
        }
    }
    file_put_contents($json_target, json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}
