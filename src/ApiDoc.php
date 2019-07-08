<?php

namespace Zls\Action;

use Closure;
use ReflectionClass;
use ReflectionException;
use Z;

/**
 * Class ApiDoc
 * @package       Zls\Action
 * @author        影浅 <seekwe@gmail.com>
 */
class ApiDoc
{
    private static $REQUEST_METHOD = [
        'GET'     => 'success',
        'POST'    => 'warning',
        'PUT'     => 'primary',
        'PATCH'   => 'info',
        'DELETE'  => 'danger',
        'COPY'    => 'default',
        'HEAD'    => 'default',
        'OPTIONS' => 'default',
        'LINK'    => 'default',
        'UNLINK'  => 'default',
        'PURGE'   => 'default',
        'RAW'     => 'default',
    ];
    private static $TYPEMAPS = [
        'string'  => '字符串',
        'phone'   => '手机号码',
        'eamil'   => '电子邮箱',
        'int'     => '整型',
        'float'   => '浮点型',
        'boolean' => '布尔型',
        'date'    => '日期',
        'array'   => '数组',
        'fixed'   => '固定值',
        'enum'    => '枚举类型',
        'object'  => '对象',
        'json'    => 'json',
    ];

    /**
     * @return array
     * @throws ReflectionException
     */
    public static function all()
    {
        $arr      = [];
        $config   = Z::config();
        $hmvcName = $config->getRoute()->gethmvcModuleName();
        self::listDirApiPhp(
            $config->getAppDir() . $config->getClassesDirName() . '/' . $config->getControllerDirName() . '/',
            $arr,
            $hmvcName
        );
        $ret = [];
        foreach ($arr as $k => $class) {
            $_hmvc = $hmvc = $class['hmvc'];
            if ((bool)$hmvc) {
                $class['controller'] = 'Hmvc_' . $class['controller'];
            }
            $controller = $class['controller'];
            if ($config->hmvcIsDomainOnly($hmvc)) {
                continue;
            }
            $data = self::docComment($controller, $hmvc, false, $class['time']);
            if (!$data) {
                continue;
            }
            $ret[$controller] = $data[0];
        }

        return $ret;
    }

    /**
     * @param        $dir
     * @param        $arr
     * @param null   $hmvc
     * @param string $Subfix
     */
    public static function listDirApiPhp($dir, &$arr, $hmvc = null, $Subfix = 'Api.php')
    {
        if (is_dir($dir) && ($dh = opendir($dir))) {
            while (false !== ($file = readdir($dh))) {
                if ('.' === $file || '..' === $file) {
                    continue;
                }
                $filePath = Z::realPath($dir . '/' . $file);
                if ((is_dir($filePath))) {
                    self::listDirApiPhp($dir . $file . '/', $arr, $hmvc);
                } else {
                    if (z::strEndsWith($file, $Subfix)) {
                        $uri       = explode('Controller/', $dir);
                        $filemtime = filemtime($filePath);
                        $arr[]     = [
                            'controller' => 'Controller_' . str_replace('/', '_', $uri[1]) . str_replace('.php', '', $file),
                            'hmvc'       => $hmvc,
                            'time'       => $filemtime ? date('Y-m-d H:i:s', $filemtime) : '',
                        ];
                    }
                }
            }
            closedir($dh);
        }
    }

    /**
     * @param null   $controller
     * @param string $hmvcName
     * @param bool   $library
     *
     * @return array|bool
     * @throws ReflectionException
     */
    public static function docComment($controller = null, $hmvcName = '', $library = false, $filetime = null)
    {
        $Prefix     = Z::config()->getMethodPrefix();
        $controller = self::getClassName($controller);
        if (!$controller) {
            return false;
        }
        $methods = self::getMethods($controller, 'public');
        if (!$class = self::apiClass($controller, null, $hmvcName)) {
            return false;
        }
        $methodArr = [];
        foreach ($methods as $method) {
            $methodType = strtoupper(Z::arrayGet(explode($Prefix, $method), 0, ''));
            $isHas      = $methodType && in_array($methodType, array_keys(self::$REQUEST_METHOD)) ?: 0 === strpos($method, $Prefix);
            if (!$library && !$isHas) {
                continue;
            }
            $methodArr[] = self::apiMethods($controller, $method, false, $hmvcName, $library, $methodType, $filetime);
        }

        return [['class' => $class, 'method' => $methodArr]];
    }

    public static function getClassName($className)
    {
        return get_class(Z::factory($className));
    }

