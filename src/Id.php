<?php

namespace Zls\Action;

use Z;

class Id
{
    const SPREAD = '_';
    protected $secret = 'zls';
    protected $offset = 5;
    protected $randomLength = 4;
    protected $prefix = '';

    public function set($secret, int $offset, int $randomLength)
    {
        $this->secret       = $secret;
        $this->offset       = $offset;
        $this->randomLength = $randomLength;
    }

    /**
     * 设置前缀
     * @param $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * uniqueId
     * 生成16位以上唯一ID.
     * @param int    $length 不含前后缀的长度，最小14，建议16+
     * @param string $subfix 后缀
     * @return string $id
     */
    public function uniqueId($length = 16, $subfix = '')
    {
        if ($length < 14) {
            $length = 14;
        }
        $id        = $this->prefix;
        $addLength = $length - 13;
        $id        .= uniqid();
        $mtRand    = function () use ($addLength) {
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

    public function encode($id, $prefix = '')
    {
        $isNumeric = function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
            $pass = Z::arrayKeyExists($key, $data);
            if ($pass) {
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = is_numeric($value);
                    if (!$okay) {
                        $pass = false;
                    }
                }
            }

            return !$pass;
        };
        if (z::checkValue($id, ['function' => $isNumeric])) {
            $offsetId        = $id + $this->offset;
            $rand            = $this->randInt();
            $signatureString = $prefix . $rand . $offsetId;
            $signature       = $this->signature($signatureString);
            $header          = substr($signature, $this->offset % 2, strlen($rand . $offsetId));

            return $this->Bencode($header . self::SPREAD . $signatureString);
        } else {
            return false;
        }
    }

    public function randInt()
    {
        $n = '';
        $i = $this->randomLength;
        while ($i--) {
            $n .= mt_rand(1, 9);
        }

        return (int)$n;
    }

    protected function signature($signatureString)
    {
        return hash_hmac('SHA256', $signatureString, $this->secret);
    }

    public function Bencode($string)
    {
        return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
    }

    public function decode($encodedId, $prefix = '')
    {
        $raw   = $this->Bdecode($encodedId);
        $attrs = explode(self::SPREAD, $raw);
        if (count($attrs) !== 2) {
            return '';
        }
        list($header, $signatureString) = $attrs;
        $offsetId = substr($signatureString, strlen($prefix) + $this->randomLength);

        return $this->checkHeader($header, $signatureString, strlen($offsetId)) ? $offsetId - $this->offset : '';
    }

    public function Bdecode($string)
    {
        return base64_decode(strtr($string, '-_', '+/'));
    }

    protected function checkHeader($header, $signatureString, $offsetLen)
    {
        $signature = $this->signature($signatureString);

        return !($header !== substr($signature, $this->offset % 2, $this->randomLength + $offsetLen));
    }

}
