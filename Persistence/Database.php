<?php

namespace LegionLab\Troubadour\Persistence;

use LegionLab\Troubadour\Collections\Settings;
use LegionLab\Troubadour\Development\Log;
use LegionLab\Troubadour\Collections\Session;
use LegionLab\Troubadour\Collections\Saved;
use LegionLab\Utils\Criteria;
use LegionLab\Utils\Pager;

/**
 * Control Database
 *
 * Created by PhpStorm.
 * User: Leonardo Vilarinho
 * Date: 04/07/2016
 * Time: 16:47
 */

abstract class Database
{
    /**
     * Atributos
     * @var null - armazena a conexao com o banco, indica objeto a ser manipulado
     */
    private $connection = null;
    private $table = null;
    protected $primaryKey = 'id';
    private $debug = false;

    private $database;

    public function __construct($table, $pk = "id", $database = null)
    {
        $this->connection = Connect::$conn;
        if(is_null($database))
            $this->database = Settings::get("default_dbname");
        else
            $this->database = $database;


        $this->table = $table;
        $this->primaryKey = $pk;
    }


    public function debug()
    {
        $this->debug = !$this->debug;
        return $this;
    }

    /**
     * Tenta realizar a conexao com um banco de dados MySQL.
     * @param $line - linha do código
     * @return bool|\PDO - false ou a conexao com o banco
     */
    private function connect($line)
    {
        if(is_null($this->connection)) {
            try {
                if ($conn = Connect::tryConnect($line, $this->database))
                    return $conn;
                else
                    return Connect::tryConnect($line);
            } catch (\Exception $e) {
                Log::register($e->getMessage() . "In l:" . __LINE__);
                return false;
            }
        }
        else
            return $this->connection;
    }

    public function insert()
    {
        $data = $this->clear();

        $table = $data['table'];
        unset($data['table']);

        if($this->connection = $this->connect(__LINE__)) {
            $pt1 = $pt2 = "";
            foreach ($data as $key => $value) {
                $pt1 .= "{$key},";
                $pt2 .= ":{$key},";
            }
            $pt1 = mb_substr($pt1, 0, -1);
            $pt2 = mb_substr($pt2, 0, -1);

            $sql = "INSERT INTO" . " {$table} ({$pt1}) VALUES($pt2);";

            if($this->debug)
                var_dump($sql);
            try {
                $prepare = $this->connection->prepare($sql);
                foreach ($data as $key => $value) {
                    if(class_exists($key)) {
                        $k = $this->$key->primaryKey;
                        $prepare->bindValue(":{$key}", $this->$key->$k);
                    }
                    elseif(is_object($this->$key)) {
                        $k = $this->$key->primaryKey;
                        $prepare->bindValue(":{$key}", $this->$key->$k);
                    }
                    else
                        $prepare->bindValue(":{$key}", $this->$key);
                }
                $set = "{$this->primaryKey}";
                if($this instanceof Database)
                    $this->$set($this->last() + 1);


                return $this->close($prepare, 'Insert', __LINE__);

            } catch(\Exception $e) {
                Log::register($e->getMessage()."In l:" .__LINE__);
                throw new \Exception();
            }
        }
        return false;
    }

    public function update($attrOrCriteria = "id")
    {
        $data = $this->clear();
        $table = $data['table'];
        unset($data['table']);

        if($this->connection = $this->connect(__LINE__)) {
            $bind = "";
            foreach ($data as $key => $value)
                if($key != $this->primaryKey)
                    $bind .= "{$key}=:{$key},";

            $bind = mb_substr($bind, 0, -1);

            $sql = ($attrOrCriteria instanceof Criteria)
                ? "UPDATE {$attrOrCriteria->getTables()} SET" . " $bind {$attrOrCriteria->getWhere()};"
                : "UPDATE {$table} SET" . " $bind WHERE {$attrOrCriteria} = :{$attrOrCriteria};";
            if($this->debug)
                var_dump($sql);
            try {
                $prepare = $this->connection->prepare($sql);
                if(!$attrOrCriteria instanceof Criteria)

                    $prepare->bindValue(":{$attrOrCriteria}", $this->$attrOrCriteria);
                else
                    foreach ($attrOrCriteria->getValues() as $key => $value)
                        $prepare->bindValue(":$key", $value);

                foreach ($data as $key => $value) {
                    if(class_exists("\\Models\\".$key)) {
                        $k = $this->$key->primaryKey;
                        $prepare->bindValue(":{$key}", $this->$key->$k);
                    }
                    else
                        if($key != $this->primaryKey)
                            $prepare->bindValue(":{$key}", $this->$key);
                }

                return $this->close($prepare, 'Update', __LINE__);

            } catch(\Exception $e) {
                Log::register($e->getMessage()."In l:" .__LINE__);
                throw new \Exception();
            }
        }
    }


