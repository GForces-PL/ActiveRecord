# ActiveRecord

A new implementation of the Active Record pattern using Attributes available since PHP8. No magic methods, no configuration just actually defined properties in models.

## Main Features

- no magic properties
- real types for properties instead of string
- no setters and getters needed
- lazy loaded relations
- fast

## Usage

To use this implementation, any model representing a table in the database, must inherit from the Gforces\ActiveRecord\Base class.

### Properties

All properties that correspond to columns in the table should be marked with the Column attribute.
You can define the property type, nullable and its visibility according to your needs.  
That's all!
```PHP
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class Vehicle extends Base
{
    #[Column] public int $id;
    #[Column] public string $make;
    #[Column] public ?string $model;
}

$vehicle = new Vehicle();
$vehicle->make = 'BMW';
$vehicle->model = 'X1';
$vehicle->save();

Vehicle::find(1)->model;
```

The $id property is auto_increment as default until any other column is marked as auto_increment, or it is disabled for `$id` column. 

```PHP
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class Vehicle extends Base
{
    #[Column(autoIncrement: false)] public int $id;
    #[Column] public string $make;
}
```

The $id property is also a primary key as default and can be used to quickly find objects. You can also set different columns using #[PrimaryKey] attribute:
```PHP
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;
use Gforces\ActiveRecord\PrimaryKey;

class Vehicle extends Base
{
    #[Column(autoIncrement: true)] public int $evidenceNo;
    #[Column] #[PrimaryKey] public string $make;
    #[Column] #[PrimaryKey] public string $model;
}
```

#### Built-in property types
All scalar built-in types are supported.

#### DateTime
_\DateTime_ properties are stored in the database as formatted string 'Y-m-d H:i:s' and converted back to _\DateTime_ when retrieved.
It is not necessary for the column in the database to be also of type DATE or DATETIME, but when converting an invalid value to a DateTime object it may throw an error.

#### Unit Enums
If property is an enum it is stored in the database as a string of case name. A column in the database may or may not be of the enum type. When it is retrieved from the database it is converted to Enum case or will throw an error when has invalid value.
```PHP
enum Status
{
    case online;
    case offline;
}

class User extends Base
{
    #[Column] 
    public int $id;
    #[Column] 
    public Status $status;
}
```

#### Backed Enums
If property is an enum it is stored in the database as a value of enum case. A column in the database may or may not be of the enum type. When it is retrieved from the database it is converted to Enum value or will throw an error when has invalid value.

#### Arrays
If property is an array it is stored in database as an encoded JSON string. Your column in database can be JSON or any string type. Once object is retrieved from database it is decoded back to array.

#### Stringable objects
You can create any class which implements StringableProperty interface to use custom objects as your model property. It has to implements constructor with string argument which is used to create the object when retrieved from database and __toString() method when it is stored to database.

```PHP
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\StringableProperty;

class UserImage implements StringableProperty
{
    public readonly string $path;
    
    protected static string $defaultPath = '/images/default.png';

    public function __construct(string $path = '')
    {
        $this->path = $path ?: static::$defaultPath;
    }

    public function getUrl(): string
    {
        return "https://example.com{$this->path}";
    }

    public function __toString(): string
    {
        return $this->path;
    }
}

class User extends Base
{
    #[Column] public int $id;
    #[Column] public UserImage $image;
}

User::find(1)->image->getUrl();
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
#### Setting connection directly
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
#### Using ConnectionProvider

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
Vehicle::findAllBySql($query);
```
### Criteria
Criteria can be a string with SQL expression or just an assoc array of properties and theirs values. 
```PHP
User::findAll(['name' => 'Phil', 'male' => Sex::male, 'disabled' => false]);
User::findAll("`name` = 'Phil' AND `sex` = 'male' AND `disabled` = 0");
```
When using assoc array, as default it builds a quoted SQL expressions with AND operator. For array values operator IN is used and IS for nulls.
```PHP
User::findAll(['name' => 'Phil', 'male' => [Sex::male, Sex::female], 'diabled' => null]);
User::findAll("`name` = 'Phil' AND `sex` IN ('male', 'female') AND `disabled` IS NULL");
```

