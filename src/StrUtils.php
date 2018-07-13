<?php

namespace Zls\Action;

/**
 * Zls\Action\StrUtils
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-07-13 11:55
 */
class StrUtils
{
    /**
     * uniqueId
     * 生成16位以上唯一ID
     * @param int    $length 不含前后缀的长度，最小14，建议16+
     * @param string $prefix 前缀
     * @param string $subfix 后缀
     * @return string $id
     */
    function uniqueId($prefix = '', $subfix = '', $length = 16)
    {
        if ($length < 14) {
            $length = 14;
        }
        $id = $prefix;
        $addLength = $length - 13;
        $id .= uniqid();
        $mtRand = function () use ($addLength) {
            return mt_rand(1 * pow(10, ($addLength)), 9 * pow(10, ($addLength)));
        };
        if (function_exists('random_bytes')) {
            try {
                $id .= substr(bin2hex(random_bytes(ceil(($addLength) / 2))), 0, $addLength);
            } catch (\Exception $e) {
                $id .= $mtRand();
            }
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $id .= substr(bin2hex(openssl_random_pseudo_bytes(ceil($addLength / 2))), 0, $addLength);
        } else {
            $id .= $mtRand();
        }

        return $id . $subfix;
    }

    /**
     * 加星号
     * @param        $str
     * @param int    $start
     * @param int    $end
     * @param string $dot
     * @param string $charset
     * @return string
     */
    public function stringStar($str, $start = 1, $end = 0, $dot = "*", $charset = "UTF-8")
    {
        $len = mb_strlen($str, $charset);
        if ($start == 0 || $start > $len) {
            $start = 1;
        }
        if ($end != 0 && $end > $len) {
            $end = $len - 2;
        } elseif ($end == $len - 1) {
            $end = 0;
        }
        $endStart = $len - $end;
        $top = mb_substr($str, 0, $start, $charset);
        $bottom = "";
        if ($endStart > 0) {
            $bottom = mb_substr($str, $endStart, $end, $charset);
        }
        $len = $len - mb_strlen($top, $charset);
        $len = $len - mb_strlen($bottom, $charset);
        $newStr = $top;
        for ($i = 0; $i < $len; $i++) {
            $newStr .= $dot;
        }
        $newStr .= $bottom;

        return $newStr;
    }

    /**
     * 随机OPENID
     * @return string
     */
    public function openid()
    {
        return 'o7nWYj' . $this->randString(22, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_');
    }

    /**
     * 产生随机字符串
     * @param int    $length
     * @param string $chars
     * @return string
     */
    public function randString($length = 4, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|')
    {
        $hash = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }

        return $hash;
    }

    /**
     * 取汉字的第一个字的首字母
     * @param $str
     * @return null|string
     */
    public function firstCharter($str)
    {
        if (empty($str)) {
            return '';
        }
        $fchar = ord($str{0});
        if ($fchar >= ord('A') && $fchar <= ord('z')) {
            return strtoupper($str{0});
        }
        $s1 = iconv('UTF-8', 'gb2312', $str);
        $s2 = iconv('gb2312', 'UTF-8', $s1);
        $s = $s2 == $str ? $s1 : $str;
        $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
        if ($asc >= -20319 && $asc <= -20284) {
            return 'A';
        }
        if ($asc >= -20283 && $asc <= -19776) {
            return 'B';
        }
        if ($asc >= -19775 && $asc <= -19219) {
            return 'C';
        }
        if ($asc >= -19218 && $asc <= -18711) {
            return 'D';
        }
        if ($asc >= -18710 && $asc <= -18527) {
            return 'E';
        }
        if ($asc >= -18526 && $asc <= -18240) {
            return 'F';
        }
        if ($asc >= -18239 && $asc <= -17923) {
            return 'G';
        }
        if ($asc >= -17922 && $asc <= -17418) {
            return 'H';
        }
        if ($asc >= -17417 && $asc <= -16475) {
            return 'J';
        }
        if ($asc >= -16474 && $asc <= -16213) {
            return 'K';
        }
        if ($asc >= -16212 && $asc <= -15641) {
            return 'L';
        }
        if ($asc >= -15640 && $asc <= -15166) {
            return 'M';
        }
        if ($asc >= -15165 && $asc <= -14923) {
            return 'N';
        }
        if ($asc >= -14922 && $asc <= -14915) {
            return 'O';
        }
        if ($asc >= -14914 && $asc <= -14631) {
            return 'P';
        }
        if ($asc >= -14630 && $asc <= -14150) {
            return 'Q';
        }
        if ($asc >= -14149 && $asc <= -14091) {
            return 'R';
        }
        if ($asc >= -14090 && $asc <= -13319) {
            return 'S';
        }
        if ($asc >= -13318 && $asc <= -12839) {
            return 'T';
        }
        if ($asc >= -12838 && $asc <= -12557) {
            return 'W';
        }
        if ($asc >= -12556 && $asc <= -11848) {
            return 'X';
        }
        if ($asc >= -11847 && $asc <= -11056) {
            return 'Y';
        }
        if ($asc >= -11055 && $asc <= -10247) {
            return 'Z';
        }

        return null;
    }
}
