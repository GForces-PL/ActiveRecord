# ActiveRecord

A new implementation of the Active Record pattern using Attributes available since PHP8. This way there are no magic methods, just actually defined properties in models.

## Main Features

- no magic properties
- real types for properties instead of string
- no setters and getters needed
- objects created directly by PDO
- lazy loaded relations
- really fast

## Usage

To use this implementation, any model representing a table in the database, must inherit from the Gforces\ActiveRecord\Base class.

### Properties

All properties that correspond to columns in the table should be marked with the Column attribute by adding the comment #[Column].
You can define the property type and its visibility according to your needs.  
That's all!
```PHP
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class Vehicle extends Base
{
    #[Column] public int $id;
    #[Column] public string $make;
    #[Column] public string $model;
}

$vehicle = Vehicle::find(1);
$vehicle->make = 'BMW';
$vehicle->model = 'X1';
$vehicle->save();
```

### Relations

Relationships are as simple as properties. They are defined in a natural way by specifying the type and visibility of the property and add an attribute to indicate the type of the relationship.
```PHP
class Vehicle extends Base
{
    #[Column] 
    public int $id;
    #[Column]
    public int $owner_id;

    #[BelongsTo]
    public Owner $owner;
}

class Owner extends Base
{
    #[Column] 
    public int $id;
    #[Column] 
    public string $name;

    #[HasMany]
    #[ArrayShape([Vehicle::class])]
    public array $vehicles;
}

$vehicle = new Vehicle;
$vehicle->owner = new Owner();

$owner = new Owner();
$owner->vehicles = [new Vehicle()];
$owner->vehicles[0]->make = 'BMW';
$owner->save(); // or $owner->vehicles[0]->save(); 
```

### Validators

Currently, only two simple validators are implemented. Feel free to add pull request with new validators.
In order to use validator you have to add another attribute to the property:
```PHP
class Vehicle extends Base
{
    #[Column]
    #[Required]
    public int $id;
    
    #[Column]
    #[Length(max: 30, message: 'Make is too long')]
    public string $make;
    
    #[Column]
    #[Length(min: 10, max: 30)]
    public string $model;
}
```

### Setting up connection

ActiveRecord uses PDO connection. There are two ways to configure connection with your database:
1. Setting connection directly
```PHP
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Connection;
Base::setConnection(new Connection($dsn, $username, $password));
```
It will set up the same connection for all models. You can still set different connection for specific model:
```PHP
use Gforces\ActiveRecord\Connection);
Vehicle::setConnection(new Connection($dsn, $username, $password));
```
2. Using ConnectionProvider

If you want the connection to be created only when it is needed, it is better to use a ConnectionProvider. You can write your own provider or use the default one:
```PHP
Base::setConnectionProvider(new Dsn($dsn, $username, $password));
```

### Finders
The following finders are implemented as these were needed so far.
```PHP
Vehicle::find($id);
Vehicle::findAll($criteria, $orderBy, $limit, $offset, $select);
Vehicle::findFirst($criteria, $orderBy);
Vehicle::findFirstByAttribute($attribute, $value);
Vehicle::findFirstByAttributes($attribute);
Vehicle::findAllBySql($query, $params);
```

### isNew property
This is a built-in property that determines whether an object is stored in the database.

### Access to modified attributes
There is a special property $keepAttributeChanges set on each model that decides if the object should keep the original values. For performance reasons, this functionality is disabled by default.
If it is enabled, each object has access to the original values that were loaded from the database. Additionally it also optimises UPDATE queries with only changed values and do not execute at all if no value was changed.
```PHP
use Gforces\ActiveRecord\Base;

class Vehicle extends Base 
{
    protected static bool $keepAttributeChanges = true;
    
    public function isMakeChanged(): bool
    {
        return $this->isAttributeChanged('make');
    }
}

$vehicle = Vehicle::find($id);
$vehicle->save(); // UPDATE query is not executed
```

## Known limitations

- currently, no custom primary keys are supported. You must have an $id property to use all the features
- not all relations fully implemented
- only few sample validators implemented
- no documentation, but code is self-documenting
- there is no tests :(