    public function get($attrOrCriteria = 'id')
    {
        $data = $this->clear();

        $sql = ($attrOrCriteria instanceof Criteria)
            ? "SELECT {$attrOrCriteria->getSelect()}" . " FROM {$this->addCriteria($attrOrCriteria)};"
            : "SELECT * FROM" . " {$data['table']} WHERE $attrOrCriteria = :$attrOrCriteria ";

        if($this->debug)
            var_dump($sql);

        if($this->connection = $this->connect(__LINE__)) {
            try {
                $prepare = $this->connection->prepare($sql);
                if(!($attrOrCriteria instanceof Criteria))
                    $prepare->bindValue(":{$attrOrCriteria}", $this->$attrOrCriteria);
                else
                    foreach ($attrOrCriteria->getValues() as $key => $value)
                        $prepare->bindValue(":$key", $value);

                $prepare->execute();
                $result = $prepare->fetch(\PDO::FETCH_ASSOC);
                $this->fill($result);
                Saved::set([$this->className() => $result]);
                Log::register("Get execute of: " . $sql. "\nIn l:" . __LINE__, "mysql_success");

                return $result;
            } catch (\Exception $e) {
                Log::register($e->getMessage()."In l:" .__LINE__);
                throw new \Exception();
            }
        }
    }

    public function count($criteria = null)
    {
        $data = $this->clear();

        $sql = ($criteria instanceof Criteria)
            ? "SELECT COUNT(*) as cnt" . " FROM {$this->addCriteria($criteria)};"
            : "SELECT COUNT(*) as cnt" . " FROM {$data['table']}";

        if($this->debug)
            var_dump($sql);

        if($this->connection = $this->connect(__LINE__)) {
            try {
                $prepare = $this->connection->prepare($sql);
                if($criteria instanceof Criteria)
                    foreach ($criteria->getValues() as $key => $value)
                        $prepare->bindValue(":$key", $value);

                $prepare->execute();
                Log::register("Count execute of :" . $sql. "\nIn l:" . __LINE__, "mysql_success" );
                return $prepare->fetchAll(\PDO::FETCH_ASSOC)[0]['cnt'];
            } catch (\Exception $e) {
                Log::register($e->getMessage()."In l:" .__LINE__);
            }
        }
        return false;
    }

    public function listAll($pager = null, $criteria = null)
    {
        $data = $this->clear();
        $sql = "";

        if($pager instanceof Pager and $criteria instanceof Criteria)
            $sql =  "SELECT *" . " FROM {$this->addCriteria($criteria)} LIMIT {$pager->range()['min']}, {$pager->range()['max']};";
        else if($pager instanceof Pager)
            $sql =  "SELECT *" . " FROM {$data['table']} LIMIT {$pager->range()['min']}, {$pager->range()['max']};";

        else if($criteria instanceof Criteria)
            $sql = "SELECT {$criteria->getSelect()}" . " FROM {$this->addCriteria($criteria)};";
        else if(!$pager instanceof Pager)
            $sql = "SELECT *" . " FROM {$data['table']};";

        if($this->debug)
            var_dump($sql);

        if($this->connection = $this->connect(__LINE__)) {
            try {
                $prepare = $this->connection->prepare($sql);
                if($criteria instanceof Criteria) {
                    foreach ($criteria->getValues() as $key => $value) {
                        $prepare->bindValue(":$key", $value);
                    }
                }

                $prepare->execute();
                $list = $prepare->fetchAll(\PDO::FETCH_ASSOC);
                $return = array();

                foreach ($list as $row)
                    array_push($return, $this->fills($row));

                Log::register("List All execute of: " . $sql. "\nIn l:" . __LINE__, "mysql_success");
                return $return;
            } catch (\Exception $e) {
                Log::register($e->getMessage()."In l:" .__LINE__);
                throw new \Exception();
            }
        }
    }