    /**
     * @param      $className
     * @param null $access
     *
     * @return array
     * @throws ReflectionException
     */
    public static function getMethods($className, $access = null)
    {
        $class     = new ReflectionClass($className);
        $methods   = $class->getMethods();
        $returnArr = [];
        foreach ($methods as $value) {
            if ($value->class == $className) {
                if (null != $access) {
                    $methodAccess = new \ReflectionMethod($className, $value->name);
                    switch ($access) {
                        case 'public':
                            if ($methodAccess->isPublic()) {
                                $returnArr[] = $value->name;
                            }
                            break;
                        case 'protected':
                            if ($methodAccess->isProtected()) {
                                $returnArr[] = $value->name;
                            }
                            break;
                        case 'private':
                            if ($methodAccess->isPrivate()) {
                                $returnArr[] = $value->name;
                            }
                            break;
                        case 'final':
                            if ($methodAccess->isFinal()) {
                                $returnArr[] = $value->name;
                            }
                            break;
                        default:
                    }
                } else {
                    $returnArr[] = $value->name;
                }
            }
        }

        return $returnArr;
    }

    /**
     * 扫描class.
     *
     * @param string $controller
     * @param string $setKey
     * @param string $hmvc
     *
     * @return array|bool
     * @throws ReflectionException
     */
    private static function apiClass($controller, $setKey = null, $hmvc = '')
    {
        if (!class_exists($controller)) {
            return false;
        }
        $rClass                = new ReflectionClass($controller);
        $dComment              = $rClass->getDocComment();
        $docInfo               = [
            'title'      => null,
            'key'        => null,
            'desc'       => null,
            'url'        => '',
            'hmvc'       => $hmvc,
            'controller' => str_replace('_', '/', substr($controller, (bool)$hmvc ? 16 : 11)),
            'repetition' => [],
        ];
        $docInfo['controller'] = str_replace('\\', '/', $docInfo['controller']);
        if (false !== $dComment) {
            $doctArr          = explode("\n", $dComment);
            $comment          = trim($doctArr[1]);
            $docInfo['title'] = trim(substr($comment, strpos($comment, '*') + 1));
            foreach ($doctArr as $comment) {
                if ($desc = self::getDocInfo($comment, 'desc')) {
                    $docInfo['desc'] = trim($desc);
                    continue;
                }
                if ($key = self::getDocInfo($comment, 'key')) {
                    $getParams = explode('|', z::get('_key', $setKey));
                    if (!in_array(trim($key), $getParams)) {
                        return false;
                    }
                    $docInfo['key'] = trim($key);
                }
            }
        }
        if (is_null($docInfo['title'])) {
            $docInfo['title'] = '{请检查函数注释}';
        }
        $docInfo['url'] = ($docInfo['hmvc'] === z::config()->getCurrentDomainHmvcModuleNname()) ? $docInfo['controller'] : $docInfo['hmvc'] . '/' . $docInfo['controller'];

        return $docInfo;
    }

    private static function getDocInfo($str, $key, $resultStr = true, $defaultRes = '')
    {
        $res = $defaultRes;
        if (false === stripos($str, '@')) {
            return $res;
        }
        $keys = ["@{$key} ", "@api-{$key} "];
        foreach ($keys as $value) {
            $len = strlen($value);
            if (false !== (stripos($str, $value))) {
                $pos = (z::strBeginsWith($value, '@api-')) ? $len - 4 : $len;
                $res = $resultStr ? trim(mb_substr(trim(substr($str, strpos($str, '*') + 1)), $len)) : [trim($value), $pos];
                break;
            } else {
                $value = rtrim($value);
                if (Z::strEndsWith($str, $value)) {
                    return '';
                } elseif ($s = mb_strpos($str, '(')) {
                    $e = mb_strrpos($str, ')');
                    if ($s !== $e) {
                        $t   = mb_substr($str, $s + 1, $e - $s - 1);
                        $res = $t;
                        break;
                    }
                }
            }
        }

        return $res;
    }

