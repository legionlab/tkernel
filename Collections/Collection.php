<?php
/**
 * Created by PhpStorm.
 * User: leonardo
 * Date: 30/09/16
 * Time: 13:59
 */

namespace LegionLab\Troubadour\Collections;

interface Collection
{
    public static function get($key, $callback = '@');

    public static function set($key, $value = '');
}
