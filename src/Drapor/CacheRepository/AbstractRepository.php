<?php namespace Drapor\CacheRepository;

use Drapor\CacheRepository\Contracts\EloquentRepositoryInterface;
use Drapor\CacheRepository\Eloquent\BaseModel;
use Drapor\CacheRepository\Exceptions\MissingKeyException;
use Drapor\CacheRepository\Exceptions\MissingValueException;
use Drapor\CacheRepository\Relation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class AbstractRepository implements EloquentRepositoryInterface
{

    /* @var $model \Eloquent */
    protected $model;
    /* @var $query \Eloquent */
    protected $query;
    /* @var $array */
    protected $order;
    /* @var $isSorted */
    protected $isSorted;

    protected $name;
    /* @var $relations Collection */
    protected $relations;

    protected $defaultAccessible = ['id', 'created_at', 'updated_at'];

    protected $withTrashed;

    protected $betweenOperator;

    /* @var $arguments Collection */
    protected $arguments;

    protected $updatesChildren;

    /**
     * @param BaseModel $model
     * @param $name
     */
    public function __construct(BaseModel $model, $name)
    {
        $this->model           = $model;
        $this->name            = str_singular($name);
        $this->withTrashed     = false;
        $this->updatesChildren = true;
        $this->betweenOperator = '%BETWEEN%';
        $this->arguments       = new Collection();
        $this->supportsDeletes = array_key_exists('deleted_at', $this->getFillableColumns());

        //Alternatively we could have done property_exists($model,'forceDeleting');
    }

    /*
    @return BaseModel
     */
    abstract public function update($id, array $data);
    /*
    @return BaseModel
     */
    abstract public function find($id);

    /*
    @return BaseModel
     */
    abstract public function create(array $data);

    /*
    @return Bool
     */
    abstract public function destroy($ids);

    /*
    @return Bool
     */
    abstract public function forceDelete($id);

    /*
    @return BaseModel
     */
    abstract public function restore($id);

    /**
     * @return Collection|BaseModel
     */
    public function get()
    {
        if ($this->query == null)
        {
            $this->query = $this->newQuery()
                ->with($this->getRelations());

            return $this->query->get();
        }
        return $this->query->get();
    }

    /**
     * @return \Eloquent
     */
    public function getQuery()
    {
        if ($this->query == null)
        {
            return $this->query = new $this->model;
        }
        return $this->query;
    }

    /**
     * @param $operator
     * @return $this
     */
    public function setBetweenOperator($operator)
    {
        $this->betweenOperator = $operator;
        return $this;
    }

    /**
     * @return $this
     */
    public function withTrashed()
    {

        $this->withTrashed = true;

        return $this;
    }
    /**
     * @return BaseModel|static[]
     */
    public function first()
    {
        return $this->query->first();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->query->toArray();
    }

    /**
     * Create a new instance of the eloquent model
     * If withTrashed() was called earlier and this model
     * can support soft deletes, then we'll go ahead and
     * prepare that for the proceeding calls
     * @return BaseModel
     */
    public function newQuery()
    {
        //This simple check should ensure that if called
        //after a query was already executed, that the proceeding one will
        //be brand new.
        if ($this->query !== null)
        {
            $this->arguments = new Collection();
        }

        $this->query = new $this->model;
        if ($this->withTrashed && $this->supportsDeletes)
        {
            //SomeModel::withTrashed()->...
            $this->query = $this->query->withTrashed();
        }

        return $this->query;
    }

    /**
     * @return array
     */
    public function getFillable()
    {
        return $this->model->getFillable();
    }

    /**
     * @param $key
     * @param $value
     * @param $operators
     * @param array $keywords
     * @throws MissingKeyException
     * @throws MissingValueException
     */
    protected function setArguments($key, $value, array $operators, array $keywords)
    {
        $key      = (array) $key;
        $value    = (array) $value;
        $argCount = count($key);

        for ($i = 0; $i < $argCount; $i++)
        {
            if (!array_key_exists($i, $key))
            {
                $data = [
                    'arguments' => $argCount,
                    'keys' => count($key),
                ];
                throw new MissingKeyException($data);
            }

            if (!array_key_exists($i, $value))
            {
                $data = [
                    'arguments' => $argCount,
                    'values' => count($value),
                ];
                throw new MissingValueException($data);
            }

            $arg           = new Argument($key[$i], $value[$i]);
            $arg->keyword  = array_key_exists($i, $keywords) ? $keywords[$i] : 'AND';
            $arg->operator = array_key_exists($i, $operators) ? $operators[$i] : '=';
            $this->arguments->push($arg);
        }
    }

    /**
     * @return Collection
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param array $relations
     */
    public function setRelations(array $relations)
    {
        $collection = [];
        foreach ($relations as $relation)
        {
            //If the developer passes in a Relation,
            // we'll just roll with that, otherwise, we'll type check
            if ($relation instanceof Relation)
            {
                $collection[$relation->name] = $relation;
                continue;
            }

            if (!is_array($relation))
            {
                $collection[$relation] = new Relation($relation);
            }
            else
            {
                //**will depreciate
                $r = new Relation($relation['name']);
                if (array_key_exists('clearcache', $relation))
                {
                    $r->setClearCache($relation['clearcache']);
                }
                $collection[$r->name] = $relation;
            }
        }
        $this->relations = new Collection($collection);
    }

    /**
     * @return Relation[]|array
     */
    public function getRelations()
    {
        if (count($this->relations) >= 1)
        {
            $relations = $this->relations->pluck('name');
            //Laravel 5.1 compatability change
            if ($relations instanceof Collection)
            {
                $relations = $relations->toArray();
            }
            return $relations;
        }
        else
        {
            return [];
        }
    }

    /**
     * @param array|string $relations
     * @return $this
     */
    public function with($relations)
    {
        if (is_array($relations))
        {
            $this->setRelations($relations);
        }
        elseif (is_string($relations))
        {
            //If the developer provided multiple arguments we will merge them
            $otherArgs = func_get_args();
            $relations = array_merge($otherArgs, [$relations]);
            $this->setRelations($relations);
        }
        return $this;
    }

    /**
     * @param string $key
     * @param string $direction
     * @return $this
     */
    public function orderBy($key, $direction)
    {
        $this->order = [
            'key' => $key,
            'direction' => $direction,
        ];
        $this->isSorted = true;
        return $this;
    }
    /**
     * @param $id
     * @return BaseModel
     */
    protected function retrieve($id)
    {

        $namesOfRelations = $this->getRelations();

        return $this->newQuery()->with($namesOfRelations)->find($id);
    }

    /**
     * We Want To Check That the array being passed has at least
     * one term to search by...
     * @param  $perPage
     * @param  array $searchArgs
     * @return $this
     */
    public function paginate($perPage, array $searchArgs = [])
    {
        $nameOfRelations = $this->getRelations();
        $argsCount       = count($searchArgs);

        if ($argsCount > 0)
        {
            $model = $this->newQuery()
                ->with($nameOfRelations);

            foreach ($searchArgs as $arg)
            {
                $item           = new Argument($arg['key'], $arg['value']);
                $item->operator = array_key_exists('operator', $arg) ? $arg['operator'] : '=';
                $item->keyword  = array_key_exists('keyword', $arg) ? $arg['keyword'] : 'AND';
                $this->arguments->push($item);
            }

            $model = $this->getModelFromSearch($model);

            $this->query = $model->paginate($perPage);

            return $this->query;
        }

        $this->query = $this->newQuery()
            ->with($nameOfRelations)
            ->paginate($perPage);

        return $this->query;
    }

    /**
     * @param String $key
     * @param String $value
     * @param array $operators
     * @param array $keywords
     * @return BaseModel|[BaseModel]
     * @throws MissingKeyException
     * @throws MissingValueException
     */
    public function where($key, $value, array $operators = ['='], array $keywords = ['AND'])
    {
        $namesOfRelations = $this->getRelations();

        /** @var BaseModel $model */
        $model = $this->newQuery()->with($namesOfRelations);

        //if this method is called from a cache callback
        //then the arguments will have already been set.
        //so there's no reason to set them twice
        if ($this->arguments->isEmpty())
        {
            $this->setArguments($key, $value, $operators, $keywords);
        }

        /** @var \Eloquent $model */
        $model       = $this->getModelFromSearch($model);
        $this->query = $model;

        return $model->get();
    }

    /**
     * Returns a filtered out model using search params passed.
     * We know that all entities will have an id column, so by
     * default we allow those queries through, all other keys need to pass the test.
     * otherwise we keep building up the query.
     * We will check for % signs indicating a LIKE query.
     * We will skip all empty K/V pairs.
     * @param \Illuminate\Database\Query\Builder $model
     * @return BaseModel
     */
    protected function getModelFromSearch($model)
    {
        $results = $model;

        if ($this->isSorted && !empty($this->order['key']) && !empty($this->order['direction']))
        {
            $results->orderBy($this->order['key'], $this->order['direction']);
        }
        $fillable = $this->getFillableColumns();

        foreach ($this->arguments as $key => $arg)
        {
            if (in_array($arg->key, $this->defaultAccessible) || in_array($arg->key, $fillable))
            {

                if ($arg->value == null)
                {
                    continue;
                }

                if (strpos($arg->value, $this->betweenOperator) !== false)
                {
                    //if the value is to be in a ballpark we shall simply call
                    //laravels method for this and continue onward..
                    $betweenThese = explode($this->betweenOperator, $arg->value);
                    $results      = $results->whereBetween(
                        $arg->key,
                        [$betweenThese[0], $betweenThese[1]]
                    );
                    continue;
                }

                if (preg_match('/`(.*?)`/', $arg->value) >= 1)
                {
                    $arg->keyword = 'OR';
                    $arg->value   = str_replace('`', '', $arg->value);
                }
                if (preg_match('/%(.*?)%/', $arg->value) >= 1)
                {
                    //if the string starts & ends with % and
                    //if the operator isn't LIKE, we will make it so.
                    $arg->operator = 'LIKE';
                }

                if ($key <= 0 && !isset($arg->operator))
                {
                    $results = $results->where($arg->key, $arg->operator, $arg->value);

                }
                else
                {
                    $results = $results->where(
                        $arg->key,
                        $arg->operator,
                        $arg->value,
                        $arg->keyword
                    );

                }
            }
        }
        return $results;
    }

    public function getFillableColumns()
    {
        $fillableColumns = [];

        //Get All The Columns From The Related Models
        if ($this->updatesChildren && count($this->relations) >= 1)
        {
            foreach ($this->relations->toArray() as $relation)
            {
                if (!$relation->nested)
                {
                    /** @var BaseModel $relatedModel */
                    $name = $relation->name;

                    $relatedModel = $this->newQuery()->$name()->first();

                    if ($relatedModel !== null)
                    {
                        $columns = array_combine($relatedModel->getColumns(), $relatedModel->getColumns());
                        $guarded = $relatedModel->getGuarded();
                        foreach ($guarded as $gaurd)
                        {
                            unset($columns[$gaurd]);
                        }
                        $fillableColumns[] = $columns;
                        //Unset properties that have been explicitly marked as gaurded.
                    }
                }
            }
        }
        $m       = $this->newQuery();
        $gaurded = $m->getGuarded();
        $columns = array_combine($m->getColumns(), $m->getColumns());
        foreach ($gaurded as $gaurd)
        {
            unset($columns[$gaurd]);
        }
        $modelColumns = array_merge($m->getColumns(), $m->getFillable());
        //Get All The Columns From The Current Model
        foreach ($modelColumns as $key => $col)
        {
            $fillableColumns[$key] = $col;
        }
        return $fillableColumns;
    }

    /**
     * @param array $data
     * @return array
     */
    public function filterModelData(array $data)
    {
        $filtered = [];
        $notUsed  = [];
        //Basically prevent failure by using the valid columns to make sure
        //that any input passed in actually exists in the DB.
        foreach ($data as $key => $attribute)
        {
            if (in_array($key, $this->getFillableColumns(), true))
            {
                $filtered[$key] = $attribute;
            }
            else
            {
                $notUsed[$key] = $attribute;
            }
        }
        return [$filtered, $notUsed];
    }

    /* Catch any missed Eloquent methods
     * @return mixed
     */
    public function __call($method, $params)
    {
        $namesOfRelations = $this->getRelations();
        /** @var BaseModel $model */
        $model = $this->newQuery()->with($namesOfRelations);
        return call_user_func_array([$model, $method], $params);
    }
}
