<?php
/**
 * @package powalib
 * @author Pierre-Frédéric Caillaud (c) 2005
 * @author xrogaan <xrogaan@gmail.com> (c) 2009
 * 
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */

/**
 * Ne fait rien
 *
 * @todo doit etre remplacé.
 */
function l10n ($text) {
	return $text;
}

/**
 * @defgroup fields FormFields
 * La classe FormField, et toutes celles qui en dérivent, représentent des champs de forms.
 *
 * Les FormField's sont gérés par la classe FormBase du fichier correspondant.
 * PAR DEFAUT ON TRAVAILLE TOUJOURS EN POST et pas en GET.
 *
 * Le rôle d'un FormField est :
 * - Afficher le champ de form HTML correspondant, avec :
 *  - Nom et Aide
 *  - Message d'erreur éventuel
 * - Lire les valeurs entrées par l'utilisateur dans le champ (GET ou POST) et :
 *  - Vérifier que les champs obligatoires sont bien remplis
 *  - Eventuellement, utiliser une valeur par défaut
 *  - Appliquer un validateur sur les valeurs
 *  - En fonction du résultat de validation, autoriser ou non la validation du Form
 */
 
/**
 * La classe FormField, et toutes celles qui en dérivent, représentent des champs de forms.
 *
 * @class FormField
 * @ingroup fields
 */
class FormField {

	const DEFAULT_VALUE = '';

	/**
	 * Nom affiché
	 * exemple : "Nom d'utilisateur"
	 */
	public $name;

	/**
	 * clé (exemple: 'login')
	 */
	public $key;

	/**
	 * clé complête. L'objet Form appellera SetKeyFormat() sur ce Field<br>
	 * ce qui ajoute un préfixe à form_key (par exemple, edit_22_login au lieu de 'login')<br>
	 * ceci permet d'avoir plusieurs fois le même champ ou form sur la même page en changeant le préfixe.
	 */
	public $form_key;

	/**
	 * validateur à utiliser (new FormValidatorNimp() )
	 */
	public $validator;

	/**
	 * @defgroup varFormValue Variables jouant avec les contenus de formulaire
	 * On utilise deux variables différentes, $form_value et $value,
	 * car une valeur qui ne passe pas la validation a value à null, mais
	 * form_value reste inchangé, pour réafficher ce que l'utilisateur
	 * avait tapé pour qu'il puisse le corriger.
	 */
	
	/**
	 * Valeur fournie lors de la Création du champ, pour afficher d'origine
	 * un truc dedans (form de modification) ou valeur brute extraite du
	 * GET/POST via la fonction Submit().
	 * @ingroup varFormValue
	 */
	public $form_value;

	/**
	 * $value sera la valeur extraite de $form_value et validée, via la fonction Validate()
	 * @ingroup varFormValue
	 */
	public $value = null;
	
	/**
	 * erreurs de validation
	 */
	public $error = array();

	/**
	 * texte d'aide à afficher
	 */
	public $help;

	/**
	 * true si le champ est obligatoire
	 */
	public $required;

	/**
	 * valeur par défaut
	 */
	public $default_value;


	protected $display_mode = array(
		'options' => '',
		'mode' => ''
	);
	
	/**
	 * Generic constructor.
	 *
	 * @param $key (string)
	 * @param $required (boolean)
	 * @param $name (string) Used for label
	 * @param $value (string) Value of input
	 * @param $help (string) Text to help
	 */
	function __construct( $key, $required, $validator, $name, $value, $help )
	{
		$this->name				= $name;
		$this->key				= $key;
		$this->form_key			= $key;
		$this->validator		= $validator;
		$this->value			= $value;

		$this->error			= array();
		$this->help				= $help;
		$this->required	 		= $required;
		$this->default_value	= $this->getDefaultDefault();
	}

	/**
	 * Renvoie la valeur par défaut par défaut (par exemple, null, '', 0, un tableau vide, suivant le type de champ)
	 */
	function getDefaultDefault() {
		return null;
	}

	/**
	 * Ajoute un préfixe à la clé de ce champ
	 */
	function SetKeyFormat( $key_fmt )	{
		$this->form_key = sprintf( $key_fmt, $this->key );
	}

