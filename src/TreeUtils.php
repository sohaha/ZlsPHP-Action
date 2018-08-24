<?php

namespace Zls\Action;

/**
 * Tree.
 *
 * @author        影浅
 * @email         seekwe@gmail.com
 *
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 *
 * @see          ---
 * @since         v0.0.1
 * @updatetime    2017-7-03 18:06:02
 */
class TreeUtils
{
    /**
     * 生成Tree.
     *
     * @param array  $arrs
     * @param string $id
     * @param string $pid
     * @param string $child
     *
     * @return array
     */
    public function make(array $arrs, $id = 'id', $pid = 'pid', $child = 'children')
    {
        $keys = array_keys($arrs);
        $isAssoc = $keys === array_keys($keys);
        if ($isAssoc) {
            $newArr = [];
            foreach ($arrs as $value) {
                $newArr[$value[$id]] = $value;
            }
            $arrs = $newArr;
        }
        $areaTree = [];
        foreach ($arrs as $kid => $arr) {
            if (isset($arrs[$arr[$pid]])) {
                $arrs[$arr[$pid]][$child][] = &$arrs[$kid];
            } else {
                $areaTree[] = &$arrs[$arr[$id]];
            }
        }

        return $areaTree;
    }

    /**
     * Tree转一维数组.
     *
     * @param array  $areaTree
     * @param string $id
     * @param string $pid
     * @param string $child
     *
     * @return array
     */
    public function toArray(array $areaTree, $id = 'id', $pid = 'pid', $child = 'children')
    {
        $arrs = [];
        foreach ($areaTree as $arr) {
            if (isset($arr[$child])) {
                $arrs = array_merge($arrs, $this->toArray($arr[$child], $id, $pid, $child));
                unset($arr[$child]);
                $arrs[] = $arr;
            } else {
                $arrs[] = $arr;
            }
        }

        return $arrs;
    }
}
