<?php
/**
 * Created by PhpStorm.
 * User: michaelkantor
 * Date: 3/25/15
 * Time: 8:37 PM
 */

namespace Drapor\CacheRepository\Contracts;

use Illuminate\Support\Collection;
use Drapor\CacheRepository\Eloquent\BaseModel;

interface EloquentRepositoryInterface
{
    /**
     * @return array
     */
    public function getFillable();

    public function getQuery();

    /**
     * @param mixed $relations
     */
    public function setRelations(array $relations);

    /**
     * @return Collection|BaseModel
     */
    public function get();

    /**
     * @return BaseModel|static[]
     */
    public function first();

    /**
     * @return array
     */
    public function toArray();

    /**
     * @param array $relations
     * @return $this
     */
    public function with($relations);

    /**
     * @param string $key
     * @param string $direction
     * @return $this
     */
    public function orderBy($key,$direction);

    /**
     * @return $this
     */
    public function withTrashed();

    /**
     * @param $perPage
     * @param $searchArgs
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage, array $searchArgs = []);

    /**
     * @param $id
     * @param array $data
     * @return $this
     */
    public function update($id, array $data);


    /**
     * @param $id
     * @return bool
     */
    public function destroy($id);


    /**
     * Clears the cache bindings for the user
     * and restores him
     * @param $id
     * @return BaseModel
     */

    public function restore($id);
    /**
     * @param array $data
     * @return BaseModel
     */
    public function create(array $data);


    /**
     * @param $id
     * @return BaseModel
     */
    public function find($id);

    /**
     * @param String $keys
     * @param String $values
     * @param array $operators
     * @param array $keywords
     * @return \Illuminate\Support\Collection
     */
    public function whereCached($keys, $values, array $operators = ['='], array $keywords = ['AND']);

    /**
     * @param Int $minutes
     * @return Void
     */
    public function setCacheLifeTime($minutes);

    /**
     * @param String $key
     * @param String $value
     * @param array $operators
     * @param array $keywords
     * @return BaseModel
     */
    public function where($key, $value, array $operators = ['='], array $keywords = ['AND']);

    /**
     * @param String $relation
     * @param String $key
     * @param String $value
     * @return Collection|BaseModel
     */

    public function whereHas($relation,$key,$value);
    /**
     * @param array $data
     * @return array
     */
    public function filterModelData(array $data);
}
