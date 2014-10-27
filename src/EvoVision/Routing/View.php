<?php
/**
 * Created by PhpStorm.
 * User: bobo
 * Date: 10/3/2014
 * Time: 4:20 PM
 */

namespace EvoVision\Routing;

class View {
    public static function make($view){
        return file_get_contents(__VIEWS__.$view.'.html');
    }
} 