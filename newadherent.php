<?
/* newadherent.php
 * - préambule à la saisie d'un adhérent par lui-même
 * - barrière anti-robots
 * Copyright (c) 2004 Frédéric Jaqcuot, Georges Khaznadar
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
include(WEB_ROOT."includes/functions.inc.php"); 

$mdp=makeRandomPassword(7);
if (!isset($reponse)){ //pas encore de saisie. Fabrication du mot de passe
  //nettoyage des mots de passes vieux de plus d'une minute
  $dh=@opendir("photos");
  while($file=readdir($dh)){
    if (substr($file,0,3)=="pw_" && 
	time() - filemtime("photos/".$file) > 60) {
      unlink("photos/".$file);
    }
  }
  $c=crypt($mdp);
  $fname=md5($mdp);
  // needs the package ttf-freefont
  $fontfile="/usr/share/fonts/truetype/freefont/FreeSans.ttf";
  $box= imageftbbox( 12, 0, $fontfile, $mdp, array());
  $textx= 2;
  $texty= abs($box[5]) + 2;
  $png= imagecreate($box[4] + 5, abs($box[3]) + abs($box[5]) + 4);
  $bg= imagecolorallocate($png,160,160,160);
  imagefttext( $png, 12, 0, 
	       2, abs($box[5]) + 2, 
	       imagecolorallocate($png,0,0,0), 
	       $fontfile, 
	       $mdp, array());
  imagepng($png,"photos/pw_".$fname.".png");
?>
<form method="post">
   <input type="hidden" name="c" value="<? echo $c ?>">
   Veuillez recopier ce mot de passe ci-dessous : 
  <img src="<? echo "photos/pw_".$fname.".png" ?>"><br>
   <input type="text" name="reponse">
</form>
<?
} else {
  if (crypt($reponse,$c)==$c){
    include(WEB_ROOT."includes/lang.inc.php"); 
    include("header.php");
    $uc=urlencode($c);
    echo "<h1>"._T("Vous avez réussi à recopier le mot de passe")."</h1>\n";
    echo "<table bgcolor='yellow'><tr><td>".$reponse."</td></tr></table>\n";
    echo _T(" ... vous n'êtes donc pas un robot stupide.")."<hr>\n";
    echo _T("Vous devrez mémoriser ce mot de passe, ");
    echo _T("ou alors vous le modifierez à votre convenance après avoir cliqué");
    echo "<a href='self_adherent.php?c=$uc'>"._T("ici.")."</a>\n";
    echo "</body></html>";
  }
  else {
    include("header.php");
    echo "<h1>"."mauvais mot de passe"."</h1>\n";
    echo "<a href='index.php'>"._T("retour")."</a>\n";
    echo "</body></html>";
  }
}

?>