    /**
     * @param        $controller
     * @param null   $method
     * @param bool   $paramsStatus
     * @param string $hmvcName
     * @param bool   $library
     * @param string $methodType
     * @param null   $filetime
     *
     * @return bool
     * @throws ReflectionException
     */
    public static function apiMethods($controller, $method = null, $paramsStatus = false, $hmvcName = '', $library = false, $methodType = '', $filetime = null)
    {
        if ($methodType === '') {
            $methodType = Z::get('_type', '');
            $method     = $methodType . $method;
        }
        if (!method_exists($controller, $method)) {
            return false;
        }
        $rMethod     = new \Reflectionmethod($controller, $method);
        $substrStart = $hmvcName ? 16 : 11;
        if ($hmvcName && z::config()->getCurrentDomainHmvcModuleNname()) {
            $docInfo['url'] = (!$library) ?
                z::url(
                    '/'
                    . str_replace('_', '/', substr($controller, $substrStart))
                    . '/'
                    . substr($method, strlen(z::config()->getMethodPrefix()))
                    . z::config()->getMethodUriSubfix()
                ) : $method;
        } else {
            $hmvcName       = (bool)$hmvcName ? '/' . $hmvcName : '';
            $docInfo['url'] = (!$library) ? z::url($hmvcName . '/' . str_replace('_', '/', substr($controller, $substrStart)) . '/' . substr($method, strlen($methodType . z::config()->getMethodPrefix())) . z::config()->getMethodUriSubfix()) : $method;
        }
        $docInfo['url']    = str_replace('\\', '/', $docInfo['url']);
        $docInfo['title']  = '{未命名}';
        $docInfo['desc']   = ''; //'//请使用@desc 注释';
        $docInfo['return'] = [];
        $docInfo['param']  = [];
        $docInfo['type']   = $methodType;
        $dComment          = $rMethod->getDocComment();
        if (false !== $dComment) {
            $doctArr           = explode("\n", $dComment);
            $comment           = trim($doctArr[1]);
            $docInfo['title']  = trim(substr($comment, strpos($comment, '*') + 1));
            $comment           = self::getCommentParameter($dComment);
            $docInfo['param']  = self::formatCommentParameter($comment);
            $docInfo['return'] = self::formatCommentReturn($comment);
            $docInfo['time']   = self::formatParameter('time', $comment);
            $docInfo['desc']   = Z::arrayGet(self::formatParameter('desc', $comment), '0.0', '');
        }
        if (!Z::arrayGet('time', $docInfo)) {
            $docInfo['time'] = $filetime;
        }

        return $docInfo;
    }

    private static function toColor($type)
    {
        return Z::arrayGet(self::$REQUEST_METHOD, strtoupper($type), 'info');
    }

