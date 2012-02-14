<?php
/**
 * @package powalib
 * @author Pierre-Frédéric Caillaud (c) 2005
 * @author xrogaan <xrogaan - gmail - com> (c) 2009
 * 
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */


# Overloading Fields
#***************************************************************************

/**
 * La classe FormBase est le coeur de la librarie de Forms.
 *
 * La plupart des fonctions de FormBase ne sont pas à utiliser directement (sauf cas spécial) ;
 * en général on utilisera seulement Process(), qui fait tout.
 *
 * Elle représente un Form complet :
 * - Nom, méthode, URL de soumission (action), méthode (GET/POST)
 * - Elle contient une liste de champs (donnée au constructeur)
 * - Un "préfixe de clé" (keyfmt) qui est ajouté aux <input name=""> des champs, pour pouvoir avoir plusieurs forms identiques sur la même page
 * - Elle appelle toutes les fonctions des champs pour l'affichage, le submit et la validation
 * - Elle gère l'affichage, normal ou suivant un template.
 *
 *	Note :
 *		Le form ajoute toujours un champ _form_present de type hidden, valeur 1,
 *		qui sert à détecter si le form a été submit (clic sur le bouton submit).
 *		C'est plus sûr que de tester la présence des variables de champ dans le $_POST.
 *
 * Voir autour de Process() pour les variantes.
 *
 * @class FormBase
 */
class FormBase
{
	private $_form_name = "powaform";
	private $_action = "";
	private $_form_tag;
	private $_keyfmt;
	private $_is_validated = false;
	private $_field_keys = array();
	
	/**
	 * Cette variable sert de lien vers Smarty.
	 * Elle est définie par setSmartyInstance.
	 */
	private $_smarty = null;
	
	/**
	 * La liste de champs peut contenir des champs, ou des chaînes, qui sont en
	 * quelque sorte des titres de chapitres.    Ici, on détecte les chapitres et on
	 * les groupe pour l'affichage.
	 */
	private $_chapter = array();

	/**
	 * Generic constructor.
	 * @param $name nom du form (<form name="...">)
	 * @param $action URL de soumission du form (<form action="...">)
	 * @param $fields objets Fields indexé numériquement
	 * @param keyfmt préfixe à utiliser, sous forme printf (ex: 'myform_%s' => 'myform_myfield')
	 */
	function __construct( $name, $action, $keyfmt=false, $fields=array()  )
	{
		is_array($fields) or
			$fields = array();
		
		empty($name) or
			$this->_form_name = $name;

		$this->_action = $action ? $action : $_SERVER['REQUEST_URI'];
		$this->_keyfmt = $keyfmt ? $keyfmt : $name.'_%s';

		// add a field telling that this form was submitted.
		$fields[] = new FormFieldHidden( '_form_present', true, false, "0" );
		$fpk = sprintf( $this->_keyfmt, '_form_present' );


		$current_chapter = '';

		$formtype = false;
		// group fields by display chapter
		foreach( $fields as $field ) {
			if( is_object( $field )) {
				if( $f = $field->getFormType() )
				{
					if( $formtype && $formtype !== $f )	die( "Erreur : types de form incompatibles." );
					else $formtype = $f;
				}

				$fk = $field->key;
				$field->SetKeyFormat( $this->_keyfmt );
				$field->_form_present_name = $fpk;
				$this->$fk = $field;
				$this->_field_keys[] = $fk;
				$this->_chapters[$current_chapter][] = $fk;
			}
			else {
				$current_chapter = $field;
			}
		}

		// construit le tag <form>
		$this->formtype = $formtype;
		$this->_form_tag = '<form id="'.$this->_form_name.'" method="post" '.($formtype?('enctype="'.$formtype.'" '):'').'action="'.$this->_action.'"> ';
	}

# Les fonctions les plus utiles
########################################################################

	function setSmartyInstance(Smarty $smarty) {
		$this->_smarty = &$smarty;
		return true;
	}

