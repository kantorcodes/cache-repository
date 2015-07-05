<?php
/**
 * Created by PhpStorm.
 * User: michaelkantor
 * Date: 6/23/15
 * Time: 4:50 AM
 */

namespace Drapor\CacheRepository;


class Argument
{
    public $value;
    public $key;
    public $operator;
    public $keyword;

    /**
     * @param $key
     * @param $value
     * @param $operator
     * @param $keyword
     */
    public function __construct($key,$value,$operator = '=',$keyword = 'AND'){

        $this->value    = $value;
        $this->key      = $key;
        $this->operator = $operator;
        $this->keyword  = $keyword;
    }

    public static function extract(array $input,$arrayKey)
    {
        if(array_key_exists($arrayKey, $input))
        {
            $filtered  = $input['search'];
            $formatted = [];
            $i         = 0;

            foreach ($filtered as $key => $value)
            {
                $search = [
                    'key'      => $key,
                    'value'    => $value,
                    'operator' => '='
                ];
                
                if ($i >= 1)
                {
                    $search['keyword'] = 'AND';
                }

                $i++;
                $formatted[] = $search;
            }
        }
        else
        {
            return [];
        }

    }


    /**
     * @return string
     */
    public function __toString()
    {
        return (string)sprintf('%s|%s|%s|%s',
            $this->key,
            $this->value,
            $this->operator,
            $this->keyword
        );
    }

}