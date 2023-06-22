# easyQuery / mysqleq
PHP library used to make MySQLi queries simpler and more easier to control.

## mysqleq
**mysqleq** is the library that extends MySQLi to add the **easyQuery** functionality while keeping everything from its parent.

## easyQuery
EasyQuery is the function that allows for easier and quicker to write operations to the database and adds improvements to conventional queries.
- Simplifies query in a single line with full functionality.
- Makes using dynamic parameters easier.
- Makes controlling errors easier.
- Automatically logs errors, execution time, etc.
- Avoids nested queries from slowing the parents down and causing issues, cause easyQuery only returns the results after capturing all of them.
- Allows for centralized control of database operations.

### Examples

#### Query

Doing queries with easyQuery is, as the name indicates, easy.

~~~
// We execute a query on our database connection (mysqleq object) and store the results.
$sql = $mysqleq->easyQuery("SELECT id, name, surname FROM users");

// We iterate the returned array
foreach ($sql as $key => $value) {

	$id      = $value["id"];
	$name    = $value["name"];
	$surname = $value["surname"];

	// We use the data however we want
	echo "ID: " . $id . ", Name: " . $name . " " . $surname . ".";

}
~~~

#### Error control

Errors are easy to control using the different kinds of return values.

Checking if a query has failed or not is as easy as checking **if the returned value is an array or not**, as an error will return **FALSE**.

~~~
$sql = $mysqleq->easyQuery("SELECT id, name, surname FROM users");

// If there are no errors
if (is_array($sql)) {

    foreach ($sql as $key => $value) {

        $id      = $value["id"];
        $name    = $value["name"];
        $surname = $value["surname"];

        echo "ID: " . $id . ", Name: " . $name . " " . $surname . ".";

    }

    if ($sql === []) echo "No users found.";

// If something goes wrong
} else {
    echo "ERROR READING USERS: " . $mysqleq->easyQueryError();
}
~~~