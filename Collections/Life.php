<?php
/**
 * Created by PhpStorm.
 * User: leonardo
 * Date: 30/09/16
 * Time: 14:08
 */

namespace LegionLab\Troubadour\Collections;

interface Life
{
    public static function create($name = '@');
    public static function destroy();
}