#### Property expressions
To obtain other comparisons, you can use the AttributeExpression like below:
```PHP
use \Gforces\ActiveRecord\PropertyExpression;
User::findAll([
    'name' => PropertyExpression::eq('Phil'), // `name` = 'Phil'  
    'male' => PropertyExpression::ne([Sex::male, Sex::female]), // NOT IN 
    'diabled' => PropertyExpression::ne(null), // IS NOT NULL
    'age' => PropertyExpression::gt(21), // `age` > 21
    'weight' => PropertyExpression::le(50), // `weight` <= 50
    'verified' => PropertyExpression::ge(new DateTime('-2 week')),
]);
```
You can also use shorter syntax
```PHP
use function \Gforces\ActiveRecord\PropertyExpressions\eq;
use function \Gforces\ActiveRecord\PropertyExpressions\ne;
use function \Gforces\ActiveRecord\PropertyExpressions\gt;
use function \Gforces\ActiveRecord\PropertyExpressions\le;
use function \Gforces\ActiveRecord\PropertyExpressions\ge;

User::findAll([
    'name' => eq('Phil'), // `name` = 'Phil'  
    'male' => ne([Sex::male, Sex::female]), // NOT IN 
    'diabled' => ne(null), // IS NOT NULL
    'age' => ge(21), // `age` >= 21
    'weight' => le(50), // `weight` <= 50
    'verified' => gt(new DateTime('-2 week')),
]);
```

#### SQL Expressions
You can combine assoc values with custom SQL expressions:
```PHP
use Gforces\ActiveRecord\Expression;
use Gforces\ActiveRecord\Expressions\Simple;
use function \Gforces\ActiveRecord\PropertyExpressions\gt;
use function \Gforces\ActiveRecord\PropertyExpressions\le;

User::findAll([
    'name' => 'Phil', 
    Expression::or([
        'age' => gt(21),
        'weight' => le(50),
    ]),
    '1 = 1',
]);
```

### isNew property
This is a built-in property that determines whether the object is stored in the database.

### Access to modified attributes
There is a special property $keepAttributeChanges set on each model that decides if the object should keep the original values. For performance reasons, this functionality is disabled by default.
If it is enabled, each object has access to the original values that were loaded from the database. Additionally, it also optimises UPDATE queries with only changed values and do not execute at all if no value was changed.
```PHP
use Gforces\ActiveRecord\Base;

class Vehicle extends Base 
{
    protected static bool $keepAttributeChanges = true;
    
    #[Column]
    public string $make;
    
    public function isMakeChanged(): bool
    {
        return $this->isAttributeChanged('make');
    }
}

$vehicle = Vehicle::find($id);
$vehicle->save(); // UPDATE query is not executed
$vehicle->isMakeChanged(); // false
$vehicle->make = 'VW';
$vehicle->isMakeChanged(); // true
$vehicle->save() // UPDATE query executed
```

### Static methods
You can use assoc array syntax, the same as for Criteria, in multiple static methods for your models
```PHP
use function \Gforces\ActiveRecord\PropertyExpressions\ge;

Product::insert(['name' => 'Bill', 'age' => 21]);
Product::updateAll(['adult' => true], criteria: ['age' => ge(18)]);
Product::deleteAll(['age' => ge(18)]);
Product::count(['age' => 21]);
Product::exists(['name' => 'Bill', 'adult' => true]);
```

## Known limitations

- primary keys no fully implemented. With some relations still 'id' column is needed
- not all relations fully implemented
- only few sample validators implemented
- no documentation, but code is self-documenting

## Used by
- [Puzzle Factory](https://puzzlefactory.com)
- [SlidingTiles](https://slidingtiles.com)
- [Puzzle Online](https://epuzzle.info)
