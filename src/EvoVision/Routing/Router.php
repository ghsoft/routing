<?php
/**
 * Created by PhpStorm.
 * User: bobo
 * Date: 10/3/2014
 * Time: 10:36 AM
 */

namespace evovision\Routing;

class Router {

    private $_routes = array();
    protected $matchTypes = array(
        'i'  => '[0-9]++',
        'a'  => '[0-9A-Za-z]++',
        'h'  => '[0-9A-Fa-f]++',
        '*'  => '.+?',
        '**' => '.++',
        ''   => '[^/\.]++'
    );

    /*
     *
     *   Verb	    Path	                    Action	Route Name
     *   GET	    /resource	                index	resource.index
     *   GET	    /resource/create	        create	resource.create
     *   POST	    /resource	                store	resource.store
     *   GET	    /resource/{resource}	    show	resource.show
     *   GET	    /resource/{resource}/edit	edit	resource.edit
     *   PUT/PATCH	/resource/{resource}	    update	resource.update
     *   DELETE	    /resource/{resource}	    destroy	resource.destroy
     */
    public function resource($resource, $controller){
        $this->add("GET", $resource, array("_controller"=>$controller, "_action"=>"index"));
        $this->add("GET", $resource."/create", array("_controller"=>$controller, "_action"=>"create"));
        $this->add("POST", $resource, array("_controller"=>$controller, "_action"=>"store"));
        $this->add("GET", $resource."/[i:id]", array("_controller"=>$controller, "_action"=>"show"));
        $this->add("GET", $resource."/[i:id]/edit", array("_controller"=>$controller, "_action"=>"edit"));
        $this->add("PUT", $resource."/[i:id]", array("_controller"=>$controller, "_action"=>"update"));
        $this->add("DELETE", $resource."/[i:id]", array("_controller"=>$controller, "_action"=>"destroy"));
    }

    /**
     * Create a collection of url's
     * @param $uri
     */
    public function add($method, $route, $target){

        $this->_routes[] = array($method, $route, $target);
        return;
    }

    public function dispatch(){
        $action = $this->action();
        $target = $action["target"];
        $params = $action["params"];
        if(is_array($target)){
            $controller = $target["_controller"];
            $action = $target["_action"];
        }else{
            $controller = $target;
            $action = "index";
        }

        $controller_file =  __CONTROLLERS__. $controller . '.php';
        if(!file_exists($controller_file))throw new \Exception('Controller not found.');
        require $controller_file;
        if(!method_exists($controller, $action))throw new \Exception('Method not found.');

        $instance = new $controller;

        return call_user_func_array(array($instance, $action), $params);
    }

    public function action() {
        $params = array();

        $requestUrl = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

        if (($strpos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }
        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        // Force request_order to be GP
        // http://www.mail-archive.com/internals@lists.php.net/msg33119.html
        $_REQUEST = array_merge($_GET, $_POST);

        foreach($this->_routes as $route) {
            list($method, $_route, $target) = $route;

            $methods = explode('|', $method);
            $method_match = false;

            // Check if request method matches. If not, abandon early. (CHEAP)
            foreach($methods as $method) {
                if (strcasecmp($requestMethod, $method) === 0) {
                    $method_match = true;
                    break;
                }
            }

            // Method did not match, continue to next route.
            if(!$method_match) continue;

            // Check for a wildcard (matches all)
            if ($_route === '*') {
                $match = true;
            } elseif (isset($_route[0]) && $_route[0] === '@') {
                $pattern = '`' . substr($_route, 1) . '`u';
                $match = preg_match($pattern, $requestUrl, $params);
            } else {
                $route = null;
                $regex = false;
                $j = 0;
                $n = isset($_route[0]) ? $_route[0] : null;
                $i = 0;

                // Find the longest non-regex substring and match it against the URI
                while (true) {
                    if (!isset($_route[$i])) {
                        break;
                    } elseif (false === $regex) {
                        $c = $n;
                        $regex = $c === '[' || $c === '(' || $c === '.';
                        if (false === $regex && false !== isset($_route[$i+1])) {
                            $n = $_route[$i + 1];
                            $regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
                        }
                        if (false === $regex && $c !== '/' && (!isset($requestUrl[$j]) || $c !== $requestUrl[$j])) {
                            continue 2;
                        }
                        $j++;
                    }
                    $route .= $_route[$i++];
                }

                $regex = $this->compileRoute($route);
                $match = preg_match($regex, $requestUrl, $params);
            }

            if(($match == true || $match > 0)) {

                if($params) {
                    foreach($params as $key => $value) {
                        if(is_numeric($key)) unset($params[$key]);
                    }
                }

                return array(
                    'target' => $target,
                    'params' => $params
                );
            }
        }
        return false;
    }

    /**
     * @from altoRouter
     * Compile the regex for a given route (EXPENSIVE)
     */
    private function compileRoute($route) {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {

            $matchTypes = $this->matchTypes;
            foreach($matches as $match) {
                list($block, $pre, $type, $param, $optional) = $match;

                if (isset($matchTypes[$type])) {
                    $type = $matchTypes[$type];
                }
                if ($pre === '.') {
                    $pre = '\.';
                }

                //Older versions of PCRE require the 'P' in (?P<named>)
                $pattern = '(?:'
                    . ($pre !== '' ? $pre : null)
                    . '('
                    . ($param !== '' ? "?P<$param>" : null)
                    . $type
                    . '))'
                    . ($optional !== '' ? '?' : null);

                $route = str_replace($block, $pattern, $route);
            }

        }
        return "`^$route$`u";
    }
} 
