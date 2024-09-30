<?php

if (!class_exists("mysqleq")) {

	/**
	 * PHP library used to make MySQLi queries simpler and easier to control.
	 */
    class mysqleq extends mysqli {

		private $easyQueryData = []; // Variable used by easyQuery and easyQueryError

		/**
		 * Facilitates the operations with the database and error control.
		 *
		 * @param string $query String with the query/operation.
		 *
		 * @param array $params Array containing the parameters used in bind_param, or NULL if not required.
		 *                      The first value must be a String representing the type of the variables to bind.
		 *                      The rest of the values are the variables to bind in order.
		 *                      If no parameters have to be binded, a NULL or empty array can be passed.
		 *
		 * @return mixed If a query succeeds and returns something it will be a bidimensional array containing rows, which will contain the column values.
		 *               If a query succeeds but returns nothing it will return an empty array.
		 *               If a writing operation succeeds it will return the number of affected rows. Note that it can be 0 even if it succeeds.
		 *               If the operation fails it will return FALSE.
		 */
		function easyQuery($query, $params = null) {

			// Array containing the current operation's data
			$qdata = [
				"query"      => $query,
				"parameters" => $params,
				"error"      => "",
				"start"      => microtime(true),
				"end"        => null,
				"time"       => null
			];

			// Checking the connection variable
			if ($this->connect_error) {

				$qdata["error"] .= "The connection has an error. ";

				$this->easyQueryData[] = $qdata;
				return false;

			}

			// Checking the operation variable
			if ($query == null) {
				$qdata["error"] .= "The query is empty.";
				$this->easyQueryData[] = $qdata;
				return false;
			}

			// Adding a comment for a better debug through phpmyadmin
			//$query .= "\n\n# easyQuery | Usuari executor: " . (isset($_SESSION["user"]) ? $_SESSION["user"] : "(unknown)");

			// If there are parameters to bind we prepare, bind_param and execute
			if (is_array($params) && count($params) > 0) {

				// We prepare the query
				if ($sqlp = $this->prepare($query)) {

					// Parameter binding

					// We create the references
					$params_ref = [];
					$params_long_data = [];
					foreach($params as $key => $value) {
						// No need to reference the parameter types string
						if ($key == 0) {
							$params_ref[$key] = $value;

						} else {
							// Checks if the type is b (blob) and stores the value away
							if ($params[0][$key] == "b") {
								$params_ref[$key] = null;
								$params_long_data[$key] = $value;

							// We create a reference for all other parameter types
							} else {
								$params_ref[$key] = &$params[$key];
							}
						}
					}

					// bind_param execution
					$bind_param = call_user_func_array([$sqlp, "bind_param"], $params_ref);

					// We run send_long_data for each blob found in the parameters
					if (count($params_long_data) > 0) {
						foreach ($params_long_data as $key => $blob) {
							$sqlp->send_long_data($key, $blob);
						}
					}

					// Error control
					if ($bind_param == false) {

						$type_total   = strlen($params[0]);
						$param_total  = count($params)-1;
						$symbol_total = substr_count($query, "?");

						if      ($type_total  != $param_total)  $qdata["error"] .= "Parameter binding error - ammount of types and values do not match (".$type_total."/".$param_total.").";
						else if ($param_total != $symbol_total) $qdata["error"] .= "Parameter binding error - ammount of question marks and values do not match (".$symbol_total."/".$param_total.").";
						else                                    $qdata["error"] .= "Parameter binding error - " . $this->error;

						$qdata["error"] .= "Parameters have not been binded: " . $this->error;
						$this->easyQueryData[] = $qdata;
						return false;

					}

					// We execute the query
					if ($sqlp->execute()) {

						$results = $sqlp->get_result();

					} else {

						// The query could not be executed. We store the details of the error, ending time and return FALSE.

						$qdata["error"] .= "Execute error: " . $this->error . " ";
						$qdata["end"]    = microtime(true);
						$qdata["time"]   = $qdata["end"] - $qdata["start"];
						$this->easyQueryData[] = $qdata;
						return false;

					}

				} else {

					// The query could not be prepared. We store the details of the error, ending time and return FALSE.

					$qdata["error"] .= "Prepare error: " . $this->error . " ";
					$qdata["end"]    = microtime(true);
					$qdata["time"]   = $qdata["end"] - $qdata["start"];
					$this->easyQueryData[] = $qdata;
					return false;

				}

			// If there are no parameters to bind we attempt to do a direct query
			// This is done so operations like CHECK TABLE, which don't support prepared statements, can be executed
			} else {

				if (!($results = $this->query($query, MYSQLI_STORE_RESULT))) {

					$qdata["error"] .= "Execution error with no prepare: " . $this->error . " ";
					$qdata["end"]    = microtime(true);
					$qdata["time"]   = $qdata["end"] - $qdata["start"];
					$this->easyQueryData[] = $qdata;
					return false;

				}

			}


			// We treat the execution

			if (is_object($results)) $n_rows = $results->num_rows;
			else                     $n_rows = null; // direct query which returns a bool

			// If the number of rows equals NULL it means it was not a SELECT
			if ($n_rows === null) {

				// We return the number of affected rows

				$affected_rows = mysqli_affected_rows($this);

				$qdata["end"]    = microtime(true);
				$qdata["time"]   = $qdata["end"] - $qdata["start"];
				$this->easyQueryData[] = $qdata;

				return $affected_rows;

			// If the number of rows is bigger than zero it means results have been returned
			} else if ($n_rows > 0) {

				// We put the rows in an array to return it

				$return_array = array();

				while ($row = $results->fetch_assoc()) {
					$return_array[] = $row;
				}

				$results->free_result();

				if (count($return_array) > 0) {

					// We store the finish time and return the results
					// We also store an error message (which isn't really an error) to log that everything went well

					$qdata["error"]  = "The function has executed properly and the results were stored.";
					$qdata["end"]    = microtime(true);
					$qdata["time"]   = $qdata["end"] - $qdata["start"];
					$this->easyQueryData[] = $qdata;

					return $return_array;

				} else {

					// If the return array is empty but rows have been returned something went wrong
					// We store an error message and return FALSE

					$qdata["error"] .= "The function returned ".$n_rows." rows but the results were lost in the process.";
					$qdata["end"]    = microtime(true);
					$qdata["time"]   = $qdata["end"] - $qdata["start"];
					$this->easyQueryData[] = $qdata;
					return false;

				}

			} else {

				// If the returned row number equals 0 it means no results were returned.
				// We store a warning message and the ending time and return an empty array.
				// We retun an empty array and not FALSE cause this is not an error.
				// We no longer return NULL like before to not break iterations.

				$qdata["error"] .= "No results were returned.";
				$qdata["end"]    = microtime(true);
				$qdata["time"]   = $qdata["end"] - $qdata["start"];
				$this->easyQueryData[] = $qdata;
				return [];

			}

			// This point should never be reached as all possibilities end in return.
			// Even then, we store an error message just in case.

			if ($qdata["error"] == "") $qdata["error"] = "The function reached its ending. Error report: " . $this->error . " ";

			$qdata["end"]    = microtime(true);
			$qdata["time"]   = $qdata["end"] - $qdata["start"];
			$this->easyQueryData[] = $qdata;

		}


		/**
		 * Returns an array with all the data from the last operation (or of the specified operation number).
		 *
		 * @param int $n Number of the operation you want to get data from.
		 *               If NULL, it will return the last operation.
		 *               If TRUE, it will return an array of all operations in this execution.
		 *
		 * @param string $key Key of the array that you want to recover.
		 *                    If NULL it will return an array with all the data of the operation/s.
		 *
		 * @return mixed String or array (multi or one dimensional) depending on the parameters.
		 */
		function easyQueryData($n = null, $key = null) {

			$max = count($this->easyQueryData) - 1;

			// If $n is NULL it means the last entry will be returned.
			if ($n === null) {

				if ($key === null) {

					return $this->easyQueryData[$max];

				} else {

					if (isset($this->easyQueryData[$max][$key])) return $this->easyQueryData[$max][$key];
					else                                         return null;

				}


			// If $n is TRUE it will return an array with all the data or the data asked for.
			} else if ($n === true) {

				if ($key === null) {

					return $this->easyQueryData;

				} else {

					$array_return = [];

					foreach ($this->easyQueryData as $i => $queryData) {
						if (isset($queryData[$key])) $array_return[] = $queryData[$key];
						else                         $array_return[] = null;
					}

					return $array_return;

				}


			// If $n is a number it will return the data of that operation (or FALSE if it doesn't exist).
			} else if (is_numeric($n)) {

				if ($n > $max || $n < 0) {

					return false;

				} else {

					if ($key === null) {

						return $this->easyQueryData[$n];

					} else {

						if (isset($this->easyQueryData[$n][$key])) return $this->easyQueryData[$n][$key];
						else                                 return null;

					}

				}

			// If no case fits it will return FALSE.
			} else {

				return false;

			}

		}

		/**
		 * Returns the error message of the last operation (or the specified one).
		 *
		 * @param int $n Number of the operation of which you want to get the error message from.
		 *               If NULL, it will return the last operation.
		 *               If TRUE, it will return an array of all operations in this execution.
		 *
		 * @return mixed String or array (multi or one dimensional) depending on the parameters.
		 */
		function easyQueryError($n = null) {

			return easyQueryData($n, "error");

		}

		/**
		 * Returns the execution time of the last operation (or the specified one).
		 *
		 * @param int $n Number of the operation of which you want to get the execution time from.
		 *               If NULL, it will return the last operation.
		 *               If TRUE, it will return an array of all operations in this execution.
		 *
		 * @return mixed String or array (multi or one dimensional) depending on the parameters.
		 */
		function easyQueryTime($n = null) {

			return easyQueryData($n, "time");

		}

	}
}

?>