	/**
	 * Donne une valeur par défaut
	 */
	function SetDefaultValue( $value )	{
		$this->default_value = $value;
	}

	/**
	 * Renvoie le content-type de form à utiliser, par exemple multipart/form-data l'objet Form demande à tous
	 * les champs s'ils souhaitent un type spécial via cette fonction.
	 */
	function getFormType() {
		return false;
	}

	/**
	 * Appelée par le Form, cette fonction contrôle la soumission du champ (Submit), càd l'extraction des valeurs
	 * GET/POST pour les mettre dans $this->form_value.
	 * 
	 * $from peut être un array(), qui sera alors utilisé comme source de données au lieu du GET/POST.
	 *
	 * @param $from si mis a false, il sera remplacé par les données envoyées par l'utilisateur
	 */
	function Submit( $from = false )
	{
		$this->error = array();
		$this->form_value = null;

		// On peut spécifier une source de données spéciale via $this->getDataSource(), par exemple $_FILES pour l'upload
		if ($from === false) {
			if (method_exists($this,'getDataSource')) {
				$from = $this->getDataSource();
			} else {
				$from = $_POST;
			}
		}

		if (array_key_exists($this->form_key, $from)) {
			// Si la clé de ce champ est présente dans les données à traiter, on la prend ; puis on supprime les \ nocifs introduits par Magic Quotes
			$val = $from[$this->form_key];
			
			if ( is_array( $val )) {
				$this->form_value = array_map( 'pf_stripslashes', $val );
			} else {
				$this->form_value = pf_stripslashes( $val );
			}
		}
		//if( DEBUG>1 ) { echo $this->form_key.".Submit() value='"; var_dump( $this->form_value ); echo "'"; }
	}

	/**
	 * Applique le validateur sur la valeur du champ, check les erreurs, renvoie true si OK ou false si la validation échoue et le form ne peut pas être submitté.
	 *
	 * @return boolean
	 */
	function Validate( &$form )
	{
		$v = $this->form_value;	// valeur entrée par l'utilisateur
		$this->value = null;	// valeur validée, null pour l'instant
		$r = false;

		if( $v == '' )			// couvre aussi NULL
		{
			if( $this->required )
			{
				$this->error[] = l10n("This field is required"); // Ce champ est obligatoire.
				return false;
			}
			else
			{
				$this->value = $this->default_value;
				return true;
			}
		}

		if( !is_null( $v ) )	// on a une valeur
		{
			$v = $this->VSubmit( $v );	// est redéfinie dans toutes les classes dérivées, c'est là que ça se passe
			if( !$this->error )
			{
				$v = $this->ApplyValidators( $v, $form );
				////if( DEBUG>1 ) { echo $this->form_key.".Validate() value='"; var_dump( $v ); var_dump( $this->error ); echo "'"; }
				if( !$this->error )
				{
					$this->value = $v;	// tout OK
					return true;
				}
			}
		}

		return ! $this->error;
	}

	/**
	 * Undocumented
	 *
	 * @todo a documenter
	 */
	function InitValue( $v ) {
		$this->value = $v;
	}

	/**
	 * Nom à afficher en face du champ de form.
	 *
	 * @return string
	 */
	function DisplayName()	{
		return $this->name;
	}

	/**
	 * Convertit la valeur en HTML pour un affichage propre
	 */
	function DisplayValue()
	{
		if( is_null($this->value) ) {
			return '---';
		} else {
			return $this->VDisplayValue();	// est redéfinie dans toutes les classes dérivées, c'est là que ça se passe
		}
	}
	
	/**
	 * Retourne la pile d'erreurs.
	 * @return string
	 */
	function DisplayError()	{
		return $this->error;
	}
	
	/**
	 * Retourne le nom du champ. Ajoute un astérisk s'il est requis.
	 */
	function FormName()	{
		return $this->required ? ($this->name.' *') : $this->name;
	}
	
	/**
	 * retourne l'aide
	 * @return string
	 */
	function FormHelp() {
		return $this->help;
	}
	