    /**
     * @param string $type 类型
     * @param        $data
     */
    public static function html($type = 'parent', $data)
    {
        $token = ((bool)$token = z::get('_token', '', true)) ? '&_token=' . $token : '';
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>项目接口文档</title><meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0"><link rel="stylesheet" 
href="//cdn.jsdelivr.net/npm/bootstrap@3.2.0/dist/css/bootstrap.min.css"><style>.panel-body,table{word-break:break-all}.type-btn{min-width:62px;}.w30{width:30%}.alert-info{margin-top:10px;}th,td{white-space: nowrap;}.table-box{overflow:auto;margin-bottom: 20px;}table.table{margin:0}</style></head><body><br/><div class="container" style="width:90%">';
        if ((bool)$data) {
            if ('self' == $type) {
                $updateTime = z::arrayGet($data, 'time', '');
                $updateTime = $updateTime ? "<h5>更新时间 {$updateTime}</h5>" : '';
                $_host      = z::host();
                $url        = self::formatUrl($data['url'], '');
                $methodType = (z::arrayGet($data, 'type') ?: 'ANY');
                $toColor    = self::toColor($methodType);
                echo <<<DD
<div class="page-header"><h2>{$data['title']}<h4>{$data['desc']}</h4>{$updateTime}<h5><a target="_blank" title="复制接口地址" onclick="copyUrl('{$_host}{$url}')"><button type="button" class="btn btn-{$toColor} btn-xs type-btn">{$methodType}</button>
{$_host}{$url}</a></h5></h2></div><h3>请求参数</h3><div class="table-box"><table class="table table-striped table-bordered" >
<thead>
DD;
                if (count($data['param']) > 0) {
                    echo '<tr><th>参数名</th><th>请求方式</th><th>说明</th><th>类型</th><th>默认</th><th>必填</th><th class="w30">备注</th></tr>';
                    foreach ($data['param'] as $param) {
                        $query     = explode('|', $param['query']);
                        $queryType = z::arrayMap($query, function ($v) {
                            $queryTypeTitle = $v;
                            $toColor        = self::toColor($v);

                            return $v ? "<button type='button' class='btn btn-xs btn-{$toColor}' title='{$queryTypeTitle}'>{$v}</button>" : '';
                        });
                        $queryType = join(' ', $queryType);
                        echo '<tr><td>' . $param['name'] . '</td><td>' . $queryType . '</td><td>' . $param['title'] . '</td><td>' . $param['type'] . '</td><td>' . $param['default'] . '</td><td>' . $param['is'] . '</td><td>' . $param['desc'] . '</td></tr>';
                    }
                }
                echo '</table></div>';
                $returnHtml = $returnJson = '';
                if ($data['return']) {
                    echo '<h3>返回示例</h3>';
                    foreach ($data['return'] as $return) {
                        if (is_string($return)) {
                            $returnJson .= '<div class="text-muted panel panel-default"><div class="bg-warning panel-body">' . self::formatJson($return) . '</div></div>';
                        } elseif ((bool)$return) {
                            $returnHtml .= '<tr><td>' . $return['name'] . '</td><td>' . $return['type'] . '</td><td>' . $return['title'] . '</td><td>' . $return['desc'] . '</td></tr>';
                        }
                    }
                }
                if ((bool)$returnHtml) {
                    echo '<div class="table-box"><table class="table table-striped table-bordered"><thead><tr><th>字段</th><th>类型</th><th class="w30">说明</th><th class="w30">备注</th></tr>' . $returnHtml . '</table></div>';
                }
                echo $returnJson;
                echo '<div role="alert" class="alert alert-info"><strong>温馨提示：</strong> 此接口参数列表根据后台代码自动生成，可将 xxx?_api=self' . $token . ' 改成您需要查询的接口</div>';
            } else {
                foreach ($data as $class) {
                    if (!z::arrayGet($class, 'class.controller')) {
                        continue;
                    }
                    $repetition = '';
                    foreach (z::arrayGet($class, 'class.repetition', []) as $i => $hmvc) {
                        $_url       = self::formatUrl(z::url($hmvc . '/' . $class['class']['controller']), '?_api' . $token);
                        $repetition .= '<a href="' . $_url . '" target="_blank"><span class="label label-primary">' . $hmvc . '</span></a>';
                    }
                    echo '<div class="page-header jumbotrons"><h2>';
                    echo $class['class']['title'] . ':' . $class['class']['controller'];
                    echo '</h2><h4>' . $class['class']['desc'] . '</h4><h3>' . $repetition . '</h3></div>';
                    echo '<div class="table-box"><table class="table table-hover table-bordered"><thead><tr><th class="col-md-4">接口服务</th><th class="col-md-3">接口名称</th><th class="col-md-2">更新时间</th><th class="col-md-4">更多说明</th></tr></thead><tbody>';
                    foreach ($class['method'] as $v) {
                        $methodType = Z::arrayGet($v, 'type');
                        $toColor    = self::toColor($methodType);
                        $updateTime = z::arrayGet($v, 'time', '--');
                        $url        = self::formatUrl($v['url'], "?_type={$methodType}&_api=self{$token}");
                        $url        .= ($class['class']['key']) ? '&_key=' . $class['class']['key'] : '';
                        echo '<tr><td><button title="复制接口地址" onclick="copyUrl(\'' . $v['url'] . '\')" type="button" class="btn btn-' . $toColor . ' btn-xs type-btn">' . ($methodType ?: 'ANY') . '</button> <a title="查看接口详情" href="' . $url . '" target="_blank">' . $v['url'] . '</a></td><td>' . $v['title'] . '</td><td>' . $updateTime . '</td><td>' . $v['desc'] . '</td></tr>';
                    }
                    echo '</tbody></table></div>';
                }
                echo '<div role="alert" class="alert alert-info"><strong>温馨提示：</strong> 此接口参数列表根据后台代码自动生成，在任意链接追加?_api=all' . $token . '查看所有接口</div>';
            }
        } else {
            echo '<h2>没有找到API接口数据</h2>';
        }
        echo '</div><input id="copyText" style="opacity :0;position: fixed;z-index: -1;"><script>function copyUrl(text) {var input = document.getElementById(\'copyText\'); input.value = text;input.select();document.execCommand(\'copy\');alert(\'复制成功\');}</script></body></html>';
    }

    public static function formatUrl($url, $args)
    {
        $args   = ltrim($args, '?');
        $parse  = parse_url($url);
        $path   = z::arrayGet($parse, 'path', '');
        $query  = z::arrayGet($parse, 'query', '');
        $query  = ($query ? $query . '&' . $args : $args);
        $newUrl = $path . ($query ? '?' . $query : '');

        return $newUrl;
    }

