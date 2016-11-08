<?php

namespace LegionLab\Troubadour\Control;

use LegionLab\Troubadour\Collections\Session;
use LegionLab\Troubadour\Collections\Settings;
use LegionLab\Troubadour\Persistence\Database;


/**
 * Control Control
 *
 * Created by PhpStorm.
 * User: Leonardo Vilarinho
 * Date: 02/07/2016
 * Time: 20:18
 */

abstract class Controller extends Template
{
    /**
     * Atributos publicos
     * @var array - links de CSS e scripts para template.
     * @var null - instancia do template criada
     */


    /**
     * Atributos privados
     * @var array - variaveis de comunicacao com view, lista contendo diretorio, arquivo do view e parametros url
     * @var null - titulo da pagina
     * @var true - se tem o nao template
     */
    private $variables = array();
    private $view = array("dir" => null, "archive" => null);
    private $hasTemplate = true;
    private $params = array();


    /**
     * Control constructor.
     * Pega um diretorio e arquivo padrao de visao e cria um novo template passando essa classe.
     */
    public function __construct()
    {
        $array_post = is_array(Session::get('post_backup')) ? Session::get('post_backup') : array();
        foreach ($array_post as $key => $value) {
            $_POST[$key] = $value;
        }

        unset($_SESSION['post_backup']);

        $this->view['dir'] = mb_strtolower($_GET['controller']);
        parent::__construct();

        $this->view['archive'] = mb_strtolower($_GET['method']);

        if(isset($_GET['params'])) {
            $this->params = $_GET['params'];
        }

        if(isset($_GET['vars'])) {
            $this->variables = $_GET['vars'];
            $this->variables = $_GET['vars'];
        }
    }

    public function allVars()
    {
        return $this->variables;
    }


    /**
     * Pega um parametro da URL
     *
     * @param $index - indice do parametro em numero
     * @param $success - callback de sucesso
     * @param $error - callback de erro
     * @return bool|mixed - valor do parametro ou false
     */
    protected function param($index, $success = '@', $error = '@' )
    {
        if(key_exists($index, $this->params)) {
            if($success !== '@')
                $success($this->params[$index]);
            else
                return $this->params[$index];
        }
        else {
            if($error !== '@')
                $error(false);
        }

        return false;
    }

    /**
     * Desativa o template do controller
     */
    protected function noTemplate()
    {
        $this->hasTemplate = false;
        return $this;
    }


    /**
     * Vai para outra pagina.
     *
     * @param $controller - controlador a ser chamado
     * @param $method - metodo a ser chamado
     * @param array $attributes - atributos do link
     */
    protected function to($controller, $method, $attributes = array())
    {
        foreach ($_POST as $key => $value)
            unset($_POST[$key]);
        $attr = "";
        if(is_array($attributes)) {
            $attr = implode("/", $attributes);
        }
        else {
            if(!empty($attributes)) {
                $attr = $attributes;
            }
        }
        Session::set('token', false);
        $controller = mb_strtolower($controller);
        $method = mb_strtolower($method);
        $attr = mb_strtolower($attr);
        header("Location:" . DOMAIN . "/{$controller}/{$method}/{$attr}");
    }

    /**
     * Troca a view padrao desse controlador. (caminho a ser iniciado da pasta /view)
     *
     * @param null $directory - diretorio onde esta a view
     * @param null $archive - arquivo da view
     * @return $this
     */
    protected function view($directory = null, $archive = null)
    {
        if(!is_null($directory)) {
            $this->view['dir'] = $directory;
        }
        if(!is_null($archive)) {
            $this->view['archive'] = $archive;
        }
        return $this;
    }

    /**
     * Pega o arquivo de view, incluindo el no script atual.
     */
    private function  getView()
    {
        if($this->view['dir'] == "/")
            $this->view['dir'] = "";
        else
            $this->view['dir'] .= "/";

        if(is_dir(ROOT . "public/visions/{$this->view['dir']}")) {
            if(file_exists(ROOT . "public/visions/{$this->view['dir']}{$this->view['archive']}.php")) {
                $dir = ROOT . "public/visions/";
                include "{$dir}{$this->view['dir']}/{$this->view['archive']}.php";
            }
            else {
                Errors::display('Arquivo da interface não foi encontrado');
            }
        }
        else {
            Errors::display('Diretório da interface não foi encontrado');
        }

    }

    /**
     * Metodo para mostrar uma tela, chama o template que cuidara do resto, se template tiver desativado
     * chama a view apenas.
     */
    protected function display()
    {
        if(!$this->ajax()) {
            if($this->hasTemplate) {
                include ROOT."settings/skeleton/".Settings::get('skeleton').'/'.'index.php';
            }
            else {
                $this->getView();
            }
        }

    }


    /**
     * Adiciona uma variavel para a visao.
     *
     * @param $key - nome da variavel
     * @param $value - valor da variavel
     * @return mixed
     */
    public function attr($key, $value = '@')
    {
        if($value !== '@') {
            $this->variables[$key] = $value;
            return $this;
        }
        else {
            if(array_key_exists($key, $this->variables)) {
                return $this->variables[$key];
            } else
                return '';
        }
        return $this;
    }


    public static function ajax($success = '@', $error = '@')
    {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : null;
        $result = (strtolower($isAjax) === 'xmlhttprequest');

        if($result and $success !== '@')
            $success();
        elseif(!$result and $error !== '@')
            $error();

        return $result;
    }

    public function objectInSession(&$object)
    {
        if (!is_object ($object) && gettype ($object) == 'object') {
            return ($object = unserialize (serialize ($object)));
        }

        return $object;
    }

    public function toArray($object)
    {
        if(is_array($object)) {

            $variables = array();
            foreach ($object as $o) {
                array_push($variables, $this->scan($o));
            }
            return $variables;
        }
        else if(is_object($object)) {
            return $this->scan($object);
        }
        return false;
    }

    private function scan($o)
    {
        if(is_object($o) and $o instanceof Database) {
            $vars = $o->vars();
            foreach ($vars as $k => $v) {
                if(is_object($v)) {
                    $vars[$k] = $this->toArray($v);
                }
            }
            return $vars;
        }
        return false;
    }
}
