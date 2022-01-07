<?php

namespace Zls\Action;

/**
 * Tree.
 */
class TreeUtils
{
    /**
     * 转树
     * @param array  $arrs
     * @param string $id
     * @param string $pid
     * @param string $child
     * @return array
     */
    public static function make(array $arrs, $id = 'id', $pid = 'pid', $child = 'children')
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
     * 转数组.
     * @param array  $areaTree
     * @param string $id
     * @param string $pid
     * @param string $child
     * @return array
     */
    public static function toArray(array $areaTree, $id = 'id', $pid = 'pid', $child = 'children')
    {
        $arrs = [];
        foreach ($areaTree as $arr) {
            if (isset($arr[$child])) {
                $arrs = array_merge($arrs, self::toArray($arr[$child], $id, $pid, $child));
                unset($arr[$child]);
                $arrs[] = $arr;
            } else {
                $arrs[] = $arr;
            }
        }

        return $arrs;
    }
}
