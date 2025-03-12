# easyQuery / mysqleq
PHP library used to make MySQLi queries simpler and easier to control.

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
	echo "ID: " . $id . ", Name: " . $name . " " . $surname . ".\n";

}
~~~

#### Parameters

Passing parameters is easier than ever. You must pass an array with the contents you would normally input into bind_param.
The first entry of this array should be a string with characters representing the type of every variable, and the rest should be the values in question.
As opposed to using bind_param in mysqli, you can directly input values without needing a variable for each.

~~~
// We execute a query with a parameter array
$sql = $mysqleq->easyQuery("SELECT id, name, surname FROM users WHERE id > ?", ["i", 10]);

// We iterate the returned array
foreach ($sql as $key => $value) {

	$id      = $value["id"];
	$name    = $value["name"];
	$surname = $value["surname"];

	// We use the data however we want
	echo "ID: " . $id . ", Name: " . $name . " " . $surname . ".\n";

}
~~~

#### Error control

Errors are easy to control using the different kinds of return values.

Checking if a query has failed or not is as easy as checking **if the returned value is an array or not**, as an error will return **FALSE**.

~~~
$sql = $mysqleq->easyQuery("SELECT id, name, surname FROM users");

// If something goes wrong the result will be FALSE and not an array
if (!is_array($sql)) {
    throw new Exception("ERROR READING USERS: " . $mysqleq->easyQueryError());
}

// If the query returns no results it will be an empty array
if (!count($sql)) {
    die("No users found.");
}

// Iterates through the results
foreach ($sql as $key => $value) {

    $id      = $value["id"];
    $name    = $value["name"];
    $surname = $value["surname"];

    echo "ID: " . $id . ", Name: " . $name . " " . $surname . ".\n";

}
~~~