	/**
	 * Formate les erreurs pour les insérer dans le formulaire
	 *
	 * @todo devrait-être laissé au moteur de template
	 */
	function FormError() {
		if ($this->error) {
			return  '<div class="powaform_error">'.implode('',$this->error)."</div>";
		}
		return '';
	}
	
	/**
	 * Undocumented
	 *
	 * @todo a documenter
	 */
	function FormInput( )
	{
		return $this->VFormInput( $this->form_key );	// est redéfinie dans toutes les classes dérivées, c'est là que ça se passe
	}

	/**
	 * Undocumented
	 *
	 * @todo a documenter
	 */
	function ApplyValidators( $value, &$form )
	{
		if( $this->validator )
			return $this->validator->Validate( $value, $this->error, $this, $form );
		else
			return $value;
	}

	/**
	 * donne soit la valeur validée, soit la valeur déjà submit pour la réafficher
	 */
	function GetValueForForm()
	{
		return htmlentities((!is_null($this->value))?$this->value:$this->form_value);
	}

	/**
		display_mode est utilisé par plusieurs classes dérivées, par exemple pour contrôler la taille d'un textarea
		c'est un tableau indexé
		<pre>
		Clé			Valeur
		--------		-------------
		mode		'textarea' transforme un <input> en <textarea>
		options		texte à coller dans le tag <textarea> ou <input>, par exemple 'rows=10 cols=60' ou 'class=truc' pour la CSS
		</pre>
	*/
	function setDisplayMode( $mode )
	{
		$this->display_mode = $mode;
	}

/*****************		Fonctions a surdéfinir		***************************/

// on est dans une classe de base donc là c'est vraiment la base

	/**
	 * Retourne la véritable 'valeur' a passer au formulaire.
	 * Le retour est différent selon la classe.
	 */
	function VSubmit( $value )
	{
		return $value;
	}

	function VFormInput( $key )
	{
		$value = $this->GetValueForForm();
		$options = is_array($this->display_mode) ? $this->display_mode['options'] : '';

		if ( isset($this->display_mode['mode']) && $this->display_mode['mode'] === 'textarea' )
			return "<textarea $options name=\"$key\">$value</textarea>";
		else
			return "<input type=\"text\" name=\"$key\" value=\"$value\" $options />";
	}

	function VDisplayValue()
	{
		return htmlentities( $this->value );
	}
}

/* **************************	Overloading Fields		*************************************************************************** */

/**
 * Champ texte.
 *
 * @ingroup fields
 * @class FormFieldString
 */
class FormFieldString extends FormField
{
	function getDefaultDefault()		{	return ''; }
}


/**
 * Pareil que FormFieldString.
 *
 * C'est à l'utilisateur de mettre un SetDisplayMode( array( 'mode'=>'textarea', 'options'=>'rows=.. cols=...' ));
 *
 * @ingroup fields
 * @class FormFieldTextArea
 */
class FormFieldTextArea extends FormFieldString
{
	function __construct( $key, $required, $validator, $name, $value, $help, $options=null )
	{
		parent::__construct( $key, $required, $validator, $name, $value, $help );
		$this->SetDisplayMode( array(
			'mode' => 'textarea',
			'options'=>$options
		));
	}
}

/**
 * Champ password simple
 *
 * @ingroup fields
 * @class FormFieldPassword
 */
class FormFieldPassword extends FormFieldString
{
	function VFormInput( $key )
	{
		$value = $this->GetValueForForm();
		$options = is_array($this->display_mode) ? $this->display_mode['options'] : '';
		return "<input type=\"password\" name=\"$key\" value=\"$value\" $options />";
	}
}

/**
 * case à cocher => boolean
 *
 * A utiliser pour avoir un "oui" ou un "non".
 *
 * Exemple : Acceptez vous la charte ? [ ]
 *
 * @ingroup fields
 * @class FormFieldBoolean
 */
class FormFieldBoolean extends FormFieldString
{
	/**
	 * @return false
	 */
	function getDefaultDefault()
	{
		return false;
	}
	
	/**
	 * Retourne la version html du champ
	 * @return string
	 */
	function VFormInput( $key )
	{
		$value = $this->GetValueForForm();
		$options = is_array($this->display_mode) ? $this->display_mode['options'] : '';
		$checked = $value ? 'checked="checked"': '';
		return "<input type=\"checkbox\" name=\"$key\" value=\"1\" $options $checked />";
	}
	
