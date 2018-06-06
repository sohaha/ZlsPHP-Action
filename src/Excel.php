<?php

namespace Zls\Action;

use Z;

/**
 * 导出数据为excel
 * @author 影浅-Seekwe
 * @link   seekwe@gmail.com
 * @since  0.0.1
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
            $filename = $filename . '.csv';
        }
        Z::header("Content-type:text/csv");
        Z::header("Content-Disposition:attachment;filename=" . $filename);
        Z::header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        Z::header('Expires:0');
        Z::header('Pragma:public');
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
                    fputcsv($fp, $data[$t]);
                }
            }
        }

        return $fp;
    }
}
