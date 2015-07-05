<?php
/**
 * Created by PhpStorm.
 * User: michaelkantor
 * Date: 6/13/15
 * Time: 9:55 PM
 */

namespace Drapor\CacheRepository\Exceptions;


class MissingKeyException extends \Exception
{

    public function __construct(array $data){
        parent::__construct("You provided {$data['arguments']} arguments, but only {$data['keys']} key(s). Please check your query.",500);
    }
}