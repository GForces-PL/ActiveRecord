# ActiveRecord
A new implementation of the Active Record pattern using Attributes available since PHP8. This way there are no magic methods, just actually defined properties in models.
##Usage
To use this implementation, any model reflecting a table in the database must inherit from the Gforces\ActiveRecord\Base class.
###Properties
All properties that correspond to columns in the table should be marked with the Column attribute by adding the comment #[Column].
You can define the property type and its visibility according to your needs.  
Thats all!
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
###Relations
Relationships are as simple as properties. We define it in a natural way by specifying the type and visibility of the property and add an attribute to indicate the type of the relationship.
```PHP
class Owner extends Base
{
    #[Column] public int $id;
    #[Column] public string $name;

    #[HasMany]
    #[ArrayShape([Vehicle::class])]
    public array $vehicles;
}

$owner = new Owner();
$owner->vehicles = [new Vehicle()];
$owner->vehicles[0]->make = 'BMW';
$owner->save(); // or $owner->vehicles[0]->save(); 
```

##Known limitations