    public function sql($sql, $bind = array(), $all = true)
    {
        if($this->debug)
            var_dump($sql);

        if($this->connection = $this->connect(__LINE__)) {
            try {
                $prepare = $this->connection->prepare($sql);
                if(!empty($bind))
                    foreach ($bind as $key => $value)
                        $prepare->bindValue(":$key", $value);

                $prepare->execute();

                Log::register("SQL person execute of: " . $sql . "\nIn l:" . __LINE__, "mysql_success");
                if($all)
                    $return =  $prepare->fetchAll(\PDO::FETCH_ASSOC);
                else
                    $return =  $prepare->rowCount();
                return $return;

            } catch (\Exception $e) {
                Log::register("Error SQL person:" . $e->getMessage() ."\nSQL:{$sql}" . "\nIn l:" . __LINE__);
                throw new \Exception();
            }
        }
    }

    public function delete($attrOrCriteria = 'id')
    {
        $data = $this->clear();
        $table = $data['table'];
        unset($data['table']);

        $sql = ($attrOrCriteria instanceof Criteria)
            ? "DELETE" . " FROM {$attrOrCriteria->getTables()} {$attrOrCriteria->getWhere()};"
            : "DELETE" . " FROM {$table} WHERE {$attrOrCriteria} = :{$attrOrCriteria}";

        if($this->debug)
            var_dump($sql);

        if($this->connection = $this->connect(__LINE__)) {
            try {
                $prepare = $this->connection->prepare($sql);
                if(!($attrOrCriteria instanceof Criteria))
                    $prepare->bindValue(":$attrOrCriteria", $this->$attrOrCriteria);
                else
                    foreach ($attrOrCriteria->getValues() as $key => $value)
                    $prepare->bindValue(":$key", $value);

                return $this->close($prepare, 'Delete', __LINE__);
            } catch (\Exception $e) {
                Log::register($e->getMessage()."In l:" .__LINE__);
                throw new \Exception();
            }
        }
    }

    public function last()
    {
        $d = $this->clear();
        $sql = "SELECT AUTO_INCREMENT as last " . " FROM information_schema.tables WHERE table_name = '{$d['table']}' AND table_schema = '{$this->database}';";

        if($this->debug)
            var_dump($sql);

        if($this->connection = $this->connect(__LINE__)) {
            try {
                $result = $this->connection->query($sql);
                Log::register("Get last of: " . $sql . "\nIn l:" . __LINE__, "mysql_success");
                return ($result->fetch(\PDO::FETCH_ASSOC)['last'] - 1);
            } catch (\Exception $e) {
                Log::register($e->getMessage()."In l:" .__LINE__);
            }
        }
        return false;
    }

    private function addCriteria($criteria)
    {
        return ($criteria instanceof Criteria)
            ? "{$criteria->getTables()} {$criteria->getWhere()} {$criteria->getLimit()} {$criteria->getOrder()}"
            : "";
    }

    private function fills($row = null)
    {
        $data = $this->clear();
        unset($data['table']);
        $class = get_class($this);
        $o = new $class();
        foreach ($data as $key => $value) {
            $set = ucfirst(str_replace('_', '', $key));
            $o->$set($row[$key]);
        }

        return $o;
    }

    public function fill($ob = null)
    {
        $data = $this->clear();
        unset($data['table']);
        foreach ($data as $key => $value) {
            $set = ucfirst(str_replace('_', '', $key));
            if(isset($ob[$key]))
                $this->$set(empty($ob[$key]) ? null : $ob[$key]);
        }
        Saved::set([$this->className() => $data]);
        return $this;
    }

    private function clear()
    {
        $attr = get_class_vars(get_class($this));
        $attr2 = get_class_vars('\\LegionLab\\Troubadour\\Persistence\\Database');


        foreach ($attr as $key => $value)
            if($key != 'table' and key_exists($key, $attr2))
                unset($attr[$key]);

        $data = array();
        foreach ($attr as $key => $value)
            $data[$key] = $this->$key;
        return $data;
    }

    public function vars()
    {
        return $this->clear();
    }

    private function close($prepare, $action, $line)
    {
        if($prepare instanceof \PDOStatement) {
            if($prepare->execute()) {
                Log::register($action . " execute of: " . $prepare->queryString . "\nIn l:$line", "mysql_success");
                foreach ($_POST as $key => $value)
                    unset($_POST[$key]);
                $this->connection = null;
                Saved::destroy();
                return true;
            }
        }
        return false;
    }
}
