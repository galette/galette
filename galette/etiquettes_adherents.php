<?php
/* etiquettes_adherents.php
 * - Generation d'un PDF d'étiquettes
 * Copyright (c) 2003 Frédéric Jaqcuot
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

	include("includes/config.inc.php");
	include(WEB_ROOT."includes/database.inc.php"); 
	include(WEB_ROOT."includes/session.inc.php");

	if ($_SESSION["logged_status"]==0)
	{
		header("location: index.php");
		die();
	}
	if ($_SESSION["admin_status"]==0)
	{
		header("location: voir_adherent.php");
		die();
	}

//	include(WEB_ROOT."includes/functions.inc.php"); 
	include_once(WEB_ROOT."includes/i18n.inc.php");
	include(WEB_ROOT."includes/phppdflib/phppdflib.class.php");
	
	$mailing_adh = array();
	if (isset($_SESSION['galette']['labels']))
	{
		while (list($key,$value)=each($_SESSION['galette']['labels']))
			$mailing_adh[]=$value;
	}
	else
		die();

		$requete = "SELECT id_adh, nom_adh, prenom_adh, adresse_adh,
									titre_adh, cp_adh, ville_adh, pays_adh, adresse2_adh
									FROM ".PREFIX_DB."adherents
			       				WHERE ";
		$where_clause = "";
		while(list($key,$value)=each($mailing_adh))
		{
			if ($where_clause!="")
				$where_clause .= " OR ";
			$where_clause .= "id_adh=".$DB->qstr($value, get_magic_quotes_gpc());
		}
		$requete .= $where_clause." ORDER by nom_adh, prenom_adh;";
		// echo $requete;
		$resultat = &$DB->Execute($requete);
		
		$pdf = new pdffile;
		$pdf->set_default('margin', 0);
		$param["height"] = PREF_ETIQ_CORPS;
		$firstpage = $pdf->new_page("a4");
		
		$param["fillcolor"] = $pdf->get_color('#000000');
		$param["align"] = "center";
		$param["width"] = 1;
		$param["color"] = $pdf->get_color('#DDDDDD');

		if ($resultat->EOF)
			die();
			
	   $yorigin=842-round(PREF_ETIQ_MARGES_V*2.835);
	   $xorigin=round(PREF_ETIQ_MARGES_H*2.835);
	   $col=1;
	   $row=1;
	   $nb_etiq=0;
	   $concatname = "";
		while (!$resultat->EOF)
		{
			$nom_adh_ext="";
			switch($resultat->fields[4])
			{
				case "1" :
					$nom_adh_ext .= _T("Mr.");
					break;
				case "2" :
					$nom_adh_ext .= _T("Mrs.");
					break;
				default :
					$nom_adh_ext .= _T("Miss.");
			}
			
			$x1 = $xorigin + ($col-1)*(round(PREF_ETIQ_HSIZE*2.835)+round(PREF_ETIQ_HSPACE*2.835));
			$x2 = $x1 + round(PREF_ETIQ_HSIZE*2.835);
			$y1 = $yorigin-($row-1)*(round(PREF_ETIQ_VSIZE*2.835)+round(PREF_ETIQ_VSPACE*2.835));
			$y2 = $y1 - round(PREF_ETIQ_VSIZE*2.835);
												
			$nom_adh_ext .= " ".strtoupper($resultat->fields[1])." ".ucfirst(strtolower($resultat->fields[2]));
			$concatname = $concatname . " - " . $nom_adh_ext;
			$param["font"] = "Helvetica-Bold";
			$pdf->draw_paragraph($y1-10, $x1, $y1-10-(round(PREF_ETIQ_VSIZE*2.835)/5)+5, $x2, $nom_adh_ext, $firstpage, $param);
			$param["font"] = "Helvetica";
			$pdf->draw_paragraph ($y1-10-(round(PREF_ETIQ_VSIZE*2.835)/5), $x1, $y1-10-(round(PREF_ETIQ_VSIZE*2.835)/5)-(round(PREF_ETIQ_VSIZE*2.835)*4/5), $x2, $resultat->fields[3]."\n".$resultat->fields[8]."\n".$resultat->fields[5]."  -  ".$resultat->fields[6]."\n".$resultat->fields[7], $firstpage, $param);
			$pdf->draw_rectangle ($y1, $x1, $y2, $x2, $firstpage, $param);
			$resultat->MoveNext();

			$col++;
			if ($col>PREF_ETIQ_COLS)
			{
				$col=1;
				$row++;
			}
			if ($row>PREF_ETIQ_ROWS)
			{
				$col=1;
				$row=1;
				$firstpage = $pdf->new_page("a4");
			}
			$nb_etiq++;
		}
		$resultat->Close();
		//dblog("Generation of "." ".$nb_etiq." "."label(s)",$concatname);
		
	header("Content-Disposition: attachment; filename=labels.pdf");
	header("Content-Type: application/pdf");
	$temp = $pdf->generate(0);
	header('Content-Length: ' . strlen($temp));
	echo $temp;
?>
