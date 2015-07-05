<?php namespace Drapor\CacheRepository;


class PlainRepository extends AbstractRepository
{

    /**
     * @param BaseModel $model
     * @param $name
     */
    public function __construct(BaseModel $model, $name)
    {
        parent::__construct($model,$name);
    }

    public function find($id)
    {
   	   return $this->retrieve($id);
    }

   public function create(array $data)
   {
   	    $filtered        = $this->filterModelData($data);
        $model           = $this->model->create($filtered[0]);

        //Call Find to retrieve related.
        $model           = $this->retrieve($model->id);

        return $model;
   }

   public function update($id,$data)
   {
   	    $model = $this->retrieve($id);
        //dd($old->toArray());

        $filtered = $this->filterModelData($data);

        //Update the current Model

        $model->update($filtered[0]);
        return $model;
   }


    public function destroy($ids)
    {
        $ids       = (array)$ids;
        $didDelete = false; 

        $didDelete =  $this->model->destroy($ids) == 0;
        
        return $didDelete;
    }

    public function forceDelete($id)
    {
        if($this->supportsDeletes)
        {
            $this->model->find($id)->forceDelete();
        }else
        {
            $this->model->find($id)->delete();
        }
    }

      /**
     * This method will restore the model and return it
     * @param $id
     * @return \Illuminate\Support\Collection|null|static
     */
    public function restore($id){

        $model = $this->newQuery()->find($id);

        if($this->supportsDeletes)
        {
            $model->restore($id);
        }

        return $model;
    }
}