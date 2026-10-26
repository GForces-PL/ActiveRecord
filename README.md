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

#### Primary keys and partial selections

For an existing object, `save()` and `remove()` require every primary-key column
(or the default `id` column) to have been selected and to have a non-null value.
Partial selections remain readable, but filling in a missing key afterward does
not make them writable: reload the object with its complete key first.

The library remembers the key on hydration and after insertion, independently
of `$keepAttributeChanges`. Changing a persisted object's key causes `save()`
and `remove()` to throw `ActiveRecordException` before executing a write.
Setting `$isNew = false` manually does not establish a persisted identity.
To change a key deliberately, use `updateAll()` with explicit criteria and reload
the object afterward.

#### Built-in property types
Scalar property types `int`, `float`, `string`, and `bool` are supported.
Properties populated from query results must declare a supported named type;
nullable declarations such as `?int` are supported. Untyped properties, union
and intersection types, and unsupported named types produce an
`ActiveRecordException` identifying the property.

SQL `NULL` is preserved for nullable properties before conversion (including
enums, dates, arrays, and custom string values). It is rejected for non-nullable
properties. Omitted result columns leave property defaults/uninitialized values
unchanged; unknown result columns are ignored.

#### DateTime
_\DateTime_ properties are stored in the database as formatted string 'Y-m-d H:i:s' and converted back to _\DateTime_ when retrieved.
Empty date strings are rejected; use SQL `NULL` for nullable dates.
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
If property is an array it is stored in database as an encoded JSON string. Your column in database can be JSON or any string type. Once an object is retrieved, JSON arrays and objects are decoded into PHP arrays,
including nested objects: use `$model->data['nested']['value']`, not
`$model->data['nested']->value`.

Use `#[JsonArray(associative: false)]` to preserve nested JSON objects as
`stdClass`. The top-level result is always a PHP array, whether the JSON root is
an array or an object; map keys are preserved. This controls JSON representation,
not element typing or conversion into model classes.

```PHP
use Gforces\ActiveRecord\JsonArray;

#[Column]
#[JsonArray(associative: false)]
public array $items; // e.g. $this->items[0]->name

#[JsonArray(associative: false)]
private array $queryResult; // helper populated by a matching SELECT alias

#[JsonArray(associative: true)]
private array $settings; // nested associative arrays
```

The attribute works on private properties and does not require `#[Column]`.
Both an omitted attribute and `#[JsonArray]` default to `associative: true`.

Malformed JSON and scalar JSON roots (including JSON `null`) are rejected with an
`ActiveRecordException` identifying the property. SQL `NULL` remains `null` for a
nullable array property; it is distinct from an empty JSON container (`[]` or
`{}`), which decodes to `[]`. Encoding failures throw `JsonException` before the
write, including invalid UTF-8. JSON checks also apply during change tracking.

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

Saving initialized relations synchronizes their links and automatically saves **new**
related objects. Existing related objects keep their other unsaved changes: save
those objects explicitly to persist their ordinary attributes and relations.

- `BelongsTo` saves a new related object first, then assigns its `id` to the
  owner's foreign key. An explicit `null` clears a nullable foreign key.
- `HasMany` treats the initialized array as the complete desired collection.
  It assigns the parent's `id` to each child's foreign key, saving new children
  and updating only that key on existing children. The related class comes from
  `relatedClass` or the existing `ArrayShape` convention.
- Children omitted from `HasMany` are detached (`FK = NULL`) when the child's
  typed foreign-key property allows null; otherwise they are deleted. Cleanup
  affects only rows still belonging to this parent. `[]` clears the collection.
- `HasOne` follows the same replacement and cleanup rules. Nullable `HasOne`
  returns `null` when no record exists; non-nullable `HasOne` retains its
  default-object behavior. Explicitly assigning `null` clears the relation.
- `HasAndBelongsToMany` synchronizes the join table. Removing items deletes only
  their join rows, never the related records. New related objects are saved;
  existing related objects are not saved.
- Uninitialized relations are skipped. `$owner->vehicles[] = $vehicle` loads the
  existing collection before appending. To add without loading the collection,
  assign `$vehicle->owner = $owner` and save the vehicle.

