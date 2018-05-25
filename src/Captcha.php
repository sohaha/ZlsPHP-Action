<?php

namespace Zls\Action;

use Z;

/**
 * 验证码类
 * $image=Z::extension('Action_Captcha');
 * $image->config('宽度','高度','字符个数','验证码session索引');
 * $code=$image->create();//这样就会向浏览器输出一张图片,并返回验证码图片上的内容
 * //所有参数都可以省略，
 * 默认是：宽80 高20 字符数4 验证码$_SESSION键名称captcha_code
 * 第四个参数即把验证码存到$_SESSION['captcha_code'],第四个参数如果为null，则不会在$_SESSION中设置验证码。
 * 验证码组成类型，有三种：1.number（纯数字） 2.letter（纯字母） 3.both（数字和字母）
 * 可以通过$image->setCodeMode('类型')进行设置
 * 最简单使用示例:
 * $image=Z::extension('Captcha');
 * $image->create();//这样就会向浏览器输出一张图片
 */
class Captcha
{
    private $width = 80, $height = 20, $codenum = 4
    , $checkcode     //产生的验证码
    , $checkimage    //验证码图片
    , $session_flag = 'captcha_code' //存到session中的索引
    , $font_path
    , $codeMode = 'number'; //验证码组成：1.number 2.letter 3.both

    function __construct()
    {
        Z::sessionStart();
        $this->font_path = z::tempPath() . '/' . md5('captchattf') . '.ttf';
        if (!file_exists($this->font_path)) {
            file_put_contents($this->font_path, $this->getFontbin());
        }
    }

    /**
     * 获取字体二进制数据
     * @return string
     */
    private function getFontbin()
    {
        return base64_decode('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AuwARATNAAACqgAABM0AAATNAe8EzQElBM0AbATNAI0EzQBCBM0ARATNAfAEzQF/BM0BTgTNADgEzQCYBM0BuATOAPcEzQHPBM0AuATNAHsEzQDtBM0AwQTNAKEEzQA9BM0AqATNAJoEzQCPBM0AtQTNAMEEzQHsBM0BmgTNAJgEzgCHBM0AmATNAKgEzQBQBM0AUgTNAJ4EzQB/BM0AqwTNAOEEzQD0BM0AZgTNAIwEzQDCBM0AtATNANMEzQEvBM4AlgTNAJYEzQB7BM0A0gTNAFQEzQC6BM0AmATNAF4EzQCBBM0AOQTOAFoEzQBOBM0ARQTNAJwEzQGLBM0AwQTNAPoEzQBvBM0ABATNAaIEzQC1BM0AqwTNAMMEzQB5BM0A6wTNAM4EzQB+BM0AtgTNANwEzQDGBM0A0gTNAIEEzQBkBM0AxATNAIoEzQCfBM0AagTNAWkEzQD5BM0AYQTOAL8EzQBdACAAkgBjAOoA4QISAMoAmAAAAecArQB3ACYATgIbAPQBSgAGARQAxQCYAAAABgETASsAmAE4ATkBogDEAEIB5wGsAYwBGQDFABcAAgAUAKgAUgBSAFIAUgBSAFIAGwB/AOEA4QDhAOEAwgDCAMIAwgAAAJYAewB7AHsAewB7AL4AewCBAIEAgQCBAEUAsACkALUAtQC1ALUAtQC1ADcAwwDrAOsA6wDrAMUAxQDFAMUAhwDEAIoAigCKAIoAigCYAHYApAC/AL8AvwBjAJ4AYwBSALUAUgC1AFIAtQB/AMMAfwDDAH8AwwB/AMMAqwB5AAAAeQDhAOsA4QDrAOEA6wDhAOsAZgB+AGYAfgBmAH4AZgBvAIwAtgCMAAQAwgDFAMIAxQDCAMUAwgDcAMIAxQA3AFgAiQB/ANMA1wDXAS8AgQEvAIEBLwCBAS8AfQALAH0AlgDEAJYAxACWAMQAgQDEAHsAigB7AIoAewCKABQARgC6AWkAugFpALoBNQCYAPkAmAD5AJgA+QCYAPkAXgBhAF4AWwCBAL8AgQC/AIEAvwCBAL8AgQC/AIEAvwBaACAARQBjAEUAnADqAJwA6gCcAOoA1wCNAMsAXgBhARsBGwE5AfwBgwGeAP4A9gC4//oBQwG+Ab4ByQDpAOkA9ADwAOUBewBOAAQBkQGRAKoAYAAAAHn/wgCcAEoAmAAXAEYA7ACYAJgAmACY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');
    }

