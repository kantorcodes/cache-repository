<?php

namespace Drapor\CacheRepository;

use Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Drapor\CacheRepository\Eloquent\BaseModel;
use Drapor\CacheRepository\Contracts\EloquentRepositoryInterface;
use Drapor\Networking\Networking;
use Queue;

/* @var $model \Eloquent  */

class CacheRepository extends AbstractRepository
{

    /* @var $cacheLifeTime int   */
    public $cacheLifeTime;

    /**
     * @param BaseModel $model
     * @param $name
     */
    public function __construct(BaseModel $model, $name)
    {
        parent::__construct($model, $name);
    }

    /**
     * @return int
     */
    public function getCacheLifeTime()
    {
        return $this->cacheLifeTime;
    }

    /**
     * @param int $cacheLifeTime
     * @return $this
     */
    public function setCacheLifeTime($cacheLifeTime)
    {
        $this->cacheLifeTime = $cacheLifeTime;
        return $this;
    }

    /**
     * Manually turn off caching completely.
     * @return $this
     */
    public function noCache()
    {
        $this->cacheLifeTime = -1;
        return $this;
    }

    /**
     * @param $relation
     * @param $key
     * @param $value
     * @return Collection|BaseModel
     */

    public function whereHas($relation, $key, $value)
    {
        $this->query = $this->newQuery();

        $callback  = function () use ($key, $value, $relation) {

            $this->query->whereHas($relation, function ($query) use ($key, $value) {
                /* @var $query Builder  */
                return $query->where($key, $value)->get();
            });
        };
        $argument = new Argument($key, $value);
        $this->arguments->push($argument);

        return $this->cache($callback);
    }

    /**
     * @param $id
     * @param array $data
     * @return $this
     */
    public function update($id, array $data)
    {
        $old = $this->retrieve($id);

        $this->forget('id', $id);

        $filtered = $this->filterModelData($data);

        //Update the current Model

        $old->update($filtered[0]);

        //Wipe any possible cache bindings to parent relationships specified as such
        //in setRelations()
        $this->forgetParentModels($old);

        //Attempt to update the related models
        $this->updateRelation($filtered[1], $old);

        //Finally after the cache has been dumped, call retrieve again
        //to put it back in with the updated fields.

        return $this->retrieve($id);
    }

    /**
     * @param array $data
     * @return BaseModel
     */
    public function create(array $data)
    {
        $filtered        = $this->filterModelData($data);
        $model           = $this->model->create($filtered[0]);

        //Call Find to retrieve related.
        $model           = $this->retrieve($model->id);

        $this->forgetParentModels($model);

        //Finally, return the query for method chaining.
        return $model;
    }

    /**
     * @param $id
     * @return BaseModel
     */
    public function find($id)
    {
        //Cache forever.
        $this->setCacheLifeTime(0);
        //Retrieve first model model from array.
        $argument = new Argument('id', $id);
        $this->arguments->push($argument);

        $this->query = $this->cache(function () use ($id) {
            return $this->retrieve($id);
        });

        //cache() returns a collection, so lets give back the first model.

        return $this->query;
    }


    /**
     * This method will flush all ids from the cache,
     * and then proceed to delete them.
     * If the resource was deleted this will also return false
     * @param $id
     * @return bool
     */
    public function destroy($ids)
    {
        $ids       = (array)$ids;
        $didDelete = false;

        foreach ($ids as $id) {
            $this->forget('id', $id);
        }

        $didDelete =  $this->model->destroy($ids) >= 1;

        return $didDelete;
    }

    public function forceDelete($id)
    {
        $this->forget('id', $id);
        if ($this->supportsDeletes) {
            $this->model->find($id)->forceDelete();
        } else {
            $this->model->find($id)->delete();
        }
    }

    /**
     * This method will restore the model,
     * recache it, and return it.
     * @param $id
     * @return \Illuminate\Support\Collection|null|static
     */
    public function restore($id)
    {
        $this->forget('id', $id);

        $user = $this->newQuery()->find($id);

        if ($this->supportsDeletes) {
            $user->restore($id);
        }

        return $user;
    }

    /**
     * This resolves out of the cache unlike paginated calls.
     * Accurate data should use the regular where().
     * @param  $key
     * @param  $value
     * @param  $operators
     * @param  $keywords
     * @throws MissingValueException
     * @throws MissingKeyException
     * @return Collection
     */
    public function whereCached($key, $value, array $operators = ['='], array $keywords = ['AND'])
    {

        $callback  = function () use ($key, $value, $operators, $keywords) {
            return $this->where($key, $value, $operators, $keywords);
        };

        return $this->cache($callback);
    }

    /**
     * @return array
     */
    public function getFillableColumns()
    {
        return Cache::rememberForever("{$this->name}.fillable", function () {
            return parent::getFillableColumns();
        });
    }

    /**
     * This method make it possible to update distant relatives
     * of a model by passing in key specific columns while
     * updating the cache of related entities if required.
     * @param $filtered
     * @param $model
     * @return BaseModel
     */
    private function updateRelation($filtered, $model)
    {
        if (!$this->updatesChildren || count($filtered) <= 0) {
            return $model;
        }

        foreach ($this->relations->toArray() as $relation) {
            $fieldsToUpdateForRelation = [];
            /** @var BaseModel $relatedModel */

            $name         = $relation->name;
            $relatedModel = $model->$name;

            if (array_key_exists($name, $filtered)) {
            /** @var array $modelFields */
                $modelFields = $filtered[$name];
                foreach ($modelFields as $key => $possibleFieldToUpdate) {
                    if (in_array($key, $relation->columns, true)) {
                        $fieldsToUpdateForRelation[$key] = $possibleFieldToUpdate;
                    }
                }
            }

            if (count($fieldsToUpdateForRelation) > 0) {
                $relatedModel->update($fieldsToUpdateForRelation);

                $model->$name  = $relatedModel;
            }
        }

        return $model;
    }

