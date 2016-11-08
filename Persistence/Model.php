<?php
/**
 * Created by PhpStorm.
 * User: leonardo
 * Date: 27/10/16
 * Time: 23:48
 */

namespace LegionLab\Troubadour\Persistence;

use LegionLab\Utils\Language;
use Respect\Validation\Validator;

class Model extends Database
{

    public function __construct($table, $pk = "id", $database = null)
    {
        parent::__construct($table, $pk, $database);

    }

    public function className()
    {
        $class = explode('\\',get_class($this));
        return strtolower(array_pop( $class ));
    }

    protected function field($param, $name, $validation, $error, $isObject = false)
    {
        $class = $this->className();

        if($param === '@')
            return $this->$name;
        else {

            $methods = explode('|', $validation);
            $validation = 'Respect\Validation\Validator::';
            foreach ($methods as $value) {
                if(strpos($value, ':')) {
                    $params = explode(':', $value);
                    $validation .= $params[0].'(';
                    unset($params[0]);
                    foreach ($params as $value2)
                        $validation .= "'{$value2}', ";
                    $validation = mb_substr($validation, 0, -2).')->';
                }
                elseif ($value === "OR" or $value === "AND") {
                    $validation .= 'validate($param) '.$value.' Respect\Validation\Validator::';
                }
                else
                    $validation .= $value."()->";
            }
            $validation .= 'validate($param)';
            
            if(eval('return '.$validation.' OR Respect\Validation\Validator::nullType()->validate($param);'))
                $this->$name = $param;
            else
                throw new \Exception(Language::get($class, $error));

            if($isObject) {
                $class = "Models\\".ucfirst($name);
                if(eval('return Respect\Validation\Validator::instance("'.$class.'")->validate($param);'))
                    $this->$name = $param;
                else {
                    $this->$name = new $class($param);
                    $this->$name->get();
                }

            }
        }
        return $this;
    }

}