<?php

GLOBAL $db, $db_VS, $db_AD, $db_COMU, $host;

if (!function_exists("easyQuery")) {

	/* Variable que utilitza easyQuery i easyQueryError */
	GLOBAL $easyQueryData;
	$easyQueryData = array();

	/**
	 * Funció que facilita les operacions amb la base de dades i el tractament d'errors.
	 *
	 * @param object $conn Objecte mysqli de la connexió amb la base de dades.
	 *
	 * @param string $query String amb la consulta/operació.
	 *
	 * @param array $params Array amb els paràmetres de bind_param o NULL.
	 *                      El primer valor ha de ser el string amb els caràcters que representen els tipus de dades i la resta els valors a enllaçar.
	 *                      Si no s'ha d'enllaçar cap paràmetre es pot passar un array buit o NULL.
	 *
	 * @param string $tipus_return ///A PUNT DE SER DEPRECAT/// En cas de ser un SELECT, tipus de claus de l'array que es retorna. Si es "i" els resultats seràn arrays numerats, si no les claus seràn els noms de les columnes.
	 *
	 * @return mixed Si la consulta s'executa correctament i retorna valors es retornarà un array bidimensional que contindrà les files, i les files contindràn les columnes.
	 *               Si la consulta s'executa correctament pero no retorna cap valor es retornarà un array buit.
	 *               Si una operació d'escriptura s'executa correctament es retornarà el nombre de files afectades.
	 *               Si la consulta falla es retornarà FALSE.
	 */
	function easyQuery($conn, $query, $params = null, $tipus_return = "s") {

		GLOBAL $easyQueryData; // En cas d'error, aquesta variable ha de ser global per poder recuperar el missatge d'error.

		if (is_numeric($tipus_return) && $tipus_return > 0) $tipus_return = "i";

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
		if ($conn == null || !($conn instanceof mysqli) || gettype($conn) != "object" || $conn->connect_error) {

			if      ($conn == null)              $qdata["error"] .= "El paràmetre de connexió es null. ";
			else if (!($conn instanceof mysqli)) $qdata["error"] .= "El paràmetre de connexió no es una instancia de mysqli. ";
			else if (gettype($conn) != "object") $qdata["error"] .= "El paràmetre de connexió no es un objecte (" . gettype($conn) . "). ";
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
		$query .= "\n\n# Consulta easyQuery | Usuari executor: " . (isset($_SESSION["pauser"]) ? $_SESSION["pauser"] : "(unknown)");

		// Si hi ha paràmetres a enllaçar fem prepare, enllaçem, i execute
		if (is_array($params) && count($params) > 0) {

			// Preparació de la consulta
			if ($sqlp = $conn->prepare($query)) {

				// Assignació de paràmetres

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

				// Execució de la consulta
				if ($sqlp->execute()) {

					$results = $sqlp->get_result();

				} else {

					// No s'ha pogut executar la query, guardem l'error i el temps de finalització i retornem FALSE

					$qdata["error"] .= "Error d'execució: " . mysqli_error($conn) . " ";
					$qdata["end"]    = microtime(true);
					$qdata["time"]   = $qdata["end"] - $qdata["start"];
					$easyQueryData[] = $qdata;
					return false;

				}

			} else {

				// No s'ha pogut preparar la query, guardem l'error i el temps de finalització i retornem FALSE

				$qdata["error"] .= "Error de preparació: " . mysqli_error($conn) . " ";
				$qdata["end"]    = microtime(true);
				$qdata["time"]   = $qdata["end"] - $qdata["start"];
				$easyQueryData[] = $qdata;
				return false;

			}

		// Si no hi ha paràmetres a enllaçar intentarem fer una query directament
		// Aixo es fa per poder executar operacions com CHECK TABLE que no soporten els prepared statements
		} else {

			if (!($results = $conn->query($query, MYSQLI_STORE_RESULT))) {

				$qdata["error"] .= "Error d'execució de query sense prepare: " . mysqli_error($conn) . " ";
				$qdata["end"]    = microtime(true);
				$qdata["time"]   = $qdata["end"] - $qdata["start"];
				$easyQueryData[] = $qdata;
				return false;

			}

		}


		// Tractem execucio

		if (is_object($results)) $n_rows = $results->num_rows;
		else                     $n_rows = null; // query directe que retorna bool

		// Si el número de rows retornat es NULL significa que no es un SELECT
		if ($n_rows === null) {

			// Retornem les files afectades per la operació

			$affected_rows = mysqli_affected_rows($conn);

			$qdata["end"]    = microtime(true);
			$qdata["time"]   = $qdata["end"] - $qdata["start"];
			$easyQueryData[] = $qdata;

			return $affected_rows;

		// Si el número de rows retornat es major a 0 significa que s'han retornat resultats
		} else if ($n_rows > 0) {

			// Posem les files en un array per retornar-lo

			$return_array = array();

			while ($row = $results->fetch_assoc()) {
				$return_array[] = $row;
			}

			$results->free_result();

			if (count($return_array) > 0) {

				// Si s'ha retornat alguna cosa es formata tal com s'ha demanat en el paràmetre $tipus_return

				switch ($tipus_return) {

					case "i":
						// Recorro l'array de resultats i borro les claus nominals per fer-les numèriques
						foreach ($return_array as $key => $row) $return_array[$key] = array_values($row);

					default:
						// Per defecte ja està formatat correctament
					break;

				}


				// Es guarda el temps de finalització i es retornen els resultats
				// Es guarda un missatge d'error (que realment no es un error) per deixar constancia de que tot ha anat be

				$qdata["error"]  = "La funció s'ha executat correctament i s'han guardat els resultats.";
				$qdata["end"]    = microtime(true);
				$qdata["time"]   = $qdata["end"] - $qdata["start"];
				$easyQueryData[] = $qdata;

				return $return_array;

			} else {

				// Si no hi ha res al array de retorn pero s'han retornat rows alguna cosa ha anat malament
				// Guardem un missatge d'error i retornem FALSE

				$qdata["error"] .= "S'han retornat ".$n_rows." rows pero s'han perdut els resultats en el procés.";
				$qdata["end"]    = microtime(true);
				$qdata["time"]   = $qdata["end"] - $qdata["start"];
				$easyQueryData[] = $qdata;
				return false;

			}

		} else {

			// Si el número de rows retornat es igual a 0 significa que no s'ha retornat cap resultat.
			// Guardem un missatge d'avís i el temps de finalització i retornem un array buit
			// Es retorna un array buit i no FALSE perque no es un error com a tal
			// Ja no es retorna NULL com es feia abans per a que no interfereixi amb iteracions

			$qdata["error"] .= "No s'ha retornat cap resultat.";
			$qdata["end"]    = microtime(true);
			$qdata["time"]   = $qdata["end"] - $qdata["start"];
			$easyQueryData[] = $qdata;
			return [];

		}

		// La funció mai hauria d'arribar a aquest punt perque totes les casualistiques acaben en return
		// Tot i així, es guarda un missatge d'error per si acàs

		if ($qdata["error"] == "") $qdata["error"] = "La funció s'ha executat fins al final. Error report: " . mysqli_error($conn) . " ";

		$qdata["end"]    = microtime(true);
		$qdata["time"]   = $qdata["end"] - $qdata["start"];
		$easyQueryData[] = $qdata;

	}


	/**
	 * Funció que retorna un array amb totes les dades de la última consulta (o del número de consulta especificat)
	 *
	 * @param int $n Número de la consulta de la que es volen rebre dades.
	 *               Si es null es retornaràn dades de l'ultima consulta executada.
	 *               Si es true es retornaràn dades de totes les consultes.
	 *
	 * @param string $key Clau de l'array de dades que es vol recuperar.
	 *                    Si es null es retornaràn totes les dades de la consulta o consultes especificades.
	 *
	 * @return mixed Es retornarà un string o un array (multi o unidimensional) depenent dels paràmetres introduïts.
	 */
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

	}

	/**
	 * Funció que retorna el missatge d'error de la última consulta (o del número de consulta especificat)
	 *
	 * @param int $n Número de la consulta de la que es vol rebre l'error.
	 *               Si es null es retornarà l'error de l'ultima consulta executada.
	 *               Si es true es retornaràn els errors de totes les consultes.
	 *
	 * @return mixed Es retornarà un string o un array (multi o unidimensional) depenent dels paràmetres introduïts.
	 */
	function easyQueryError($n = null) {

		return easyQueryData($n, "error");

	}

	/**
	 * Funció que retorna un array amb el temps que va portar la última consulta (o del número de consulta especificat)
	 *
	 * @param int $n Número de la consulta de la que es vol rebre el temps d'execució.
	 *               Si es null es retornarà el temps d'execució de l'ultima consulta executada.
	 *               Si es true es retornaràn els temps d'execució de totes les consultes.
	 *
	 * @return mixed Es retornarà un string o un array (multi o unidimensional) depenent dels paràmetres introduïts.
	 */
	function easyQueryTime($n = null) {

		return easyQueryData($n, "time");

	}

}

?>