For `BelongsTo`, changing only the foreign key makes that key authoritative and
invalidates the cached relation on save. Changing only the relation updates the
key. Changing both is accepted only when they agree; a conflict throws
`AssociationException` before cascading writes. This comparison works even when
attribute-change tracking is disabled.

Inverse references to an already persisted object do not recursively save it.
Circular insert dependencies requiring an unsaved object's ID throw
`AssociationException`. These relations still assume an `id` key. New related
objects use their normal validation even when the initiating call uses
`save(false)`.

Collections represent the caller's desired database state, including collections
loaded earlier or with filtering. There is no identity map: saving a stale or
partial collection may undo later changes or remove omitted children. Refresh
or adjust collections before saving them.

Cascading saves do not automatically open a transaction. For atomic writes on
one connection, wrap the operation in a transaction; otherwise a later failure
can leave earlier objects persisted. In-memory state is not automatically restored
by rolling back a database transaction.

### Validators

Currently, only few simple validators are implemented. Feel free to add pull request with new validators.
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
    #[Required]
    #[Length(min: 10, max: 30)]
    public string $model;
    
    #[Column]
    #[Url]
    public string $website;
    
    #[Column]
    #[Matches('/[(A-H|J-N|P|R-Z|0-9)]{17}/')]
    public string $vin;
}
```

Validation runs triggered by `isValid()`, `getErrors(true)`,
`getErrorMessages(true)`, or `save()` replace previous errors. Correcting a field
and validating again clears its old errors; repeated validation does not duplicate
messages. `getErrors()` and `getErrorMessages()` without validation return the
last validation result.

`Length`, `ByteLength`, `Matches`, and `Url` skip uninitialized fields, as well as
empty values. Combine them with `Required` when a value must be provided.
`Required` rejects uninitialized fields on new objects; on existing objects it
allows omitted fields (for partial selections), but still rejects initialized
empty values. Validation contexts (`always`, `onCreate`, `onUpdate`) continue to
apply normally.

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

A provider creates and returns a connection without changing the global connection.
`Base` caches the connection when it resolves the provider. Models without their
own connection or provider share the default connection configured on `Base`.
A model-specific provider leaves that default and other models' connections intact:

```PHP
Vehicle::setConnectionProvider(new Dsn($vehicleDsn, $username, $password));
```

Calling `$provider->getConnection()` directly creates a new connection on each
call; it does not register it on `Base`. Use `Base::setConnectionProvider()` for
lazy default configuration or `Base::setConnection()` for an existing connection.


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

#### SQL input safety

Array criteria quote attribute names and values. Identifier quoting escapes the
database's delimiter; use an array of identifier components with
`Connection::quoteIdentifier(['table', 'column'])` for a qualified identifier.
A string containing a dot is treated as one identifier.

Raw criteria strings, `$orderBy`, `$select`, joins, custom SQL, and raw expressions
are SQL code. Do not concatenate untrusted input into them. Map user-selected
sort/filter fields to an allowlist of permitted columns; quoting alone does not
control which columns a caller may access.

The condition `'0'` is preserved as `WHERE 0`, and a limit of `0` is preserved as
`LIMIT 0`. Empty criteria still mean no restriction, including for `updateAll()`
and `deleteAll()`.

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

`Expression::not($expression)` negates the entire expression using parentheses,
including groups built with `Expression::and()` or `Expression::or()`.

Empty `IN` lists (including criteria such as `['id' => []]`) produce `1 = 0` and
match no rows. Empty `NOT IN` lists produce `1 = 1` and impose no restriction,
including for nullable columns. This applies to reads and bulk writes alike:
`deleteAll(['id' => PropertyExpression::notIn([])])` matches every row.
Non-empty lists retain standard SQL behavior.

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

With change tracking enabled, snapshots keep independent copies of `DateTime`,
`StringableProperty`, and nested arrays/JSON objects. In-place mutations are
tracked; replacing a date or custom string value with an equivalent value does
not trigger an update. Dates are compared at the stored `Y-m-d H:i:s` precision,
custom string values by their string representation, and arrays by JSON encoding.
`getOriginalValues()` returns independent copies of these mutable values.

New objects insert all initialized columns, including PHP defaults such as
`false` and `null`. Leave a property uninitialized to omit it from the insert and
use the database default. After insertion, the snapshot includes the generated
ID, so an immediate unchanged `save()` does not issue an update.

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