	/**
	 * Une fois appellé, valide et affiche le formulaire
	 *
	 * Process() fait tout :
	 *	- Si il a été submit :
	 *	     -# On traite les valeurs et on valide
	 *	     -# Si c'est bon on retourne true
	 *	     -# Si ça ne valide pas, les messages d'erreur sont mis dans les champs
	 *	     -# On affiche le form.
	 *	- Si le form n'a pas été submit, on l'affiche
	 *
	 * @param boolean $redisplay N'est pas utilisé ?
	 * @todo vérifier redisplay
	 * @todo fonction bancale. DiplayForm ne devrait pas afficher directement le formulaire mais attendre qu'on lui demande
	 */
	function Process( $redisplay = true )
	{
		if ($r = $this->SubmitForm()) {
			return $r;
		}
		
		if ($this->_smarty instanceof Smarty) {
			$this->smarty_DisplayForm();
		} else {
			$this->DisplayForm();
		}
		return false;
	}

	/**
	 * Renvoie un tableau validé.
	 *
	 * Renvoie un tableau indexé array( nom_champ => valeur ) avec les valeurs validées de tous les champs du form.
	 * @return array
	 */
	function ExtractValues( )
	{
		$r = array();
		foreach( $this->_field_keys as $key )
			if( $key != '_form_present' )
				$r[$key] = $this->$key->value;
		return $r;
	}

	/**
	 * Travaille les valeurs du formulaire pour une insertion SQL
	 *
	 * Renvoie les valeurs du Form sous une forme adaptée à db_query (noms des champs, valeurs, format avec %s)
	 *
	 * @return array
	 */
	function ExtractValuesSQL( )
	{
		$fields = array();
		$values = array();
		$percents = array();
		foreach( $this->ExtractValues() as $key => $value )
		{
			$fields[] = $key;
			$values[] = $value;
			$percents[] = '%s';
		}
		return array( $fields, $values, $percents );
	}

	/**
	 * Travaille un tableau.
	 *
	 * Prend un tableau indexé array( nom_champ => valeur ) avec les
	 * valeurs valides de tous les champs du form, et met les valeurs
	 * dans les champs (pour initialiser le form à partir d'une requête SQL par exemple).
	 *
	 * @param array $data
	*/
	function SetValues( $data )
	{
		foreach( $this->_field_keys as $key ) {
			if ( $key != '_form_present' && array_key_exists( $key, $data )) {
				$this->$key->value = $data[$key];
			}
		}
	}

	/**
	 * Vérifie si le form a été envoyé.
	 *
	 * Retourne TRUE si ce form a été submitté (ie. les données du form
	 * sont présentes dans le POST). On peut appeler cette fonction dès
	 * que le form a été créé, cela permet de
	 *
	 * @param boolean|array $data voir FormField::Submit()
	 * @todo documentation tronquée. A quoi cette fonction pourrait servir ?
	 */
	function IsSubmitted( $data = false )
	{
		$this->_form_present->Submit( $data );
		return $this->_form_present->Validate( $this ) && $this->_form_present->form_value;
	}

	/**
	 * Traitement des valeurs du POST et validation.
	 *
	 * @param boolean|array $data voir FormField::Submit()
	 */
	function SubmitForm( $data = false )
	{
		if( !$this->IsSubmitted( $data ))	// form non soumis : on vient d'arriver sur la page du form => return false
			return false;

		foreach( $this->_field_keys as $k )
			$this->$k->Submit( $data );		// demande à chaque champ de traiter les valeurs du POST

		return $this->Validate( $this );	// valide tout et met les messages d'erreur dans les champs pour affichage. Retourne true si tout valide.
	}

# Affichage
########################################################################

