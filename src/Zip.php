<?php
/**
 * zip
 * @author 影浅-Seekwe
 * @link   seekwe@gmail.com
 * @since  0.0.1
 */

namespace Zls\Action;

use Z;

class Zip
{
    private $errorMsg;
    private $errorCode;
    private $saveFile;

    /**
     * 解压文件
     * @param      $filename
     * @param      $path
     * @param null $fn
     * @param bool $del
     * @param int  $maxSize
     * @return array|bool
     */
    public function unzip($filename, $path, $fn = null, $del = true, $maxSize = 6291456)
    {
        $errorMsg = [];
        if (file_exists($filename)) {
            $starttime = explode(' ', microtime());
            if ($del === true) {
                z::rmdir($path, false);
            }
            /*将文件名和路径转成windows系统默认的gb2312编码，否则将会读取不到*/
            $filename = iconv("utf-8", "gb2312", $filename);
            $path = iconv("utf-8", "gb2312", $path);
            $resource = zip_open($filename);
            while ($dir_resource = zip_read($resource)) {
                if (zip_entry_open($resource, $dir_resource)) {
                    $file_name = $path . zip_entry_name($dir_resource);
                    /*以最后一个“/”分割,再用字符串截取出路径部分*/
                    $file_path = substr($file_name, 0, strrpos($file_name, "/"));
                    if (!is_dir($file_path)) {
                        mkdir($file_path, 0777, true);
                    }
                    if (!is_dir($file_name)) {
                        $file_size = zip_entry_filesize($dir_resource);
                        /*如果文件过大，跳过解压，继续下一个*/
                        if ($file_size < $maxSize) {
                            $file_content = zip_entry_read($dir_resource, $file_size);
                            if ($fn instanceof \Closure) {
                                $file_content = $fn($file_name, $file_content);
                            }
                            file_put_contents($file_name, $file_content);
                        } else {
                            $errorMsg[iconv("gb2312", "utf-8", $file_name)] = '此文件已被跳过，原因：文件过大';
                        }
                    }
                    zip_entry_close($dir_resource);
                }
            }
            zip_close($resource);
            $endtime = explode(' ', microtime());
            $thistime = $endtime[0] + $endtime[1] - ($starttime[0] + $starttime[1]);
            $thistime = round($thistime, 3);

            return ['thistime' => $thistime];
        } else {
            $this->setError(404, '文件 ' . $filename . ' 不存在');
        }

        return false;
    }

    /**
     * @param $code
     * @param $msg
     */
    public function setError($code, $msg)
    {
        $this->errorCode = $code;
        $this->errorMsg = $msg;
    }

    /**
     * 压缩文件
     * @param        $path
     * @param        $save
     * @param null   $manage
     * @param bool   $overwrite 覆盖
     * @param string $prefix
     * @param array  $include
     * @param bool   $debug
     * @return bool
     * @internal param null $ignoreFn
     */
    public function zip($path, $save, $manage = null, $overwrite = false, $prefix = '', $include = [], $debug = false)
    {
        try {
            $zip = new \ZipArchive();
            $files = [];
            $this->saveFile = $save;
            $zipState = $zip->open($save, $overwrite ? \ZIPARCHIVE::OVERWRITE : \ZIPARCHIVE::CREATE);
            if ($zipState === true) {
                $this->initFilesList($path, $files);
                foreach ($files as $file) {
                    $localname = str_replace($path, $prefix, $file);
                    $localname = ltrim($localname, '/');
                    if ($manage instanceof \Closure) {
                        $manageRes = $manage($localname, $file);
                        if ($manageRes === false) {
                            continue;
                        } elseif (is_string($manageRes)) {
                            $zip->addFromString($localname, $manageRes);
                            continue;
                        }
                    }
                    $zip->addFile($file, $localname);
                }
                if ($include) {
                    foreach ($include as $v) {
                        $localname = str_replace($path, $prefix, $v);
                        $localname = ltrim($localname, '/');
                        $zip->addFile(z::realPath('../' . $v), $localname);
                    }
                }
                $res = $save;
                if ($debug) {
                    $debug = [];
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $debug[] = $zip->getNameIndex($i);
                    }
                    $res = $debug;
                }
                /*关闭处理的zip文件*/
                $zip->close();

                return $res;
            } else {
                throw new \Exception('open [ ' . $save . ' ] error, ' . $this->errorMessage($zipState));
            }
        } catch (\Exception $exc) {
            $this->setError(500, $exc->getMessage());

            return false;
        }
    }

    /**
     * 初始化文件列表
     * @param             $path
     * @param             $file
     */
    private function initFilesList($path, &$file)
    {
        /*打开当前文件夹由$path指定。*/
        $handler = opendir($path);
        while (($filename = readdir($handler)) !== false) {
            /*文件夹文件名字为'.'和‘..’，不要对他们进行操作*/
            if ($filename != "." && $filename != "..") {
                /*如果读取的某个对象是文件夹，则递归*/
                if (is_dir($path . "/" . $filename)) {
                    $this->initFilesList($path . "/" . $filename, $file);
                } else {
                    /*将文件加入zip对象*/
                    $file[] = $path . "/" . $filename;
                }
            }
        }
        closedir($handler);
    }

    public function errorMessage($code)
    {
        switch ($code) {
            case 0:
                return 'No error';
            case 1:
                return 'Multi-disk zip archives not supported';
            case 2:
                return 'Renaming temporary file failed';
            case 3:
                return 'Closing zip archive failed';
            case 4:
                return 'Seek error';
            case 5:
                return 'Read error';
            case 6:
                return 'Write error';
            case 7:
                return 'CRC error';
            case 8:
                return 'Containing zip archive was closed';
            case 9:
                return 'No such file';
            case 10:
                return 'File already exists';
            case 11:
                return 'Can\'t open file';
            case 12:
                return 'Failure to create temporary file';
            case 13:
                return 'Zlib error';
            case 14:
                return 'Malloc failure';
            case 15:
                return 'Entry has been changed';
            case 16:
                return 'Compression method not supported';
            case 17:
                return 'Premature EOF';
            case 18:
                return 'Invalid argument';
            case 19:
                return 'Not a zip archive';
            case 20:
                return 'Internal error';
            case 21:
                return 'Zip archive inconsistent';
            case 22:
                return 'Can\'t remove file';
            case 23:
                return 'Entry has been deleted';
            default:
                return 'An unknown error has occurred(' . intval($code) . ')';
        }
    }

    /**
     * 下载压缩包
     * @param string $filename
     */
    public function download($filename = '')
    {
        if (!$filename) {
            $filename = $this->saveFile;
        }
        z::header("Cache-Control: public");
        z::header("Content-Description: File Transfer");
        z::header('Content-disposition: attachment; filename=' . basename($filename));
        z::header("Content-Type: application/zip");
        z::header("Content-Transfer-Encoding: binary");
        z::header('Content-Length: ' . filesize($filename));
        @readfile($filename);
    }

    /**
     * @return array
     */
    public function getError()
    {
        return ['code' => $this->errorCode, 'msg' => $this->errorMsg];
    }
}