    public static function formatJson($json = '')
    {
        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '&emsp;';
        $newLine     = '<br>';
        $prevChar    = '';
        $outOfQuotes = true;
        for ($i = 0; $i <= $strLen; ++$i) {
            $char = substr($json, $i, 1);
            if ('"' == $char && '\\' != $prevChar) {
                $outOfQuotes = !$outOfQuotes;
            } else {
                if (('}' == $char || ']' == $char) && $outOfQuotes) {
                    $result .= $newLine;
                    --$pos;
                    for ($j = 0; $j < $pos; ++$j) {
                        $result .= $indentStr;
                    }
                }
            }
            $result .= $char;
            if ((',' == $char || '{' == $char || '[' == $char) && $outOfQuotes) {
                $result .= $newLine;
                if ('{' == $char || '[' == $char) {
                    ++$pos;
                }
                for ($j = 0; $j < $pos; ++$j) {
                    $result .= $indentStr;
                }
            }
            $prevChar = $char;
        }

        return $result;
    }

    private static $commentRegex = '/([a-z_\\][a-z0-9_\:\\]*[\x{4e00}-\x{9fa5}a-z_][\*\x{4e00}-\x{9fa5}a-z0-9_-]*)|((?:[+-]?[0-9]+(?:[\.][0-9]+)*)(?:[eE][+-]?[0-9]+)?)|("(?:""|[^"])*+")|\s+|\*+|(.)/iu';

    /**
     * @param $class
     * @param $method
     *
     * @return Closure
     * @throws ReflectionException
     */
    public static function getMethodComment($class, $method)
    {
        $ref       = new ReflectionClass($class);
        $comment   = $ref->getMethod($method)->getDocComment();
        $parameter = self::getCommentParameter($comment);

        return function ($key = null, $involvePrefix = true) use ($parameter) {
            return $key ? self::formatParameter($key, $parameter, $involvePrefix) : $parameter;
        };
    }

    public static function formatParameter($key, array $parameter, $involvePrefix = true, $trim = '"')
    {
        $prefixKey = 'api-' . $key;
        $values    = null;
        if (Z::arrayKeyExists($key, $parameter)) {
            $values = Z::arrayGet($parameter, $key, []);
        }
        if ($involvePrefix && Z::arrayKeyExists($prefixKey, $parameter)) {
            $values = array_merge($values ?: [], Z::arrayGet($parameter, $prefixKey, []));
        }
        if (is_array($values)) {
            foreach ($values as &$value) {
                if ($value) {
                    if ($trim) {
                        foreach ($value as &$vv) {
                            if (substr($vv, 0, 1) === $trim && substr($vv, -1, 1) === $trim) {
                                $vv = trim($vv, '"');
                            }
                        }
                    }
                    if (Z::arrayGet($value, 0) === '(' && Z::arrayGet($value, count($value) - 1) === ')') {
                        array_shift($value);
                        array_pop($value);
                        $newValue = [];
                        foreach ($value as $k => $nv) {
                            if (in_array($nv, ['(', ')', ','])) {
                                continue;
                            }
                            $next = Z::arrayGet($value, $k + 1);
                            if ($next === '=') {
                                $newValue[$nv] = $value[$k + 2];
                                $value[$k + 2] = $value[$k + 1] = ',';
                            } else {
                                $newValue[] = $nv;
                            }
                        }
                        $value = $newValue;
                    }
                }
            }
        }

        return $values;
    }

    public static function resolveComment($comment)
    {
        $flags   = PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE;
        $matches = preg_split(self::$commentRegex, $comment, -1, $flags);
        $tokens  = [];
        if (false === $matches) {
            $matches = [[$comment, 0]];
        }
        foreach ($matches as $match) {
            $tokens[] = [
                'value'    => $match[0],
                'position' => $match[1],
            ];
        }

        return $tokens;
    }