	/**
	 * Affiche les noms et valeurs des champs, sans éléments de formulaire.
	 *
	 * Par exemple, pour afficher les valeurs rentrées après que le form ait
	 * été validé, ou pour afficher les valeurs actuelles avant le form de modification.
	*/
	function DisplayHTML()
	{
		echo "\n<table class=\"form\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\">";
		foreach( $this->_chapters as $chapter=>$keys )
		{
			$c = 0;
			echo <<<EOF
	<tr>
		<td colspan="2">$chapter</td>
	</tr>
EOF;
			foreach( $keys as $k )
			{
				$klass = ($c ^= 1) ? 'even' : 'odd';
				$n = $this->$k->DisplayName();
				if( !$n ) continue;
				$v = $this->$k->DisplayValue();
				echo <<<EOF
	<tr class="$klass">
		<td class="name">
			$n
		</td>
		<td class="value">
			$v
		</td>
	</tr>"
EOF;
			}
		}
		echo "</table>\n";
	}

	/**
	 * Affiche le formulaire.
	 *
	 * Affiche le formulaire avec une présentation en tableau standard (colonnes: nom, <input>, texte d'aide), et avec
	 * les titres de chapitres définis dans la liste des champs.
	 * A styler avec du CSS.
	 */
	function DisplayForm( )
	{
		/**
		 * Dit que ce form est présent dans les données qu'on va récupérer au submit...
		 */
		$this->_form_present->value = 1;

		$hidden = '';
		$temp='';
		$temp.= $this->_form_tag;
		$temp.= "\n<table class=\"form\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\">";
		foreach( $this->_chapters as $chapter=>$keys )
		{
			$c = 0;
			$temp.= <<<EOF
	<tr>
		<td colspan="3" class="chapter">
			$chapter
		</td>
	</tr>
	<tr class="separator">
		<td colspan="3"></td>
	</tr>
EOF;
			foreach( $keys as $k )
			{
				$klass = ($c ^= 1) ? 'even' : 'odd';
				$n = $this->$k->FormName();			// Nom à afficher
				if ($n) {
					$v = $this->$k->FormInput();	// élément <input> ou variantes selon le type de champ
					$h = $this->$k->FormHelp();		// Aide
					$e = $this->$k->FormError();	// Texte d'erreur, est vide si tout va bien
					$temp.= <<<EOF
	<tr class="$klass">
		<td class="name">
			$n
		</td>
		<td class="form">
			$v
		</td>
		<td class="help">$h $e</td>
	</tr>
	<tr class="separator">
		<td colspan="3"></td>
	</tr>
EOF;
				} else {
					$hidden .= $this->$k->FormInput();
				}
			}
		}
		$temp.= <<<EOF
	<tr>
		<th colspan="3">
			<div style="padding: 5px;">
				$hidden
				<input type="submit" value="Valider" />
			</div>
		</th>
	</tr>
</table>
</form>
EOF;

		echo $temp;
	}

	/**
	 * Affiche le formulaire, avec une présentation donnée par $template.
	 *
	 * C'est un moyen simple de contrôler les lignes et les colonnes de la table.
	 * $template est un tableau à 2 dimensions, qui liste les champs à mettre
	 * dans chaque cellule du tableau.
	*/
	function DisplayFormQuickTemplate( $template )
	{
		// dit que ce form est présent dans les données qu'on va récupérer au submit...
		$this->_form_present->value = 1;
		$done = array();

		$hidden = '';
		echo $this->_form_tag;
		echo "<table class=\"form\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\">";
		$c = 0;
		foreach( $template as $keys )
		{
			$klass = ($c ^= 1) ? 'even' : 'odd';

			$k = $keys[0];
			if( !$k ) puke_traceback();
			$n = $this->$k->FormName();
			echo "<tr class=\"$klass\"><td class=\"name\">$n</td><td class=\"form\"><table border=\"0\"><tr>";
			foreach( $keys as $k )
			{
				$done[$k] = true;
				echo '<td>'.$this->$k->FormInput().'</td>';
			}
			echo '</tr></table></td><td class=\"help\">';
			foreach( $keys as $k )
				if( $h = $this->$k->FormHelp() )
				{
					echo "<div>$h</div>";
					break;
				}
			foreach( $keys as $k )
				echo '<div>'.$this->$k->FormError().'</div>';
			echo '</td></tr>';
		}
		foreach( $this->_chapters as $chapter=>$keys )
		{
			$c = 0;
			foreach( $keys as $k )
			{
				if( array_key_exists( $k, $done )) continue;

				$klass = ($c ^= 1) ? 'even' : 'odd';
				$n = $this->$k->FormName();
				if( $n )
				{
					$v = $this->$k->FormInput();
					$h = $this->$k->FormHelp();
					$e = $this->$k->FormError();
					echo "<tr class=\"$klass\"><td class=\"name\">$n</td><td class=\"form\">$v</td><td class=\"help\">$h $e</td></tr>";
					echo "<tr class=\"separator\"><td colspan=\"3\"></td></tr>";
				}
				else
					$hidden .= $this->$k->FormInput();
			}
		}
		echo "<tr><th colspan=\"3\"><div style=\"padding: 5px;\">$hidden<input type=\"submit\" value=\"Valider\" />&nbsp;</div></th></tr></table>";
		echo "</form>";
	}

