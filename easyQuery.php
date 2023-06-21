<?php

	// Funció per fer consultes facilment
	if (!function_exists("easyQuery")) {

		/* Variable que utilitza easyQuery i easyQueryError */
		GLOBAL $easyQueryData, $easyQueryError;
		$easyQueryData = array();

		/**
		 * Funció que facilita les operacions amb la base de dades i el tractament d'errors.
		 *
		 * @param object $conn objecte mysqli de la connexió amb la base de dades.
		 *
		 * @param string $query String amb la consulta/operació.
		 *
		 * @param array $params Array amb els paràmetres de bind_param o NULL.
		 *                      El primer valor ha de ser el string amb els caràcters que representen els tipus de dades.
		 *                      Si no s'ha d'enllaçar cap paràmetre es pot passar NULL.
		 *
		 * @param string $return Tipus de claus de l'array que es retorna. Si es "i" els resultats seràn arrays numerats, si es "s" les claus seràn els noms de les columnes.
		 *                       Per motiu de compatibilitat amb versions anteriors, si es reb un enter o ningún valor serà "i" per defecte.
		 *
		 * @return mixed Si la consulta s'executa correctament i retorna valors es retornarà un array bidimensional que contindrà les files, i les files contindràn les columnes.
		 *               Si la consulta falla es retornarà FALSE.
		 *               Si una operació d'escriptura s'executa correctament es retornarà TRUE.
		 *               Si la consulta s'executa correctament pero no retorna cap valor es retornarà NULL.
		 */
		function easyQuery($conn, $query, $params = null, $tipus_return = "i") {

			GLOBAL $easyQueryData; // Dades de les consultes executades.
			GLOBAL $easyQueryError; // Compatibilitat

			if (is_numeric($tipus_return)) {
				if ($tipus_return > 0) $tipus_return = "i";
				else                   $tipus_return = null;
			}

			// Array amb informació de la operació actual
			$qdata = [
				/*"connection" => $conn,*/
				"query"      => $query,
				"parameters" => $params,
				"return"     => $tipus_return,
				"error"      => "",
				"start"      => microtime(true),
				"end"        => null,
				"time"       => null
			];

			// Comprovació de la variable de connexió
			if ($conn == null || gettype($conn) != "object" || !($conn instanceof mysqli) || $conn->connect_error) {

				if ($conn == null)                   $qdata["error"] .= "El paràmetre de connexió es null. ";
				else if (gettype($conn) != "object") $qdata["error"] .= "El paràmetre de connexió no es un objecte (" . gettype($conn) . "). ";
				else if (!($conn instanceof mysqli)) $qdata["error"] .= "El paràmetre de connexió no es una instancia de mysqli. ";
				else if ($conn->connect_error)       $qdata["error"] .= "El paràmetre de connexió te connect_error. ";

				$easyQueryData[] = $qdata;
				return false;

			}

			// Comprovació de la variable de la consulta
			if ($query == null) {
				$qdata["error"] .= "La consulta es buida.";
				$easyQueryData[] = $qdata;
				return false;
			}

			// Addició de comentari a la consulta per a facilitar el debug des de phpmyadmin
			//$query .= "\n\n# Consulta easyQuery | Usuari executor: " . (isset($_SESSION["pauser"]) ? $_SESSION["pauser"] : "(unknown)");

			// Preparació de la consulta
			if ($sqlp = @$conn->prepare($query)) {

				// Assignació de paràmetres (si n'hi ha)
				if ($params != NULL) {

					// Creem referencies
					$params_ref = [];
					foreach($params as $key => $value) $params_ref[$key] = &$params[$key];

					// Executem el bind_param
					$bind_param = call_user_func_array([$sqlp, "bind_param"], $params_ref);

					// Control d'errors
					if ($bind_param == false) {
						$qdata["error"] .= "Els parametres no s'han pogut enllaçar: " . mysqli_error($conn);
						$easyQueryData[] = $qdata;
						return false;
					}

				}

				// Execució de la consulta
				if ($sqlp->execute()) {

					if ($tipus_return == null) {

						// Si el return es NULL significa que no s'espera cap valor
						// Això s'utilitza quan es fan operacions d'escriptura
						// Retornem les files afectades per la operació

						$affected_rows = mysqli_affected_rows($conn);

						$qdata["end"] = microtime(true);
						$qdata["time"] = $qdata["end"] - $qdata["start"];
						$easyQueryData[] = $qdata;

						return $affected_rows;

					} else {

						$results = $sqlp->get_result();

						if (is_bool($results)) {

							// Si retorna un booleà en comptes d'un objecte retornem el resultat pero guardem un error.

							$qdata["error"] .= "Ha retornat booleà quan s'esperava un objecte.";
							$qdata["end"] = microtime(true);
							$qdata["time"] = $qdata["end"] - $qdata["start"];
							$easyQueryData[] = $qdata;
							return $results;

						} else {

							$rows = $results->num_rows;
							$return_array = array();

							while ($row = $results->fetch_assoc()) {
								$return_array[] = $row;
							}

							if (count($return_array) > 0) {

								// Si s'ha retornat alguna cosa es formata tal com s'ha demanat en el paràmetre $tipus_return

								switch ($tipus_return) {

									case "s":
										// Per defecte ja està formatat aixó
										break;

									case "i":
									default:
										// Es crea un altre array on es canvien les claus nominals per numériques
										foreach ($return_array as $key => $value) {
											$new_array = [];
											foreach ($value as $key2 => $value2) $new_array[] = $value2;
											$return_array[$key] = $new_array;
										}

								}

								// Es guarda el temps de finalització i es retornen els resultats

								$qdata["end"] = microtime(true);
								$qdata["time"] = $qdata["end"] - $qdata["start"];
								$easyQueryData[] = $qdata;

								return $return_array;

							} else {

								// Si no es retorna res guardem un missatge d'avís i el temps de finalització i retornem NULL
								// Es retorna NULL i no FALSE perque no es un error com a tal

								$qdata["error"] .= "No s'ha retornat cap resultat.";
								$qdata["end"] = microtime(true);
								$qdata["time"] = $qdata["end"] - $qdata["start"];
								$easyQueryData[] = $qdata;
								return null;

							}

						}

					}

				} else {

					// No s'ha pogut executar la query, guardem l'error i el temps de finalització i retornem FALSE

					$qdata["error"] .= "Error d'execució: " . mysqli_error($conn) . " ";
					$qdata["end"] = microtime(true);
					$qdata["time"] = $qdata["end"] - $qdata["start"];
					$easyQueryData[] = $qdata;
					return false;

				}

			} else {

				// No s'ha pogut preparar la query, guardem l'error i el temps de finalització i retornem FALSE

				$qdata["error"] .= "Error de preparació: " . @mysqli_error($conn) . " ";
				$qdata["end"] = microtime(true);
				$qdata["time"] = $qdata["end"] - $qdata["start"];
				$easyQueryData[] = $qdata;
				return false;
			}

			// La funció mai hauria d'arribar a aquest punt perque totes les casualistiques acaben en return
			// Tot i així, es guarda un missatge d'error per si acàs

			if ($qdata["error"] == "") $qdata["error"] = "La funció s'ha executat fins al final. Error report: " . mysqli_error($conn) . " ";
			$qdata["end"] = microtime(true);
			$qdata["time"] = $qdata["end"] - $qdata["start"];
			$easyQueryData[] = $qdata;

			// Compatibility
			$easyQueryError = easyQueryError();

		}


		/* Funció que retorna un array amb totes les dades de la última consulta (o del número de consulta especificat) */
		function easyQueryData($n = null, $key = null) {

			GLOBAL $easyQueryData;

			$max = count($easyQueryData) - 1;

			// Si $n es null vol dir que es retornarà l'ultima entrada de l'array de dades
			if ($n === null) {

				if ($key === null) {

					return $easyQueryData[$max];

				} else {

					if (isset($easyQueryData[$max][$key])) return $easyQueryData[$max][$key];
					else                                   return null;

				}


			// Si $n es true es retornarà un array amb totes les dades o amb les que es demanin
			} else if ($n === true) {

				if ($key === null) {

					return $easyQueryData;

				} else {

					$array_return = [];

					foreach ($easyQueryData as $i => $queryData) {
						if (isset($queryData[$key])) $array_return[] = $queryData[$key];
						else                         $array_return[] = null;
					}

					return $array_return;

				}


			// Si $n es un número es retornarà les dades d'aquella consulta (o false si no existeix)
			} else if (is_numeric($n)) {

				if ($n > $max || $n < 0) {

					return false;

				} else {

					if ($key === null) {

						return $easyQueryData[$n];

					} else {

						if (isset($easyQueryData[$n][$key])) return $easyQueryData[$n][$key];
						else                                 return null;

					}

				}

			// Si no encaixa en cap cas es retornarà false
			} else {

				return false;

			}

			//return $easyQueryData[$n];

		}

		/* Funció que retorna l'error de la última consulta (o del número de consulta especificat) */
		function easyQueryError($n = null) {

			return easyQueryData($n, "error");

		}

		/* Funció que retorna un array amb el temps que va portar la última consulta (o del número de consulta especificat) */
		function easyQueryTime($n = null) {

			return easyQueryData($n, "time");

		}

	}

?>