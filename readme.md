<h1>What Does This Do?</h1>

<p>
This laravel package gives a fluid way to cache your queries while speeding up your <b>Eloquent</b> workflow.
</p>
<h2>
Setup:
</h2>

<p>
Install the composer package 
</p>
     composer require "drapor/cache-repository"

<p>
Add the service Provider to your app.php
</p>
     'Drapor\CacheRepository\CacheRepositoryServiceProvider' 

<h2>
Usage 
</h2>
<p>
Step 1 : Modify our Model 
</p>

      <?php namespace app\Models;

          use Drapor\CacheRepository\Eloquent\BaseModel;

             class User extends BaseModel
             { ... }
      ?>

<p>
Step 2: Create a Repository Class
</p>

      <?php namespace app\Repositories;
      use Drapor\CacheRepository\CacheRepository;
        
         class UserRepository extends CacheRepository
        {
            public function __construct(User $user)
            {
              parent::__construct($user, 'user');
              
              //Create a Relation instance and pass the second argument as a boolean
              //indicating wether or not it should be removed from the cache when the User object is updated.
              
              $task = new Relation('task',true);
              
              //Only call $this->setRelations() if you wish to eager load these relations before every call. This method accepts both
              //instances of Relation and strings. 

             $this->setRelations([
                'group', $task
             ]);
           }
        }

<p> 
    Now You can Inject the Repository wherever you need to use it.
    This can be done in quite a few ways due to Laravel's magic, but we're 
    going to keep it simple and use a common example.
</p>

<p>Step 3 : Inject The Repo </p>


     <?php namespace app/controllers;

     use Repositories/UserRepository; 
     use Request;

     class UserController extends Controller
     {

    protected $repository;  
    
    public function __construct(UserRepository $repository)
    {
      $this->repository = $repository;
    }

    public function index()
    {
       $limit = Request::input('limit');

       $users = $this->repository
       ->orderBy('name','ASC')
       ->paginate($limit);

       return response()->json($users);
    }

    public function getSadUsers()
    {
       $limit = Request::input('limit');

       //Paginate accepts a second argument for search parameters.
       //This should be an array of arrays, each with at least a key and a value.

       $params =  [
       [ 
          'key'   => 'sad',
          'value' => 'true'
       ],
       [
           'key'        => 'happiness',
            'operator'  => '<',
            'value'     => '2',
            'keyword'   => 'AND'
       ]
       ];

       //Alternatively you can call Argument::extract(Request::input(),'search')
       //and any search information will automatically be formatted

       $users = $this->repository
       ->with('equity','orders')
       ->paginate($limit,$params);

       return response()->json($users->toJson());

    }

    public function find($id)
    {
      $user = $this->repository->with('tasks')->find($id);

      return response()->json($user->toJson());
    }

    public function update($id)
    {
       //The Repository class will automatically clean out any input
       //that isn't fillable by our model.
       $user = $this->repository->update($id,Request::input());

        return response()->json($user->toJson());
    }
      }
<h2>What should I know?</h2>
<ul>
<li>
Cache can be disabled for any query at any time by calling 
<code>


$user = $this->repository->noCache()->find($id);
</code>
or by using the <code>PlainRepository</code> class instead.
</li>
<li>
Calls to the <code>paginate()</code> method are not cached at this time. If you wish to cache a collection, you should use the <code>whereCached()</code> method directly from your Repository
</li>
<li>
You can alter the time a model is cached by calling <code>setCacheLifeTime()</code> before any method
</li>
</ul>

<h2>
Planned Feature List
</h2>

<ul>
<li>
Cached json serialization of each entity. <code> 
$books = $this->respository
->with('books')
->whereCached('name','harry-potter')->
->transformTo(BookTransformer::class);
</code>
</li>
<li>
Global broadcasting of regular model events. Create, delete, restore, etc.
</li>
<li>
Fluid cache for pagination responses, that is updated when an element of the collection is modified and does not 
modify the whole collection each time. 
</li>

</ul>
