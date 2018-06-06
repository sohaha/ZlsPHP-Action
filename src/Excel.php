<?php

namespace Zls\Action;

use Z;

/**
 * 导出数据为excel
 * @author 影浅-Seekwe
 * @link   seekwe@gmail.com
 * @since  0.0.2
 */
class Excel
{
    public function headerCsv($filename = '')
    {
        if (!$filename) {
            $filename = date('Y-m-d H:i:s');
        }
        $filename = trim(strtolower($filename));
        if (!Z::strEndsWith($filename, '.csv')) {
            $filename = $filename . 'csv';
        }
        header("Content-type:text/csv");
        header("Content-Disposition:attachment;filename=" . $filename);
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
    }

    public function exportCsv($data = [], $head = [], $fp = null)
    {
        if (!$fp) {
            $fp = fopen('php://output', 'a');
            fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        }
        if (!$head && !$data) {
            fclose($fp);
        } else {
            if ($head) {
                if (is_string($head)) {
                    $head = explode(',', $head);
                }
                //foreach ($head as $i => $v) {$head[$i] = iconv('utf-8', 'gbk//IGNORE', $v);}
                fputcsv($fp, $head);
            }
            if ($data) {
                $cnt = 0;
                $limit = 100000;
                $count = count($data);
                for ($t = 0; $t < $count; $t++) {
                    $cnt++;
                    if ($limit == $cnt) {
                        ob_flush();
                        flush();
                        $cnt = 0;
                    }
                    $row = $data[$t];
                    //foreach ($row as $i => $v) {
                    //    $row[$i] = @iconv('utf-8', 'gbk//IGNORE', $v);
                    //    //也许有特殊字符编码呢
                    //    //    $row[$i] = mb_convert_encoding($v, 'gbk', 'utf-8');
                    //    //}
                    //}
                    fputcsv($fp, $row);
                    unset($row);
                }
            }
        }

        return $fp;
    }
}
