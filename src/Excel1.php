<?php
namespace Zls\Action;
use Z;
/**
 * 导出数据为excel
 * @author 影浅-Seekwe
 * @link   seekwe@gmail.com
 * @since  0.0.1
 */
class Excel1
{
    private $utf16 = false;
    public function __construct()
    {
        $this->utf16 = function_exists('mb_convert_encoding');
    }
    public function headerCsv($filename = '')
    {
        if (!$filename) {
            $filename = date('Y-m-d H:i:s');
        } else {
            $filename = trim(strtolower($filename));
        }
        if (!Z::strEndsWith($filename, '.csv')) {
            $filename = $filename . 'csv';
        }
        Z::header('Expires:0');
        Z::header('Pragma:public');
        $ua = z::server("HTTP_USER_AGENT");
        if (preg_match("/MSIE/", $ua)) {
            Z::header('Content-Disposition: attachment; filename="' . $filename . '"');
        } elseif (preg_match("/Firefox/", $ua)) {
            Z::header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        } else {
            Z::header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        Z::header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        Z::header('Content-type: text/csv; charset=' . ($this->utf16 ? 'UTF-16LE' : 'UTF-8'));
    }
    public function setUtf8()
    {
        $this->utf16 = false;
    }
    public function exportCsv($data = [], $head = [], $fp = null)
    {
        //     $fp = fopen('php://output', 'a');
        // }
        if ($head) {
            echo ($this->utf16) ? chr(255) . chr(254) : chr(0xEF) . chr(0xBB) . chr(0xBF);
            if (is_string($head)) {
                $head = explode(',', $head);
            }
            echo $this->toLine($head);
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
                echo $this->toLine($data[$t]);
            }
        }
    }
    public function toLine($data)
    {
        $result = '';
        foreach ($data as $value) {
            $result .= $value . ",";
        }
        $result = substr( $result,0,-1);
        return ($this->utf16 ? mb_convert_encoding($result, "UTF-16LE", "UTF-8") : $result);
    }
}
