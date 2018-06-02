<?php

namespace Zls\Action;

/**
 * Ini文件操作
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-03-19 12:57
 */
class Ini
{
    function extended($content)
    {
        $config = [''];
        foreach ($content as $namespace => $properties) {
            $config[] = '[' . $namespace . ']';
            foreach ($properties as $key => $value) {
                $config[] = $key . ' = ' . $value;
            }
            $config[] = '';
        }

        return join(PHP_EOL, $config);
    }
}