    /**
     * 获取写到图片上的验证码字符串
     * @return string
     */
    public function getCheckCode()
    {
        return $this->checkcode;
    }
    /*
  * 参数：（宽度，高度，字符个数）
  */
    /**
     * 设置验证码组成
     * @param string $codeMode 验证码组成类型，参数值有三种：1.number（纯数字） 2.letter（纯字母） 3.both（数字和数字）
     * @return $this
     */
    public function setCodeMode($codeMode)
    {
        Z::throwIf(!in_array($codeMode, ['number', 'letter', 'both']), 500, 'error type [ ' . $codeMode . ' ] , should be one of [ number,letter,both ]');
        $this->codeMode = $codeMode;

        return $this;
    }

    public function config($width = '80', $height = '20', $codenum = '4', $session_flag = 'captcha_code')
    {
        $this->width = $width;
        $this->height = $height;
        $this->codenum = $codenum;
        $this->session_flag = $session_flag;
    }

    public function create()
    {
        //输出头
        $this->outFileHeader();
        //产生验证码
        $this->createCode();
        //产生图片
        $this->createImage();
        //画正弦干扰线
        $this->wirteSinLine();
        //往图片上写验证码
        $this->writeCheckCodeToImage();
        imagepng($this->checkimage);
        imagedestroy($this->checkimage);
        if (!empty($this->session_flag)) {
            $_SESSION[$this->session_flag] = $this->checkcode;
        }

        return $this->checkcode;
    }

    private function outFileHeader()
    {
        Z::header("Content-type: image/png");
    }

    /**
     * 产生验证码
     */
    private function createCode()
    {
        if ($this->codeMode == 'both') {
            $this->checkcode = strtoupper(substr(md5(rand()), 0, $this->codenum));
        } elseif ($this->codeMode == 'number') {
            $this->checkcode = rand((int)('1' . implode('', array_fill(0, $this->codenum - 1, 0))), (int)('1' . implode('', array_fill(0, $this->codenum, 0)))) . '';
        } elseif ($this->codeMode == 'letter') {
            $map = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
            for ($i = 0; $i < $this->codenum; $i++) {
                $this->checkcode .= $map{rand(0, strlen($map) - 1)};
            }
        }
    }

    /**
     * 产生验证码图片
     */
    private function createImage()
    {
        $this->checkimage = @imagecreate($this->width, $this->height);
        $back = imagecolorallocate($this->checkimage, 255, 255, 255);
        imagefilledrectangle($this->checkimage, 0, 0, $this->width - 1, $this->height - 1, $back); // 白色底
    }

    private function wirteSinLine()
    {
        $count = 2;
        for ($index = 0; $index < $count; $index++) {
            $w = rand(floor($this->width / rand(2, 5)), $this->width * rand(1, 3));
            $img = $this->checkimage;
            $color = imagecolorallocate($this->checkimage, rand(0, 255), rand(0, 255), rand(0, 255));
            $h = $this->height;
            $h1 = rand(-5, 5);
            $h2 = rand(-1, 1);
            $w2 = rand(10, 15);
            $h3 = rand(4, 6);
            $type = rand(0, 1);
            for ($i = -$w / 2; $i < $w / 2; $i = $i + 0.1) {
                $v = [cos($i / $w2), sin($i / $w2)];
                $y = $h / $h3 * $v[$type] + $h / 2 + $h1;
                imagesetpixel($img, $i + $w / 2, $y, $color);
                $h2 != 0 ? imagesetpixel($img, $i + $w / 2, $y + $h2, $color) : null;
            }
        }
    }

    /**
     * 在验证码图片上逐个画上验证码
     */
    private function writeCheckCodeToImage()
    {
        for ($i = 0; $i < $this->codenum; $i++) {
            $bg_color = imagecolorallocate($this->checkimage, rand(0, 255), rand(0, 128), rand(0, 255));
            $x = floor($this->width / $this->codenum) * $i;
            $y = rand(14, 18);
            imagettftext($this->checkimage, rand(13, 16), rand(-35, 35), $x + 5, $y, $bg_color, $this->font_path, $this->checkcode[$i]);
        }
    }

    public function __destruct()
    {
        unset($this->width, $this->height, $this->codenum, $this->session_flag);
    }
}
