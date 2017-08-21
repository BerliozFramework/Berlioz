Entities
========

**Berlioz** purpose you some classes to manage your entities:
* `\Berlioz\Core\Entity\Entity`: a basic entity
* `\Berlioz\Core\Entity\Collection`: a collection of entities
* `\Berlioz\Core\Entity\Pagination`: a pagination manager for your entities

## \Berlioz\Core\Entity\Entity ##

### Declaration ###

```php
class User extends \Berlioz\Core\Entity\Entity implements \JsonSerializable
{
    /** @var int ID of user */
    protected $id_user;
    /** @var string Creation date time of user */
    protected $create_time;
    /** @var string Lastname of user */
    protected $last_name;
    /** @var string Firstname of user */
    protected $first_name;

    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed
     */
    public function jsonSerialize()
    {
        return ['id_user'     => $this->id_user,
                'create_time' => $this->create_time,
                'last_name'   => $this->last_name,
                'first_name'  => $this->first_name];
    }

    /**
     * Get ID user.
     *
     * @return int
     */
    public function getIdUser(): int
    {
        return $this->id_user ?? 0;
    }

    /**
     * Get create time.
     *
     * @return string
     */
    public function getCreateTime(): string
    {
        return $this->create_time ?? '';
    }

    /**
     * Get last name.
     *
     * @return string
     */
    public function getLastName(): string
    {
        return $this->last_name ?? '';
    }

    /**
     * Set last name.
     *
     * @param string $last_name
     */
    public function setLastName(string $last_name)
    {
        $this->lastname = $last_name;
    }

    /**
     * Get first name.
     *
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->first_name ?? '';
    }

    /**
     * Set first name.
     *
     * @param string $first_name
     */
    public function setFirstName(string $first_name)
    {
        $this->firstname = $first_name;
    }
}
```

### Use ###

```php
$user = new User(['id_user'     => 1234,
                  'create_time' => '2017-08-01 10:00:00',
                  'first_name'  => 'Jacques',
                  'last_name'   => 'Dupond']);

// Print: "Dupond Jacques"
printf('%s %s', $user->getLastName(), $user->getFirstName());
```


## \Berlioz\Core\Entity\Collection ##

### Declaration ###

```php
class UserList extends \Berlioz\Core\Entity\Collection
{
    /**
     * Constructor
     *
     * @param \Berlioz\Core\OptionList|null $options Options for object
     */
    public function __construct(OptionList $options = null)
    {
        // To limit usage of this collection
        parent::__construct(__NAMESPACE__ . '\User', $options);
    }
}
```

### Use ###

```php
/** @var User $user1 */
/** @var User $user2 */
/** @var User $user3 */

$userList = new UserList;
$userList[] = $user1;
$userList[] = $user2;
$userList[] = $user3;

foreach ($userList as $user) {
    printf("%s %s\n", $user->getLastName(), $user->getFirstName());
}
```