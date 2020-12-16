<?php

namespace Zls\Action;

/**
 * Ini文件操作.
 *
 * @author        影浅
 * @email         seekwe@gmail.com
 *
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 *
 * @see          ---
 * @since         v0.0.1
 * @updatetime    2018-03-19 12:57
 */
class Ini
{
    public function extended($content)
    {
        $config = [''];
        foreach ($content as $namespace => $properties) {
            $config[] = '[' . $namespace . ']';
            $config[] = $this->valueHandle($properties);
            $config[] = '';
        }
        return join(PHP_EOL, $config);
    }

    private function valueHandle($values, $name = '')
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $result[] = $this->valueHandle($value, $key);
            } else {
                switch ($value) {
                    case \z::checkValue($value, 'num'):
                    case 'true':
                    case 'false':
                        break;
                    default:
                        $value = '"' . addslashes($value) . '"';
                }
                $result[] = ($name ? $name . '[]' : $key) . " = {$value}";
            }
        }
        return join(PHP_EOL, $result);
    }
}