    /*
     * @var string $idKey
     * @var string $cacheKey
     * @return void
     */

    public static function squash($idKey, $cacheKey)
    {
        Cache::tags($idKey)->flush();

        //Techically the tag flush should be enough to dump it out
        //But if for whatever the developer doesn't pass in an, then
        //this should definately get rid of it.

        if (Cache::has($cacheKey)) {
            Cache::forget($cacheKey);
        }

    }

    /**
     * Forget the cache for a specific Id.
     * @param $key
     * @param $value
     * @param $name
     * @return bool
     */
    public function forget($key, $value, $name = null)
    {
        //Flush out the existing arguments
        $this->arguments = new Collection();
        $cacheArg        = new Argument($key, $value);

        if ($name == null) {
            $name = $this->name;
        }

        $this->arguments->push($cacheArg);

        //This key will only be of the model we want to forget
        $cacheKey  = $this->getCacheKey($name);
        $cacheArgs = unserialize($cacheKey);
        $idKey     = $name.'|'.$cacheArgs['key'].'|'.$cacheArgs['value'];

        self::squash($idKey, $cacheKey);

        //We're going to "broadcast" the cache dump,
        //So any listening parties can also remove their version of model
        Queue::push(function ($job) use ($key, $value, $name)
        {
            $request                   = new Networking();
            $request->options['query'] = true;
            $broadcastUrls             = config('cacherepository.removal_broadcast_urls');

            foreach ($broadcastUrls as $url) {
                $request->baseUrl = $url;

                $payload = [
                    'key'    => $key,
                    'value'  => $value,
                    'name'   => $name
                ];

                $request->send($payload, "/cache/broadcast", 'GET');
            }
            $job->delete();
        });


        return true;
    }

    /**
     * We're going to check for parent models whose relations
     * are cached and can be removed. If the relation exists, we'll
     * forget each relatedee.
     * @param $model
     */

    public function forgetParentModels($model)
    {
        foreach ($this->relations->toArray() as $relation) {
            if ($relation->clearCache) {
                $nameOfRelated  = $relation->name;
                $relatedModels  = $model->$nameOfRelated;

                if ($relatedModels instanceof Collection) {
                    foreach ($relatedModels->toArray() as $relatedModel) {
                        $this->forget('id', $relatedModel['id'], str_singular($relation->name));
                        }
                } elseif ($relatedModels instanceof BaseModel) {
                    $this->forget('id', $relatedModels['id'], str_singular($relation->name));
                }
                    //If the developer specified a relation, but it doesn't contain anything
                    //then there isn't anything to clear anyway.
            }
        }
    }


    /**
     * @param $data
     * @return mixed
     */
    public static function timestamps($data)
    {
        $copy = $data;

        $now = \Carbon\Carbon::createFromTimestamp(time());
        $copy['created_at'] = $now;
        $copy['updated_at'] = $now;

        return $copy;
    }


    /**
     * @param callable $query
     * @return Collection
     */
    private function cache(callable $query)
    {

        //If set to -1, we won't cache anything and simply return the query.
        if ($this->getCacheLifeTime() == -1) {
            return $query();
        }

        //If for whatever reason no arguments are set,
        //We will try to get them from the query
        if ($this->arguments->isEmpty()) {
            $function = new \ReflectionFunction($query);
            $args     = $function->getStaticVariables();
            $this->setArguments($args['key'], $args['value'], $args['operators'], $args['keywords']);
        }

        $cacheKey         = $this->getCacheKey();
        $cacheArgs        = unserialize($cacheKey);
        $idTagKey         = $this->name.'|'.$cacheArgs['key'].'|'.$cacheArgs['value'];

        $collectionTagKey = $this->name;

        if ($this->getCacheLifeTime() === 0) {
        //Infinitely tag all related models no matter what relations are used for later
            //For example, we might call a User object with a certain relation one time,
            //and another the next.
            return Cache::tags($idTagKey, $collectionTagKey)->rememberForever($cacheKey, function () 
                use ($query){
            
                /** @var Collection $this */
                return $query();
            });
        } else {
            return Cache::tags($idTagKey, $collectionTagKey)->remember($cacheKey, $this->getCacheLifeTime(), function () 
                use ($query){
            
                /** @var Collection $this */
                return $query();
            });
        }
    }

    /*
     * @param string $modelName
     * @return array
     */
    private function getCacheKey($modelName = null)
    {
        $args =  [
            'key'       => $this->arguments->implode('key', '|'),
            'value'     => $this->arguments->implode('value', '|'),
            'operators' => $this->arguments->implode('operator', '|'),
            'keywords'  => $this->arguments->implode('keyword', '|'),
            'name'      => $modelName !== null ? $modelName : $this->name,
            'relations' => count($this->relations) >= 1 ? $this->relations->implode('name', '|') : '|'
        ];

        return serialize($args);
    }
}