	/**
	 * Fonction permettant l'affichage le plus souple possible.
	 *
	 * Le HTML de chaque élément de form est généré et placé dans un tableau qui est retourné.
	 * Celui qui appelle la fonction peut ensuite afficher tous ces morceaux de HTML comme il veut.
	 * @return Un tableau contenant le nécessaire pour générer un formulaire via un moteur de template externe
	 * @retval array '_formtag', '_formtitle', 'nomDuChamp'
	 * @retval _formtag => '<form ...>' + tous les champs hidden,
	 * @retval _formtitle => Titre du dernier chapitre du form,
	 * @retval 'nomDuchamp' array => 'name, 'input', 'help, 'error'
	 * @retval 'name' => Nom à afficher,
	 * @retval 'input'	=> Elément de form HTML,
	 * @retval 'help'	=> Texte d'aide,
	 * @retval 'error'	=> Message d'erreur
	 */
	function RenderFormTemplate()
	{
		// dit que ce form est présent dans les données qu'on va récupérer au submit...
		$this->_form_present->value = 1;

		$hidden = '';
		$data = array(	'_formtag'	=> $this->_form_tag );

		foreach( $this->_chapters as $chapter=>$keys )
		{
			$data['_formtitle'] = $chapter;
			foreach( $keys as $k )
			{
				$n = $this->$k->FormName();
				if( $n )
					$data[$k] = array( 'name'=>$n, 'input'=>$this->$k->FormInput(), 'help'=>$this->$k->FormHelp(), 'error'=>$this->$k->FormError() );
				else
					$hidden .= $this->$k->FormInput();
			}
		}
		$data['_formtag'] .= $hidden;
		return $data;
	}

	/**
	 * Construit un tableau avec toute les données des champs a donner a un plugin smarty.
	 */
	function smarty_DisplayForm ()
	{
		if ( ! ($this->_smarty instanceof Smarty) ) {
			throw new Exception('Unable to process : need a Smarty object.');
		}
		
		// dit que ce form est présent dans les données qu'on va récupérer au submit...
		$this->_form_present->value = 1;

		$ar['formtag'] = $this->_form_tag;
		$hidden='';
		foreach( $this->_chapters as $chapter=>$keys )
		{
			$c = 0;
			$ar['item'][]=array('chapter'=>$chapter);
			foreach( $keys as $k )
			{
				$klass = ($c ^= 1) ? 'even' : 'odd';
				$n = $this->$k->FormName();			// Nom à afficher
				if( $n )
				{
					$ar['item'][]=array(
						'name'      =>  $n,
						'input'     =>  $this->$k->FormInput(),
						'help'      =>  $this->$k->FormHelp(),
                        'error'     =>  $this->$k->FormError(),
                        'form_key'  =>  $this->$k->form_key;
					);
				}
				else
					$hidden.= $this->$k->FormInput();
			}
		}
		$ar['formtag'].=$hidden;

//		global $smarty;
		static $numFroms;
		if (!isset($numFroms))
			$numFroms = 0;
		else
			$numFroms++;

		$this->_smarty->assign('formBase_'.$this->_form_name,$ar);
		return null;
	}


# Autres
########################################################################

