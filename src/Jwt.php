<?php

namespace Zls\JWT;

use Z;

class Jwt
{
    public static $supportedAlgs = [
        'ES384' => ['openssl', 'SHA384'],
        'ES256' => ['openssl', 'SHA256'],
        'HS256' => ['hash_hmac', 'SHA256'],
        'HS384' => ['hash_hmac', 'SHA384'],
        'HS512' => ['hash_hmac', 'SHA512'],
        'RS256' => ['openssl', 'SHA256'],
        'RS384' => ['openssl', 'SHA384'],
        'RS512' => ['openssl', 'SHA512'],
        'EdDSA' => ['sodium_crypto', 'EdDSA'],
    ];

    /**
     * 创建数据体
     */
    public static function genPayload(array $payload, int $expire = 7200): array
    {
        $now = time();
        return array_merge($payload, ['exp' => $now + $expire, 'iat' => $now]);
    }

    /**
     * 编码
     * @param array $payload 数据体   格式如下非必须
     * [
     *  'iss'=>'manage',  // 签发者
     *  'iat'=>time(),  // 签发时间
     *  'exp'=>time()+7200,  // 过期时间
     *  'nbf'=>time()+60,  // 该时间之前不接收处理
     *  'sub'=>'zls',  // 面向的用户
     *  'jti'=>md5(uniqid('zzz').time())  // Token 唯一标识
     * ]
     * @return bool|string
     */
    public static function encode(array $payload, $key, $alg = 'HS256')
    {
        $base64header = self::base64UrlEncode(json_encode(['alg' => $alg, 'typ' => 'JWT'], ZLS_JSON_UNESCAPED));
        $base64payload = self::base64UrlEncode(json_encode($payload, ZLS_JSON_UNESCAPED));
        $algo = Z::arrayGet(self::$supportedAlgs, $alg, ['hash_hmac', 'SHA256']);
        $signature = self::sign($base64header . '.' . $base64payload, $key, $algo);
        if (!$signature) {
            return false;
        }
        return $base64header . '.' . $base64payload . '.' . $signature;
    }


    /**
     * 解码
     */
    public static function decode(string $token, $pubkey)
    {
        $tokens = explode('.', $token);
        if (count($tokens) === 3) {
            list($base64header, $base64payload, $sign) = $tokens;
            $header = json_decode(self::base64UrlDecode($base64header), ZLS_JSON_UNESCAPED);
            if (!empty($header['alg'])) {
                $alg = $header['alg'];
                $algo = Z::arrayGet(self::$supportedAlgs, $alg, ['hash_hmac', 'SHA256']);
                $verify = self::verify($sign, $base64header . '.' . $base64payload, $pubkey, $algo);
                if ($verify) {
                    $payload = json_decode(self::base64UrlDecode($base64payload), ZLS_JSON_UNESCAPED);
                    $now = time();
                    if (!((isset($payload['iat']) && $payload['iat'] > $now) || (isset($payload['exp']) && $payload['exp'] < $now) || (isset($payload['nbf']) && $payload['nbf'] > $now))) {
                        return $payload;
                    }
                }
            }
        }
        return false;
    }

    private static function base64UrlEncode(string $input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    private static function base64UrlDecode(string $input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $addlen = 4 - $remainder;
            $input .= str_repeat('=', $addlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    private static function verify(string $sign, $input, string $pubkey, array $algo): bool
    {
        $verify = false;
        list($func, $alg) = $algo;
        switch ($func) {
            case 'hash_hmac':
                $verify = hash_equals($sign, self::sign($input, $pubkey, $algo));
                break;
            case 'openssl':
                $signature = self::base64UrlDecode($sign);
                if (in_array($algo, ['ES256', 'ES384'],true)) {
                    $signature = self::signatureToDER($signature);
                }
                $verify = @openssl_verify($input, $signature, $pubkey, $alg);
                break;
            case 'sodium_crypto':
                try {
                    $lines = array_filter(explode("\n", $pubkey));
                    $key = base64_decode(end($lines));
                    /** @noinspection PhpComposerExtensionStubsInspection */
                    $verify = @sodium_crypto_sign_verify_detached(self::base64UrlDecode($sign), $input, $key);
                } catch (\Exception $e) {
                    // $e->getMessage()
                }
                break;
            default:
        }
        return $verify;
    }

    private static function sign(string $input, string $key, array $algo)
    {
        $signature = '';
        list($func, $alg) = $algo;
        switch ($func) {
            case 'hash_hmac':
                $signature = hash_hmac($alg, $input, $key, true);
                break;
            case 'openssl':
                /** @noinspection PhpComposerExtensionStubsInspection */
                $success = @openssl_sign($input, $signature, $key, $alg);
                // OpenSSL unable to sign data
                if ($success) {
                    if ($alg === 'ES256') {
                        $signature = self::signatureFromDER($signature, 256);
                    } elseif ($alg === 'ES384') {
                        $signature = self::signatureFromDER($signature, 384);
                    }
                }
                break;
            case 'sodium_crypto':
                // libsodium is not available
                if (function_exists('sodium_crypto_sign_detached')) {
                    try {
                        $lines = array_filter(explode("\n", $key));
                        $key = base64_decode(end($lines));
                        $signature = sodium_crypto_sign_detached($input, $key);
                    } catch (\Exception $e) {
                        // $e->getMessage()
                    }
                }
                break;
            default:
        }
        return self::base64UrlEncode($signature);
    }

    private static function signatureToDER($sig)
    {
        list($r, $s) = str_split($sig, (int)(strlen($sig) / 2));
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        if (ord($r[0]) > 0x7f) {
            $r = "\x00" . $r;
        }
        if (ord($s[0]) > 0x7f) {
            $s = "\x00" . $s;
        }

        return self::encodeDER(
            0x10,
            self::encodeDER(0x02, $r) .
            self::encodeDER(0x02, $s)
        );
    }

    private static function encodeDER($type, $value)
    {
        $tag_header = 0;
        if ($type === 0x10) {
            $tag_header |= 0x20;
        }
        $der = chr($tag_header | $type);
        $der .= chr(strlen($value));
        return $der . $value;
    }

    private static function signatureFromDER($der, $keySize)
    {
        list($offset) = self::readDER($der);
        list($offset, $r) = self::readDER($der, $offset);
        list(, $s) = self::readDER($der, $offset);
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, $keySize / 8, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, $keySize / 8, "\x00", STR_PAD_LEFT);
        return $r . $s;
    }

    private static function readDER($der, $offset = 0)
    {
        $pos = $offset;
        $size = strlen($der);
        $constructed = (ord($der[$pos]) >> 5) & 0x01;
        $type = ord($der[$pos++]) & 0x1f;
        $len = ord($der[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x1f;
            $len = 0;
            while ($n-- && $pos < $size) {
                $len = ($len << 8) | ord($der[$pos++]);
            }
        }
        if ($type == 0x03) {
            $pos++;
            $data = substr($der, $pos, $len - 1);
            $pos += $len - 1;
        } elseif (!$constructed) {
            $data = substr($der, $pos, $len);
            $pos += $len;
        } else {
            $data = null;
        }

        return array($pos, $data);
    }
}