	/**
	 * 
	 * @return boolean
	 */
	function VSubmit( $value )
	{
		return $value ? true : false;
	}
}

/**
 * Champ de type hidden
 *
 * @ingroup fields
 * @class FormFieldHidden
 */
class FormFieldHidden extends FormField
{
	function __construct( $key, $required, $validator, $value ) {
		parent::__construct( $key, $required, $validator, '', $value, '' );
	}
	
	function DisplayName()	{
		return false;
	}
	
	function FormName() {
		return false;
	}
	
	function VFormInput( $key ) {
		$value = $this->GetValueForForm();
		return "<input type=\"hidden\" name=\"$key\" value=\"$value\" />";
	}
}

/**
 * Champ Select
 *
 * Champ permettant de sélectionner une option dans une liste (select, select multiple, radio, etc) domain est
 * un array( clé=>nom ), les clés étant les vraies valeurs dans $this->value, et les noms étant ce qui est affiché dans le select.
 *
 * FormFieldDomain affiche un sélecteur HTML standard, voir ci-dessous pour les variantes (Radio Buttons, Checkboxes, etc).
 *
 * $this->value est TOUJOURS la clé (par exemple 1) et JAMAIS le texte affiché !
 * Le tableau peut contenir une clé égale é 0.
 * Si le champ est facultatif, et que l'utilisateur ne choisit aucune valeur, $this->value vaut null, ce qui est facile à confondre avec 0 attention.
 *
 * Exemple :
 * @code
 * new FormFieldDomain(
 * 	'stupidity',	// $key
 * 	true		// $required
 * 	false,		// $validator
 * 	'Etes-vous',	// $name,
 *	1,			// $value,	// valeur par défaut: 1 ; mettre null pour avoir "Choisissez..." à la place ou aucun bouton/checbox actif
 *	"Texte d'aide",	// $help
 *	array( 1=>'Débile', 2='Juste boulet', 3=> 'Normal' )		// $domain
 * )
 * @endcode
 *
 * @ingroup fields
 * @class FormFieldDomain
 */
class FormFieldDomain extends FormField {
	public $domain;
	
	function __construct( $key, $required, $validator, $name, $value, $help, $domain ) {
		parent::__construct( $key, $required, $validator, $name, $value, $help );
		$this->domain = $domain;
	}

	function VSubmit( $value ) {
		if ( $value === '' ) {
			return $value;		// == '' vaut true si $value === false
		}
		
		if (array_key_exists( $value, $this->domain )) {
			return $value;
		}
		
		$this->error[] = l10n("Choose a value").'.';
		return '';
	}

	function VFormInput( $key ) {
		$value = $this->GetValueForForm();
		if( $value === '' ) $value = null;
		$options = is_array($this->display_mode) ? $this->display_mode['options'] : '';
		$f = '<select name="' . $key . '" '.$options.'><option value="">' . ucfirst(l10n('choose')) . "...</option>\n";
		foreach ($this->domain as $key=>$name) {
			$s = ($value === strval( $key )) ? ' selected' : '';
			$f.= "	<option value=\"$key\"$s>$name</option>\n";
		}
		$f.= "</select>";
		return $f;
	}

	function VDisplayValue()
	{
		if( array_key_exists( $this->value, $this->domain ))
			return htmlentities( $this->domain[$this->value] );
		else
			return '---';
	}
}

/**
 * Boutons radio
 *
 * Dérivation de FormFieldDomain pour afficher des Radio Buttons.
 *
 * @ingroup fields
 * @class FormFieldRadio
 */
class FormFieldRadio extends FormFieldDomain
{
	function VFormInput( $key )
	{
		$value = $this->GetValueForForm();
		$f = array();
		foreach( $this->domain as $vkey=>$name )
		{
			$s = ($value == $vkey) ? ' checked="checked"' : '';
			$f[] = "<input type=\"radio\" class=radio name=\"$key\" value=\"$vkey\" $s />$name";
		}

		if( is_array($this->display_mode) && $this->display_mode['mode'] === 'array' )
			return $f;

		return implode( "\n", $f );
	}
}

