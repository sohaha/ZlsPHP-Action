<?php
/**
 * 文件解压
 */

namespace Zls\Action;

use Z;
use ZipArchive;

class Zip
{
    private $errorMsg;
    private $errorCode;
    private $saveFile;

    /**
     * 解压文件.
     *
     * @param      $filename
     * @param      $path
     * @param null $fn
     * @param bool $autoEmpty 清空解压目录
     * @param int $maxSize
     *
     * @return array|bool
     */
    public function unzip($filename, $path, $fn = null, $autoEmpty = true, $maxSize = 6291456)
    {
        if (file_exists($filename)) {
            $errorMsg = [];
            $starttime = explode(' ', microtime());
            if (true === $autoEmpty) {
                Z::rmdir($path, false);
            }
            $filename = iconv('utf-8', 'gb2312', $filename);
            $path = iconv('utf-8', 'gb2312', $path);
            $resource = zip_open($filename);
            while ($dirResource = zip_read($resource)) {
                if (zip_entry_open($resource, $dirResource)) {
                    $zipFileName = zip_entry_name($dirResource);
                    $fileName = $path . $zipFileName;
                    if (!is_dir($fileName)) {
                        $fileSize = zip_entry_filesize($dirResource);
                        if ($fileSize < $maxSize) {
                            $fileContent = null;
                            $getContent = function () use ($dirResource, $fileSize) {
                                return zip_entry_read($dirResource, $fileSize);
                            };
                            if ($fn instanceof \Closure) {
                                $fileContent = $fn($zipFileName, $getContent);
                                if (is_bool($fileContent) && !$fileContent) {
                                    $errorMsg[iconv('gb2312', 'utf-8', $fileName)] = '此文件已被跳过，原因：业务过滤';
                                    continue;
                                }
                            }
                            if (is_null($fileContent))
                                $fileContent = $getContent();
                            $filePath = substr($fileName, 0, strrpos($fileName, '/'));
                            if (!is_dir($filePath)) {
                                @mkdir($filePath, 0777, true);
                            }
                            file_put_contents($fileName, $fileContent);
                        } else {
                            $errorMsg[iconv('gb2312', 'utf-8', $fileName)] = '此文件已被跳过，原因：文件过大';
                        }
                    }
                    zip_entry_close($dirResource);
                }
            }
            zip_close($resource);
            $endtime = explode(' ', microtime());
            return ['thistime' => round($endtime[0] + $endtime[1] - ($starttime[0] + $starttime[1]), 3), 'err' => $errorMsg];
        }
        $this->setError(404, '文件 ' . $filename . ' 不存在');
        return false;
    }

    /**
     * @param $code
     * @param $msg
     */
    private function setError($code, $msg)
    {
        $this->errorCode = $code;
        $this->errorMsg = $msg;
    }

    /**
     * 压缩文件.
     *
     * @param        $path
     * @param        $save
     * @param null $manage
     * @param bool $overwrite 覆盖
     * @param string $prefix
     * @param array $include
     * @param bool $debug
     *
     * @return array
     *
     * @internal param null $ignoreFn
     */
    public function zip($path, $save, $manage = null, $overwrite = false, $prefix = '', $include = [], $debug = false)
    {
        $files = [];
        $zip = $this->getZip($save, $overwrite);
        $this->initFilesList($path, $files);
        foreach ($files as $file) {
            $localname = str_replace($path, $prefix, $file);
            $localname = ltrim($localname, '/');
            if ($manage instanceof \Closure) {
                $manageRes = $manage($localname, $file);
                if (false === $manageRes) {
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
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $debug[] = $zip->getNameIndex($i);
            }
            $res = $debug;
        }
        $zip->close();

        return $res;
    }

    /**
     * @param $save
     * @param $overwrite
     *
     * @return ZipArchive
     */
    public function getZip($save, $overwrite)
    {
        Z::throwIf(!class_exists('ZipArchive'), 500, 'please start the php zip extension first');
        $zip = new ZipArchive();
        $this->saveFile = $save;
        $zipState = $zip->open($save, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE);
        Z::throwIf(true !== $zipState, 500, 'open [ ' . $save . ' ] error, ' . $this->errorMessage($zipState));

        return $zip;
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
     * 初始化文件列表.
     *
     * @param   $path
     * @param   $file
     */
    private function initFilesList($path, &$file)
    {
        $handler = opendir($path);
        while (false !== ($filename = readdir($handler))) {
            if ('.' != $filename && '..' != $filename) {
                if (is_dir($path . '/' . $filename)) {
                    $this->initFilesList($path . '/' . $filename, $file);
                } else {
                    $file[] = $path . '/' . $filename;
                }
            }
        }
        closedir($handler);
    }

    public function download($filename = '')
    {
        if (!$filename) {
            $filename = $this->saveFile;
        }
        Z::header('Cache-Control: public');
        Z::header('Content-Description: File Transfer');
        Z::header('Content-disposition: attachment; filename=' . basename($filename));
        Z::header('Content-Type: application/zip');
        Z::header('Content-Transfer-Encoding: binary');
        Z::header('Content-Length: ' . filesize($filename));
        return readfile($filename) != false;
    }

    /**
     * @return array
     */
    public function getError()
    {
        return ['code' => $this->errorCode, 'msg' => $this->errorMsg];
    }

    public function pathMatche($path, $rule)
    {
        $matches = str_replace('*', '(.*)', $rule);
        $matches = str_replace('/', '\/', $matches);
        return preg_match('/' . $matches . '/', $path);
    }
}
