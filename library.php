<?php

class Helper
{
    public static function getNames()
    {
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

        return $names;
    }

    public static function check_persons($person)
    {
        $names = self::getNames();
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
                'Uliw．Qaljupayare' => '簡東明Uliw．Qaljupayare',
                'Uliw．Aljupayare' => '簡東明Uliw．Qaljupayare',

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
    }
}