/**
 * Checkboxes
 *
 * Dérivation de FormFieldDomain pour afficher des Checkboxes à sélection multiple.
 *
 * @ingroup fields
 * @class FormFieldMultiple
 */
class FormFieldMultiple extends FormFieldDomain
{
	function VSubmit( $value )
	{
		if( !is_array( $value ) ) return array();

		$value = array_map( 'intval', array_values( $value ) );
		foreach( $value as $id )
		{
			if( !array_key_exists( intval($id), $this->domain ))
			{
				$this->error[] = l10n("Choose a value").'.'; //"Choisissez une valeur.";
				return '';
			}
		}
		return $value;
	}

	function GetValueForForm()
	{
		if( is_array($this->value) )		return $this->value;
		if( is_array($this->form_value) )	return $this->form_value;
		return array();
	}

	function VFormInput( $key )
	{
		$divid = 'form_checkboxes_'.$key;
		$header = "<div class=form_checkboxes id=\"$divid\"><a name=\"$divid\" />";
		$footer = "<a class=\"clickable\" onclick=\"CheckBoxMultiSelect('$divid',true);\">Tous</a> &nbsp; <a class=\"clickable\"  onclick=\"CheckBoxMultiSelect('$divid',false);\">Aucun</a></div>";

		$value = $this->GetValueForForm();

		$r = array();
		foreach( $this->domain as $id => $name )
		{
			$s = in_array($id, $value) ? ' checked="checked"' : '';
			$r[] = "<input type=\"checkbox\" name=\"${key}[]\" value=\"$id\"$s />$name<br />";
		}

		list( $cols, $width ) = calc_table_columns( sizeof( $r ), 4, 8, true );

		if( $cols > 1 )
		{
			$table = make_table( $r, $cols );
			$r = array( '<table width="100%"><tr>' );
			foreach( $table as $row )
			{
				$r[]= '<tr>';
				foreach( $row as $elem )
					$r[] = "<td width=\"$width\">$elem</td>";
				$r[] = '</tr>';
			}
			$r[] = "</table>";
		}
		return $header.implode($r).$footer;
	}

	function VDisplayValue()
	{
		$r = array();
		$value = $this->GetValueForForm();
		if( !$value || !is_array( $value ))
			return '---';

		foreach( $value as $id )
			if( !is_null( $elem = $this->domain[$id] ))
				$r[] = htmlentities( $elem );
		return implode( ', ', $r );
	}
}

/**
 * Sélecteur préfabriqué avec Oui et Non.
 *
 * Utile quand on veut que l'utilisateur choisisse obligatoirement une réponse, alors qu'une checkbox
 * non cochée signifie peut-être simplement que l'utilisateur l'a oubliée.
 *
 * @ingroup fields
 * @class FormFieldYesNo
 */
class FormFieldYesNo extends FormFieldDomain
{
	function __construct( $key, $required, $validator, $name, $value, $help )
	{
		parent::__construct( $key, $required, $validator, $name, $value, $help, array( 'O'=>l10n('yes'), 'N'=>l10n('no') ));
	}

}

/**
 * Champ date.
 *
 * Champ date demandant d'entrer une date au format MM/AA ou JJ/MM/AAAA si need_days=true.
 * Création automatique du validateur.  La valeur est renvoyée en texte.
 *
 * @ingroup fields
 * @class FormFieldDate
 */
class FormFieldDate extends FormField
{
	function __construct( $key, $required, $validator, $name, $value, $help, $need_days )
	{
		if( $help ) $help .= '';
		$help .= $need_days ? "JJ/MM/AAAA" : "MM/AAAA";
		if( !$validator )
			$validator = new FormValidatorDate( '01/01/1890', '01/01/2025' );
		parent::__construct(  $key, $required, $validator, $name, $value, $help );
		$this->need_days = $need_days;
		$this->setDisplayMode( array( 'options'=> 'size=10' ));
	}
	function InitValue( $v )
	{
		$this->value = strftime( '%d/%m/%Y' );
	}
}

