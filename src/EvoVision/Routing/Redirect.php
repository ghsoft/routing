<?php
/**
 * Created by PhpStorm.
 * User: bobo
 * Date: 10/8/2014
 * Time: 9:59 AM
 */

namespace evovision\Routing;


class Redirect {
    public function to($url){
        return header("Location: $url");
    }
} 
