<?php
/**
 * Created by PhpStorm.
 * User: michaelkantor
 * Date: 3/30/15
 * Time: 8:31 PM
 */

namespace Drapor\CacheRepository;

use App;
use Illuminate\Console\AppNamespaceDetectorTrait;

class Relation
{
    use AppNamespaceDetectorTrait;
    public $name = '';
    public $columns;

    public $nested;

    /*
    Indicates weather or not a parent relation exists which needs its
    cache bindings cleared when updated.
     */
    public $clearCache;

    /**
     * @return mixed
     */
    public function getClearCache()
    {
        return $this->clearCache;
    }

    /**
     * @param mixed $clearCache
     */
    public function setClearCache($clearCache)
    {
        $this->clearCache = $clearCache;
    }

    public function __construct($name, $clearCache = false)
    {
        $this->name       = $name;
        $this->clearCache = $clearCache;
        $this->nested     = str_contains($name, '.');

        if (!$this->nested)
        {

            if (class_exists($name))
            {
                $model         = App::make($name);
                $this->name    = (new \ReflectionClass($name))->getShortName();
                $this->columns = $model->getColumns();
            }
            else
            {
                $appName = $this->getAppNamespace();
                /** @var \Drapor\CacheRepository\Eloquent\BaseModel $model */
                $modelLocation = config('cacherepository.modelLocation');
                $modelName     = str_singular(studly_case($this->name));
                if (is_array($modelLocation) && array_key_exists($modelName, $modelLocation))
                {
                    $modelLocation = $modelLocation[$modelName];
                }
                $model = App::make(str_replace('\\\\', '\\', sprintf("%s%s\\%s", $appName, $modelLocation, $modelName)));
                //Quickly create an instance of the model and grab its fillable fields from cache.
                $this->columns = $model->getColumns();
            }
        }
        else
        {
            //if its a nested relationship, we don't care about its fillable columns.
            $this->columns = [];
        }
    }

    /**
     * @return mixed
     */
    public function getColumns()
    {
        return $this->columns;
    }

    public function __toString()
    {
        return $this->name;
    }
}