/**
 * Champ date,
 *
 * Champ date demandant d'entrer une date au format MM/AA ou JJ/MM/AAAA si need_days=true.
 * Création automatique du validateur. La valeur est renvoyée en texte.
 * Petit bouton à côté du form avec calendrier popup en javascript.
 *
 * @ingroup fields
 * @class FormFieldJSDate
 */
class FormFieldJSDate extends FormField
{
	function __construct( $key, $required, $validator, $name, $value, $help )
	{
		if( !$validator )	$validator = new FormValidatorDate( '01/01/1890', '01/01/2025' );
		parent::__construct(  $key, $required, $validator, $name, $value, $help );
		$this->need_days = true;
	}
	function InitValue( $v )
	{
		$this->value = strftime( '%d/%m/%Y' );
	}
	function VFormInput( $key )
	{
		$value = $this->GetValueForForm();
		if( $value ) $value = ",'$value'";
		return "<script language=\"javascript\">DateInput('$key', true, 'DD/MM/YYYY' $value)</script>";
	}
}

/**
 * Champ Heure,
 * Champ Heure demandant d'entrer une heure au format HH:MM.
 * Création automatique du validateur. La valeur est renvoyée en texte.
 *
 * @ingroup fields
 * @class FormFieldTime
 */
class FormFieldTime extends FormField
{
	function __construct( $key, $required, $validator, $name, $value, $help )
	{
		if( !$validator )	$validator = new FormValidatorTime();
		if( $help ) $help .= '';
		$help .= "HH:MM";
		parent::__construct(  $key, $required, $validator, $name, $value, $help );
		$this->display_mode = array( 'options' => 'size=7' );
	}
}

/**
 * Champ pour envoyer un fichier.
 *
 * $this->value est l'entrée de $_FILES renvoyée par PHP, qui contient entre autres le nom du fichier, le content-type, etc.
 * Utiliser un FormValidatorUpload
 *
 * @ingroup fields
 * @class FormFieldUpload
 * @see FormValidatorUpload()
 */
class FormFieldUpload extends FormField
{
	function VSubmit( $value )
	{
		if (is_uploaded_file($value['tmp_name'])) {
			return $value;
		} else {
			switch ($value['error']) {
				case UPLOAD_ERR_OK:
					return $value;
				case UPLOAD_ERR_INI_SIZE:
					$this->error[] = "Le fichier téléchargé excède la taille de _upload_max_filesize_, configurée dans le php.ini.";
					return '';
				case UPLOAD_ERR_FORM_SIZE:
					$this->error[] = "Le fichier téléchargé excède la taille de *MAX_FILE_SIZE*, qui a été spécifiée dans le formulaire HTML.";
					return '';
				case UPLOAD_ERR_PARTIAL:
					$this->error[] = "Le fichier n'a été que partiellement téléchargé.";
					return '';
				case UPLOAD_ERR_NO_FILE:
					if ( $this->required ) {
						$this->error[] = "Aucun fichier n'a été téléchargé.";
					}
					return '';
				case UPLOAD_ERR_NO_TMP_DIR: // Introduit en PHP 4.3.10 et PHP 5.0.3.
					$this->error[] = "Un dossier temporaire est manquant.";
					return '';
				case UPLOAD_ERR_CANT_WRITE: // Introduit en PHP 5.1.0.
					$this->error[] = "Échec de l'écriture du fichier sur le disque.";
					return '';
				case UPLOAD_ERR_EXTENSION: // Introduit en PHP 5.2.0.
					$this->error[] = "L'envoi de fichier est arrêté par l'extension.";
					return '';
				default:
					$this->error[] = "Unknow error : " . $value['error'];
					return '';
			}
		}
/*		if( $value['tmp_name'] ) return $value;		// == '' vaut true si $value === false
		if( $this->required )
			$this->error[] = l10n("You must send a file").'.'; //"Vous devez envoyer un fichier.";
		return '';*/
	}

	function getDataSource()	{	return $_FILES; }
	function getFormType()		{	return 'multipart/form-data'; }

	function VFormInput( $key )
	{
		$hidden = '<input type="hidden" name="MAX_FILE_SIZE" value="'. $this->validator->max_size . '" />';
		return $hidden . '<input type="file" name="'.$key.'" />';
	}

	function VDisplayValue()
	{
		return '---';
	}
}