	/**
	 * Valide tous les champs du form, renvoie true si réussite, sinon false.
	 *
	 * Est appelée par SubmitForm(), donc normalement peu de raisons de l'utiliser directement.
	 * @return boolean
	 */
	function Validate()
	{
		$this->_is_validated = false;

		$r = true;

		// valide une premiére fois
		foreach( $this->_field_keys as $k )
			$this->$k->error = array();

		foreach( $this->_field_keys as $k )
			if( !$this->$k->Validate( $this ))
				$r =  false;
/*
		// revalide toutte
		foreach( $this->_field_keys as $k )
			$this->$k->error = array();
		$r = true;
		foreach( $this->_field_keys as $k )
			if( !$this->$k->Validate() )
				$r =  false;
*/
//		var_dump( $r );

        if ($this->_form_present->form_value != 1) {
            return false;
        }

		$this->_is_validated = $r;
		return $r;
	}

	/**
	 * Pendant de SubmitForm(), mais qui stocke les résultats du formulaire dans la session (si ils sont validès)
	 * et qui prend les valeurs depuis la session, si le form n'a pas été soumis.
	 *
	 * Pour faire un formulaire qui garde toujours ses valeurs même sans être submit à chaque page,
	 * par exemple, formulaire de recherche ou de filtrage dans une liste d'objets.<br>
	 * Les valeurs sont stockées selon le nom du form (on peut donc utiliser autant de form qu'on veut pourvu
	 * qu'ils aient des noms différents).
	 *
	 * @return string
	 */
	function SubmitFormUsingSession( )
	{
		if( $this->IsSubmitted( ))
		{
			foreach( $this->_field_keys as $k )
				$this->$k->Submit( );

			if( $this->Validate( ) )
			{
				// Soumission et validation OK : enregistre les valeurs dans la session
				$_SESSION['powaform'][$this->_form_name] = $this->ExtractValues();

				// retourne 'post' pour dire que les valeurs viennent de l'utilisateur
				return 'post';
			}
		}
		// oublier les informations stockées dans la session
		elseif( isset($_GET['remove_session_form']) && $_GET['remove_session_form'] === $this->_form_name )
			$_SESSION['powaform'][$this->_form_name] = null;
		else
		{
			// pas d'infos dans le POST, le form n'est pas submitté, on regarde dans la session
			if( isset($_SESSION['powaform'][$this->_form_name]))
			{
				// use values from session
				$this->SetValues( $_SESSION['powaform'][$this->_form_name] );

				// retourne 'post' pour dire que les valeurs viennent de la session
				return 'session';
			}
		}
	}
}

/**
 * Petits outils pour faire vite fait des trucs de Form, rien à voir avec la classe ci-dessus.
 *
 * @class Forms
 */
class Forms
{
	/**
	 * Génère un sélecteur à partir d'un tableau clé=>valeur.
	 */
	function Select( $attributes, $selected, $options )
	{
		echo "<select $attributes>";
		foreach( $options as $id => $title )
			echo "<option value=\"$id\"".($selected==$id ? 'selected="selected"':'').">$title</option>";
		echo "</select>";
	}

	/**
	 * Génère un sélecteur à partir d'un tableau clé=>array( id, text ).
	 */
	function Select2( $attributes, $selected, $options )
	{
		echo "<select $attributes>";
		foreach( $options as $line )
			if( $line['raw'] )
				echo $line['raw'];
			else
				echo '<option value="'.$line['id'].'"'.($selected==$line['id'] ? ' selected="selected"':'').'>'.$line['text'].'</option>';
		echo "</select>";
	}

	/**
	 * Renvoie un entier depuis les arguments _GET ou null si absent.
	 * @return int
	 */
	function GetIntArg( $name )
	{
		if( is_null( $v = $_GET[$name] ) ) return null;
		return intval( $v );
	}
}

