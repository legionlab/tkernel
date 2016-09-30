<?php

namespace LegionLab\Troubadour\Collections;

/**
 * Control Saved
 *
 * Created by PhpStorm.
 * User: Leonardo Vilarinho
 * Date: 13/07/2016
 * Time: 14:17
 */
abstract class Saved implements Collection, Life
{
    /**
     * Cria um novo salvamento de dados usando dados do usuário para setar o nome da variavel
     * e aramazenando o array de post atual
     */
    public static function create()
    {
        Session::set(md5('saved' . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']), $_POST);
    }

    /**
     * Armazena um array personalizado na variavel de salvamento, para caso não esteja armazenando o post.
     *
     * @param $key - lista de dados a ser gravados
     * @param $value - nada
     */
    public static function set($key, $value = '')
    {
        if(is_array($key)) {
            Session::set(md5('saved' . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']), $key);
        }
    }

    /**
     * Pega uma variavel salva no salvamento atual, quando passado a chave, busca no array salvo esse
     * mesmo elemento, se houver encontrado retorna seu valor, se não retorna uma string vazia.
     *
     * @param $key - chave do array, ou seja, nome da variavel
     * @param $callback - função de callback
     * @return string - valor do item encontrado ou string vazia
     */
    public static function get($key, $callback = '@')
    {
        if(isset(Session::get(md5('saved' . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']))[$key])) {
            $return = Session::get(md5('saved' . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']))[$key];
            if($callback !== '@')
                $callback($return);

            return $return;
        }
        else {
            if($callback !== '@')
                $callback(false);
        }
        return '';
    }

    /**
     * Apaga salvamento atual usando unset.
     */
    public static function destroy()
    {
        unset($_SESSION[md5('saved' . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'])]);
    }
}
