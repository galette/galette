<?
	define("GALETTE_VERSION", "v0.63");

	/*
	*@author steve gricci
	*@access public
	*@skill beginner
	*@site www.deepcode.net
 	*/
 
 	function utime ()

	{
		$time = explode( " ", microtime());
		$usec = (double)$time[0];
		$sec = (double)$time[1];
		return $sec + $usec;
	}
	$start = utime();
	
	include(WEB_ROOT."/includes/adodb/adodb.inc.php");
	$DB = ADONewConnection(TYPE_DB);
	$DB->debug = false;
	if(!@$DB->Connect(HOST_DB, USER_DB, PWD_DB, NAME_DB)) die("No database connection...");

	if (!defined("PREFIX_DB"))
	   define("PREFIX_DB","");

	// Definition du protocole
	if (isset($_SERVER["HTTPS"]))
	{
		if ($_SERVER["HTTPS"]=="on")
			define("HTTP","https");
		else
			define("HTTP","http");
	}
	else
		define("HTTP","http");

	// Chargement des preferences
	$result = $DB->Execute("SELECT * FROM ".PREFIX_DB."preferences");
	while (!$result->EOF)
	{
	   define(strtoupper($result->fields["nom_pref"]), $result->fields["val_pref"]);
	   $result->MoveNext();
	}
	$result->Close();

	function is_exempt($DB, $cotisant)
	{
		$request = "SELECT bool_exempt_adh
			      FROM ".PREFIX_DB."adherents
			     WHERE id_adh=" . $cotisant;
		return $DB->GetOne($requete_cotis);
	}

	function get_echeance ($DB, $cotisant) {
		$exempt = is_exempt($DB, $cotisant);
		
		$return_date = "";
		// définition couleur pour adherent exempt de cotisation
		if ($exempt != "1")
		{
			$requete_cotis = "SELECT count(*)
					  FROM ".PREFIX_DB."cotisations
					  WHERE id_adh=" . $cotisant;
			$count = &$DB->GetOne($requete_cotis);
			if ($count) {
				$requete_cotis = "SELECT max(date_fin_cotis)
						  FROM ".PREFIX_DB."cotisations
						  WHERE id_adh=" . $cotisant;
				$max_date = &$DB->GetOne($requete_cotis);
				if ($max_date)
				{
					list($a,$m,$j) = split("-", $max_date);
					$return_date = split("/", date("d/m/Y", mktime(0,0,0,$m,$j,$a)));
				}
			}
		}	
		return $return_date;
	}

	function get_last_auto_increment($DB, $table, $column) {
		$val_or_oid = $DB->Insert_ID();
		// When calling Insert_ID, postgres returns the OID
		$val = $DB->GetOne("SELECT $column FROM $table WHERE oid=$val_or_oid");
		return $val ? $val : $val_or_oid;
	}
?>