    private static function formatCommentReturn($comment)
    {
        $return = self::formatParameter('return', $comment, true, '');
        $data   = [];
        foreach ($return as $rs) {
            if (strtolower(Z::arrayGet($rs, 0, '')) === 'json') {
                $data[] = implode('', Z::arrayFilter(array_slice($rs, 1), function ($v) {
                    return $v !== '*';
                }));
            } elseif (isset($rs[1])) {
                $k      = 0;
                $type   = Z::arrayGet($rs, $k++, '');
                $name   = Z::arrayGet($rs, $k++, '');
                $title  = Z::arrayGet($rs, $k++, '');
                $desc   = implode(' ', array_slice($rs, $k));
                $data[] = [
                    'title' => $title,
                    'name'  => $name,
                    'type'  => z::arrayGet(self::$TYPEMAPS, $type, $type),
                    'desc'  => $desc,
                ];
            }
        }

        return $data;
    }

    private static function formatCommentParameter($comment, $RESTful = 'param')
    {
        $params   = self::formatParameter($RESTful, $comment);
        $data     = [];
        $isParent = $RESTful === 'param';
        if ($isParent) {
            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'COPY', 'HEAD', 'OPTIONS', 'LINK', 'UNLINK', 'PURGE'] as $method) {
                $data = array_merge($data, self::formatCommentParameter($comment, strtolower($method)));
            }
        }
        if (is_null($params)) {
            return $data;
        }
        foreach ($params as $param) {
            $k    = 0;
            $type = Z::arrayGet($param, $k++, '');
            $name = Z::arrayGet($param, $k++, '');
            if (!$name) {
                continue;
            }
            $title   = Z::arrayGet($param, $k++, '');
            $query   = z::arrayMap(explode('|', $isParent ? Z::arrayGet($param, $k++, 'GET') : $RESTful), function ($query) {
                $query = trim($query);
                $query = $query && !in_array($query, ['', '\'\'', '""', '-']) ? $query : '';
                $_P    = 'Post-FormDate';
                $_G    = 'Get';
                $_R    = 'Raw';
                $_RT   = 'Raw-Text';
                $_RJ   = 'Raw-Json';
                $_F    = 'FormDate';

                return Z::arrayGet([
                    'P'    => $_P,
                    'POST' => $_P,
                    'G'    => $_G,
                    'GET'  => $_G,
                    'F'    => $_F,
                    'FORM' => $_F,
                    'J'    => $_RJ,
                    'JSON' => $_RJ,
                    'T'    => $_RT,
                    'TEXT' => $_RT,
                    'R'    => $_R,
                    'RAW'  => $_R,
                ], strtoupper($query), ucwords($query));
            });
            $query   = join('|', $query);
            $default = Z::arrayGet($param, $k++, '');
            $is      = strtoupper(Z::arrayGet($param, $k++, ''));
            $is      = in_array($is, ['N', 'NO']) ? '否' : '是';
            $desc    = implode(' ', array_slice($param, $k));
            $data[]  = [
                'title'   => $title,
                'name'    => $name,
                'type'    => z::arrayGet(self::$TYPEMAPS, $type, $type),
                'query'   => $query,
                'default' => $default,
                'is'      => $is,
                'desc'    => $desc,
            ];
        }

        return $data;
    }

    public static function getCommentParameter($commentStr)
    {
        $pos          = self::findPosition($commentStr);
        $commentStr   = trim(substr($commentStr, $pos), '* /');
        $comments     = self::resolveComment($commentStr);
        $parametes    = [];
        $jumpKey      = [];
        $currentKey   = null;
        $currentIndex = 0;
        foreach ($comments as $k => &$comment) {
            if (!in_array($k, $jumpKey, true)) {
                $value = $comment['value'];
                $nextK = $k + 1;
                $next  = Z::arrayGet($comments, $nextK, ['value' => '']);
                if ($value === '@') {
                    if (!$next) {
                        continue;
                    }
                    $jumpKey[] = $nextK;
                    $key       = $next['value'];
                    if (!isset($parametes[$key])) {
                        $parametes[$key] = [];
                        $currentIndex    = 0;
                    } else {
                        $currentIndex++;
                    }
                    $currentKey = $key;
                } elseif ($value === '*' && ($next['value'] === '@' || $next['value'] === '*' || substr($commentStr, $comment['position'] + 1, 1) === "\n")) {
                    continue;
                } else {

                    $parametes[$currentKey][$currentIndex][] = $comment['value'];
                }
            }
        }

        return $parametes;
    }

    public static function findPosition($comment)
    {
        $pos = 0;
        while (($pos = strpos($comment, '@', $pos)) !== false) {
            $preceding = substr($comment, $pos - 1, 1);
            if ($pos === 0 || $preceding === ' ' || $preceding === '*' || $preceding === "\t") {
                return $pos;
            }
            $pos++;
        }

        return null;
    }


}
