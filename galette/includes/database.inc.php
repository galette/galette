<?
	define("GALETTE_VERSION", "v0.62");

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
	
	function get_echeance ($DB, $cotisant, $exempt_default="") {
		if ($exempt_default=="")
		{
			$requete_cotis = "SELECT bool_exempt_adh
					  FROM ".PREFIX_DB."adherents
					  WHERE id_adh=" . $cotisant;
			$resultat_cotis = &$DB->Execute($requete_cotis);
			if ($resultat_cotis->EOF)
				$exempt="1";
			else
				$exempt=$resultat_cotis->fields[0];
			$resultat_cotis->Close();
		}	
		else
			$exempt=$exempt_default;
		
		// définition couleur pour adherent exempt de cotisation
		if ($exempt=="1")
			return "";
		else
		{
			$requete_cotis = "SELECT *
					  FROM ".PREFIX_DB."cotisations
					  WHERE id_adh=" . $cotisant . "
					  ORDER BY date_cotis";
			$resultat_cotis = &$DB->Execute($requete_cotis);
			$diff = 0;
			$duree_old = 0;
			$ts_old = 0;
			while (!$resultat_cotis->EOF) 
			{
				// difference avec date precedente
			
				// timestamp actuel
				list($a,$m,$j)=split("-",$resultat_cotis->fields["date_cotis"]);
				$ts = mktime(0,0,0,$m,$j,$a);
			
				// duree cotisation courante (en s)
				$duree = (mktime(0,0,0,$m+$resultat_cotis->fields["duree_mois_cotis"],$j,$a)-mktime(0,0,0,$m,$j,$a));
			
				// diff = (date_prec + duree_prec + diff) - date_courante
				$diff = ($ts_old + $duree_old + $diff)-$ts;
			
				if ($diff < 0)
				  $diff = 0;
			  
				$ts_old = $ts;
				$duree_old = $duree;
				$resultat_cotis->MoveNext();
			}
			$resultat_cotis->Close();
		
			if ($ts_old==0)
				return "";
			else
				$cumul = intval((($ts_old + $duree_old + $diff)-time())/(3600*24));
		}	

		  //
		 // Fin du calcul du temps d'adhésion
		// 
	 	 
		$return_date = date("d/m/Y",time()+$cumul*3600*24);
		return split("/",$return_date);
	}
?>
