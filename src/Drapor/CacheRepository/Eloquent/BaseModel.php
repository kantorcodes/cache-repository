<?php
/**
 * Created by PhpStorm.
 * User: michaelkantor
 * Date: 3/15/15
 * Time: 12:24 AM
 */

namespace Drapor\CacheRepository\Eloquent;

use Cache;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    protected $guarded = ['id'];
    public $columns;
    /* @var $columns array */
     public function __construct($attributes = array())
     {
         parent::__construct($attributes);
         $this->setColumns();
         $this->timestamps = true;
         $this->guarded    = ['id'];
     }

    public function setColumns()
    {
        $table = $this->getTable().'.columns';
        $this->columns = Cache::rememberForever($table, function () use ($table) {
            return \Schema::getColumnListing($table);
        });

        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }
}
