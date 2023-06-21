<?php
	GLOBAL $easyQueryError;
	
	if (!function_exists('easyQuery')) {
		
		/* Variable que utilitza easyQuery i easyQueryError */
		GLOBAL $easyQueryData;
		$easyQueryData = array();

		/* Funció per a fer operacions amb la base de dades molt mes facilment.
		 *
		 * $conn ---> objecte mysqli de la connexió amb la base de dades.
		 *            Exemple: $conn = new mysqli($database_servername, $database_username, $database_password, $database_name);
		 * $query --> String amb la consulta/operació.
		 * $params -> Array amb les dades que es faríen servir en el bind_param. El primer valor ha de ser el string amb les lletres que representen els tipus de dades.
		 *            Cada lletra representa una variable: "s" per string i "i" per integer. Si no necesites cap paràmetre, posa un null.
		 *            Exemple: array("iisi", $nombre1, $nombre2, $string, $nombre3);
		 * $return -> Numero de quantes columnes es consulten (quantes variables rebrás). Si el numero no es exacte la consulta fallarà.
		 *
		 * Returns: array bidimensional amb els resultats. El primer index de l'array es el numero del resultat, el segon es el numero del valor retornat.
		 *          $resultat[0] -> (array) primer resultat.
		 *          $resultat[2][1] -> Segona variable del tercer resultat.
		 *          Si la consulta falla es retorna false.
		 */
		function easyQuery($conn, $query, $params, $return) {
			
			GLOBAL $easyQueryData; // En cas d'error, aquesta variable ha de ser global per poder recuperar el missatge d'error.
			
			$qdata = array(
				/*"connection" => $conn,*/
				"query" => $query,
				"parameters" => $params,
				"return" => $return,
				"error" => ""
			);
			
			if ($sqlp=$conn->prepare($query)) {
				
				if ($params != NULL) {
					
					/* Control d'errors */
					
					$to_replace = substr_count($query, "?");
					$params_n   = count($params) - 1;
					$params_n2  = strlen($params[0]);
					
					if ($to_replace != $params_n || $params_n != $params_n2) {
						
						$qdata["error"] .= "Error assigning parameters, the quantity of ? (" . $to_replace . ") i and of parameters (" . $params_n . ", " . $params_n2 . ") doesn't match. ";
						$easyQueryData[] = $qdata;
						return false;
						
					}
					
					/* Assignació de parámetres */
					
					$bind = '$bind_param = $sqlp->bind_param("' . $params[0] . '"';
					
					for ($i=1; $i < count($params); $i++) {
						$bind .= ', $params['.$i.']';
					}
					
					$bind .= ');';
					
					eval($bind);
					
					if ($bind_param == false) $qdata["error"] .= "The parameters couldn't be binded: " . mysqli_error($conn) . " ";
					
				}
				
				if ($sqlp->execute()) {
					$sqlp->store_result();
					
					if ($return > 0) {
						
						$bindresult = '$bind_result = $sqlp->bind_result(';
						
						for ($i=0; $i < $return; $i++) {
							if ($i > 0) $bindresult .= ', ';
							$bindresult .= '$data['.$i.']';
						}
						
						$bindresult .= ');';
						
						eval($bindresult);
						
						if ($bind_result == false) $qdata["error"] .= "The results couldn't be binded: " . mysqli_error($conn) . " ";
						
						$i = 0;
						while ($sqlp->fetch()) {
							
							for ($j=0; $j < count($data); $j++) {
								$return_array[$i][$j] = $data[$j];
							}
							$i++;
							
						}
						
					} else {
						$sqlp->fetch();
					}
					
					if ($return > 0 && isset($return_array)) {
						$easyQueryData[] = $qdata;
						return $return_array;
					} else if ($return == 0) {
						$easyQueryData[] = $qdata;
						return true;
					} else {
						$qdata["error"] .= "The query didn't return any result. ";
						$easyQueryData[] = $qdata;
						return null;
					}
					
				} else {
					$qdata["error"] .= "Execution error: " . mysqli_error($conn) . " ";
					$easyQueryData[] = $qdata;
					return false;
				}
			} else {
				$qdata["error"] .= "Prepare error: " . mysqli_error($conn) . " ";
				$easyQueryData[] = $qdata;
				return false;
			}
			
			if ($qdata["error"] == "") $qdata["error"] = "The function reached the end. Error report: " . mysqli_error($conn) . " ";
			$easyQueryData[] = $qdata;
			
		}

		/* Funció que retorna l'error de la última consulta (o del número de consulta especificat) */
		function easyQueryError($n = null) {
			GLOBAL $easyQueryData;
			$max = count($easyQueryData) - 1;
			if ($n == null || $n > $max) $n = $max;
			return $easyQueryData[$n]["error"];
		}

		/* Funció que retorna un array amb totes les dades de la última consulta */
		function easyQueryInfo($n = null) {
			GLOBAL $easyQueryData;
			$max = count($easyQueryData) - 1;
			if ($n == null || $n > $max) $n = $max;
			return $easyQueryData[$n];
		}
		
	}
?>