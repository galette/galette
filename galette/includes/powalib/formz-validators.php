<?php
/**
 * @package powalib
 * @author Pierre-Frédéric Caillaud (c) 2005
 * @author xrogaan <xrogaan@gmail.com> (c) 2009
 * 
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */ 
/**
 * @defgroup validator FormValidator
 * Validateurs pour la librarie de Forms.
 *
 * -----------------------------------
 *
 * Chaque validateur est une classe, qui doit implémenter Validate().
 * Cette fonction est appelée par la fonction Validate() du champ (Field),
 * qui appelle tour à tour tous les validateurs sur ce champ.
 * Si tout se passe bien, Validate() retourne la valeur validée, convertie dans le type correct.
 * Par exemple, FormValidatorInteger::Validate() renvoie un entier. On peut donc utiliser les validateurs
 * pour forcer le type d'un argument, ce qui fait partie de leur utilisation normale.
 *
 *
 *	-----------------------------------
 *
 * Les paramétres de validation (longueur min et max, etc) sont passés au constructeur du validateur.
 *
 *	-----------------------------------
 *
 *
 * Le traitement des champs obligatoires et facultatifs est fait dans la classe FormField.
 * C'est cette classe qui donne une erreur si un champ obligatoire est absent.
 * Les champs de form ne sont validés que s'ils sont présents.
 *
 */


/**
 * pf_stripslashes enléve les \ stupides et inutiles ajoutés par Magic Quotes.
 * @param $x
 * @return $x
 */
function pf_stripslashes( $x )	{
	return get_magic_quotes_gpc() ? stripslashes($x) : $x;
}

/**
 * Formate une date en tableau
 *
 * Date en format JJ/MM/AAAA => array( jour, mois, année ) ou null si erreur.
 * Si $need_days=false et que la date donnée n'a pas de jour (ex. 02/1980) renvoie 01/02/1980
 * Exemple :
 * @code
 * echo pf_date_to_jma('01/02/1985');
 * // ==> array( 1,2,1985 )
 * @endcode
 *
 * @param string $value Date au format JJ/MM/AAAA
 * @param bool $need_days 
 * @return array
 */
function pf_date_to_jma( $value, $need_days=false ) {
	$regex = $need_days ? "^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$" : "^[0-9]{1,2}/[0-9]{4}$" ;
	if (!ereg($regex, $value )) {
		return null;
	}

	$jma = explode( '/',$value );
	$j = $m = $a = 0;
	if( sizeof( $jma ) == 3 ) {
		list( $j, $m, $a ) = $jma;
	} else {
		if( (!$need_days) && sizeof( $jma ) == 2 ) {
			$j = '01';
			list( $m, $a ) = $jma;
		}
	}
	$j = intval($j);
	$m = intval($m);
	$a = intval($a);
	
	if( !checkdate( $m, $j, $a ))
		return null;

	return array($j,$m,$a);
}

/**
 * Compare deux dates au format array( j,m,a )
 * @param array $ad Première date
 * @param array $bd Deuxième date
 */
function pf_compare_jma( $ad, $bd )
{
	for( $i=2; $i>=0; $i-- ) {
		$a = $ad[$i]; $b = $bd[$i];
		if( $a<$b )		return -1;
		if( $a>$b )		return 1;
	}
	return 0;
}

/**
 renvoie true si $text satisfait une des expressions régulières du tableau $eregs
*/
function multi_ereg_match( $eregs, $text )
{
	trigger_error('This function use deprecated methods', E_USER_NOTICE);
	
	if( !is_array( $eregs ))
		return ereg( $eregs, $text );
	else foreach( $eregs as $e )
		if( ereg( $e, $text ))
			return true;
	return false;
}

function multi_strpos($haystacks, $text) {
	if (!is_array($haystacks)) {
		return (strpos($haystacks, $text) !== false) ? true : false;
	} else {
		foreach ($haystacks as $haystack)
			if (strpos($haystack, $text) !== false)
				return true;
		return false;
	}
}

function multi_preg_match ($regexs, $text) {
	if (!is_array($regexs)) {
		return preg_match($regexs, $text);
	} else {
		foreach ($regexs as $regex)
			if (preg_match($regex, $text))
				return true;
		return false;
	}
}

/**
 * Interface pour tout les validateurs
 * @interface iFormValidator
 * @ingroup validator
 */
interface iFormValidator {
	/**
	 * @param $value Valeur à valider
	 * @param $error Référence d'une variable array() contenant les erreurs de validation.
	 *               Si une erreur de validation est détectée, le texte de l'erreur est ajouté dans ce tableau par Validate()
	 *               Une validation correcte est donc détectée par le fait que $error est vide.
	 * @param $obj Champ de form qu'on est en train de valider
	 * @param $form Form qu'on est en train de valider
	 */
	public function Validate($value, &$error, &$obj, &$form);
}

# Validateurs simples
###############################################################

/**
 * Valide une chaîne de caractères.
 *
 * @param int $min_len
 * @param int $max_len
 * @class FormValidatorString
 * @ingroup validator
 */
class FormValidatorString implements iFormValidator
{
	private $message;
	private $min_len;
	private $max_len;
	
	function __construct($min_len, $max_len) {
		$this->min_len = $min_len;
		$this->max_len = $max_len;
	}
	
	function FormValidatorString( $min_len, $max_len ) {
		trigger_error(__FUNCTION__ . 'is obsolete.', E_USER_WARNING);
		$this->min_len = $min_len;
		$this->max_len = $max_len;
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		if ($this->min_len !== false && (strlen($value) < $this->min_len)) {
			$error[] = "Vous devez entrer au moins $this->min_len caractères.";
			return null;
		}

		if( $this->max_len !== false && (strlen($value) > $this->max_len )) {
			$error[] = "Ce champ ne peut contenir plus de $this->max_len caractères.";
			return null;
		}
		return $value;
	}
}

/**
 * Valide une chaîne de caractères.
 * Idem que FormValidatorString, mais la chaîne doit satisfaire l'expression régulière fournie.
 *
 * @param int $min_len
 * @param int $max_len
 * @ingroup validator
 * @class FormValidatorRegexp
 */
class FormValidatorRegexp extends FormValidatorString
{
	private $regex;
	
	function __construct( $min_len, $max_len, $regexp, $message ) {
		parent::__construct( $min_len, $max_len );
		$this->regexp = $regexp;
		$this->message = $message;
	}

	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		$value = parent::Validate( $value, $error, $obj, $form );
		if( !$error )
		{
			if( !ereg( $this->regexp, $value ))
				$error[] = $this->message;
		}
		return $value;
	}
}

/**
 * Valide une chaîne de caractères.
 * Idem que FormValidatorString, la chaîne doit satisfaire l'expression régulière PERL fournie.
 * @param int $min_len
 * @param int $max_len
 * @ingroup validator
 * @class FormValidatorPerlRegexp
 */
class FormValidatorPerlRegexp extends FormValidatorString
{
	private $regex;
	
	function __construct( $min_len, $max_len, $regexp, $message ) {
		parent::__construct( $min_len, $max_len );
		$this->regexp = $regexp;
		$this->message = $message;
	}

	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		$value = parent::Validate( $value, $error, $obj, $form );
		if( !$error )
		{
			$r = array();
			if( !preg_match( $this->regexp, $value, $r ))
				$error[] = $this->message;
		}
		return $value;
	}
}

/**
 * Valide un entier.
 *
 * Vérifie que la valeur est bien un nombre.
 *
 * @param int $min_value
 * @param int $max_value
 * @ingroup validator
 * @class FormValidatorInteger
 */
class FormValidatorInteger implements iFormValidator
{
	private $min_value;
	private $max_value;
	private $iexist = false;
	
	function __construct( $min_value=false, $max_value=false ) {
		$this->min_value = $min_value;
		$this->max_value = $max_value;
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		if ( $value === '' )
			return null;
		
		if ( !is_numeric($value) ) {
			$error[] = "Doit être une valeur num?rique.";
			return null;
		}
		
		$value = intval($value);

		if ( $this->min_value !== false && ($value < $this->min_value )) {
			$error[] = "Doit être un nombre supérieur ou égal à $this->min_value .";
			return null;
		}
		
		if ( $this->max_value !== false && ($value > $this->max_value )) {
			$error[] = "Ce nombre ne peut être supérieur à $this->max_value .";
			return null;
		}
		
		return $value;
	}
}

/**
 * Valide un nombre flottant
 *
 * Vérifie que la valeur est bien un nombre flottant
 * @param int $min_value
 * @param int $max_value
 * @ingroup validator
 * @class FormValidatorFloat
*/
class FormValidatorFloat implements iFormValidator
{
	private $min_value;
	private $max_value;
	function __construct( $min_value, $max_value=false ) {
		$this->min_value = $min_value;
		$this->max_value = $max_value;
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		if( $value === '' )
			return null;
		
		if( !is_numeric( $value )) {
			$error[] = "Doit être une valeur numérique."; return null;
		}
		
		$value = floatval($value);

		if( $this->min_value !== false && ($value < $this->min_value )) {
			$error[] = "Doit être un nombre supérieur ou égal à $this->min_value .";
			return null;
		}
		
		if( $this->max_value !== false && ($value > $this->max_value )) {
			$error[] = "Ce nombre ne peut être supérieur à $this->max_value .";
			return null;
		}
		return $value;
	}
}

/**
 * Valide une date.
 *
 * Vérifie que la valeur est bien une date valide.
 * Regarde le membre need_days dans le champ de form pour voir quoi faire :
 * <pre>
 * need_days = false
 * 	JJ/MM/AAAA => renvoie MM/AAAA
 *	MM/AAAA => renvoie MM/AAAA
 * need_days = true
 * 	JJ/MM/AAAA => renvoie JJ/MM/AAAA
 * 	MM/AAAA => erreur
 * </pre>
 *
 * @param string $min_date
 * @param string $max_date
 * @ingroup validator
 * @class FormValidatorDate
 * @return string
 */
class FormValidatorDate implements iFormValidator
{
	function __construct( $min_date=false, $max_date=false ) {
		$this->min_date = $min_date;
		$this->max_date = $max_date;
	}

	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		$jma = pf_date_to_jma( $value, $obj->need_days );
		if( !$jma )
		{
			$error[] = "Date invalide.";
			return $value;
		}
		if( $this->min_date && ($min_date = pf_date_to_jma($this->min_date, $obj->need_days)) )
		{
			if( pf_compare_jma( $jma, $min_date ) < 0 )
			{
				$error[] = "Entrez une date plus r?cente.";
				return $value;
			}
		}
		if( $this->max_date && ($max_date = pf_date_to_jma($this->max_date, $obj->need_days)))
		{
			if( pf_compare_jma( $jma, $max_date ) > 0 )
			{
				$error[] = "Entrez une date plus ancienne.";
				return $value;
			}
		}

		if( $obj->need_days )
			return sprintf( "%02d/%02d/%04d", $jma[0], $jma[1], $jma[2] );
		else
			return sprintf( "%02d/%04d", $jma[1], $jma[2] );
	}
}

/**
 * Valide une heure (HH:MM)
 * @ingroup validator
 * @class FormValidatorTime
 */
class FormValidatorTime implements iFormValidator
{
	private $iexist;
	
	function __construct( ) {	// in this dumb language, an empty object is false
		$this->iexist = true;
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		$v = explode(' ', trim(ereg_replace( "[^0-9]+", " ", $value )));

		switch( sizeof( $v ) )
		{
			case 1:
				$v[] = '0';
			case 2:
				$hour = intval($v[0]);
				$minute = intval($v[1]);
				if( $hour < 24 && $minute < 60 )
					return sprintf( "%02d:%02d", $hour, $minute );
		}
		$error[] = "Heure invalide.";
		return $value;
	}
}

/**
 * Valide une adresse IP
 * Cherche  (X.X.X.X), X étant nombre entre 0 et 256
 * @ingroup validator
 * @class FormValidatorIPAddress
 * @return string
 */
class FormValidatorIPAddress implements iFormValidator
{
	private $iexist;
	
	function __construct ( ) {
		$this->iexist = true;
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		if( $value === '' )
			return null;
		
		if( !ereg( '^[0-9]+\\.[0-9]+\\.[0-9]+\\.[0-9]+$', $value )) {
			$error[] = "Doit être une adresse IP.";
			return null;
		}
		foreach( array_map( 'intval', explode( '.', $value )) as $i ) {
			if( $i<0 || $i>255 ) {
				$error[] = "Doit être une adresse IP valide."; return null;
			}
		}
		return $value;
	}
}

/**
 * Compagnon du FormFieldUpload
 *
 * Ce validateur dissèque le fichier uploadé pour vérifier un certain nombre de paramêtres, qui sont donnés
 * au constructeur dans 'params'. params peut être un tableau vide, mais ?a ne sert pas à grand chose.
 *
 *
 * @class FormValidatorUpload
 * @see FormFieldUpload()
 * @ingroup validator
 */
class FormValidatorUpload implements iFormValidator
{
	/**
	 * Taille minimale du fichier en octets
	 */
	public $min_size;
	
	/**
	 * Taille maximale du fichier en octets
	 */
	public $max_size;
	
	/**
	 * Nom de fichiers valide.
	 *
	 * array( expressions régulières ) ; le nom du fichier doit en satisfaire au moins une.
	 *
	 * exemple :
	 * @code array( '^.*\.jpg$' )
	 * @endcode
	 */
	public $filename;
	
	/**
	 * content_types autorisé.
	 *
	 * array( expressions régulières ) ; le content-type (mime, ex image/jpeg) du fichier doit en satisfaire au moins une.
	 * c'est le contet-type donné par le navigateur de l'utilisateur qui est pris en compte.
	 *
     * exemple :
	 * @code array( '^image/.*$' )
	 * @endcode
	 */
	public $content_types = null;
	
	/**
	 * tableau clé=>valeur permettant de valider des images
	 *
	 * si il est présent, on vérifie que le fichier est une image.
	 * Doit être remplis par __construct
	 * <pre>
	 *	Clé                      Valeur
	 *	---------------------    --------------------------------------------------------
	 *	content_types            pareil que FormValidatorUpload::$content_types ci-dessus, mais c'est le content-type détecté lors de l'analyse de l'image
	 *	width                    la largeur en pixels doit être égale à width
	 *	height                   la hauteur en pixels doit être égale à height
	 *	width_max                no comment ;)
	 *	height_max
	 *	width_min
	 *	height_min
	 * </pre>
	 */
	public $image;
	
	private $iexist; //undetermined action.
	private $funcCheck;
	
	function __construct( array $params ) {
		$this->iexist = true;
		foreach( $params as $k=>$v ) {
			if ($k == 'content_types') {
			    if (!empty($v['regex'])) {
				    $this->funcCheck = 'multi_preg_match';
				    $v = $v['regex'];
				} elseif (!empty($v['strpos'])) {
			    	$this->funcCheck = 'mutli_strpos';
			    	$v = $v['strpos'];
    			} else {
			        throw new Exception('Unknow types.');
	    		}
	    	}
			$this->$k = $v;
		}
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		if( !$value && !$obj->required ) return null;
		// Taille
		if( !is_null( $this->max_size ) && $value['size'] > $this->max_size ) {
			$error[] = "La taille du fichier est supérieure à la taille maximum autorisée qui est de {$this->max_size} octets.";
			return null;
		}
		if( !is_null( $this->min_size ) && $value['size'] < $this->min_size ) {
			$error[] = "La taille du fichier est inférieure à la taille minimum autorisée qui est de {$this->min_size} octets.";
			return null;
		}

		// Type
		if( !is_null( $this->content_types ) && !call_user_func_array($this->funcCheck, array( $this->content_types, $value['type'] ))) {
			$error[] = "Seuls les types de fichiers suivants sont autorisés : ".(is_array($this->content_types)?implode(',',$this->content_types):$this->content_types) . ". Vous avez donné : " . $value['type'];
			return null;
		}

		// Nom de fichier
		if (!is_null($this->filename))
		{
			if( is_object( $this->filename ))	// si c'est un validateur ...
			{
				$validator->Validate( $value['name'], $error, $obj, $form );
				if ( $error )
					return null;
			}
			elseif( !multi_ereg_match( $this->filename, $value['name'] )) {
				$error[] = "Le nom de fichier ne correspond pas à l'expression régulière de validation : ".(is_array($this->filename)?implode(',',$this->content_types):$this->filename);
				return null;
			}
		}

		// Propriétés images
		if( !is_null( $this->image ))
		{
			$p = $this->image;
			$img = getimagesize( $value['tmp_name'] );
			if ( !$img ) {
				$error[] = "Erreur à l'analyse du fichier image.";
				return null;
			}

			// Revalide le content-type
			$ctypes = $p['content_types'] ? $p['content_types'] : $this->content_types;
			if (!is_null( $ctypes ) && !call_user_func_array($this->funcCheck, array( $ctypes, $img['mime'] ))) {
				$error[] = "Seuls les types d'images suivants sont autorisés : " . (is_array($ctypes) ? implode(',',$ctypes) : $ctypes);
				return null;
			}

			// Taille
			if( 	(isset($p['width']) && !is_null( $p['width'] ) && $img[0] !== $p['width'])
				||	(isset($p['width']) && !is_null( $p['height'] ) && $img[1] !== $p['height'])
				||	(isset($p['width_max']) && !is_null( $p['width_max'] ) && $img[0] > $p['width_max'])
				||	(isset($p['height_max']) &&!is_null( $p['height_max'] ) && $img[1] > $p['height_max'])
				||	(isset($p['width_min']) &&!is_null( $p['width_min'] ) && $img[0] < $p['width_min'])
				||	(isset($p['height_min']) &&!is_null( $p['height_min'] ) && $img[1] < $p['height_min']) )
			{
				foreach( array( 'width'=>'Largeur', 'height'=>'Hauteur' ) as $k=>$v )
				{
					$e = array();
					if ( isset($p[$k]) && !is_null( $p[$k] ) ) {
						$e[]= ' = '.$p[$k];
					} else {
						if(isset($p[$k.'_min']) && !is_null( $p[$k.'_min'] )) {
							$e[]= ' >= '.$p[$k.'_min'];
						}
						if(isset($p[$k.'_max']) && !is_null( $p[$k.'_max'] )) {
							$e[]= ' <= '.$p[$k.'_max'];
						}
					}
					if( $e ) {
						$error[] = $v.' doit être '.implode( ' et ', $e );
					}
				}
			}
		}
		return $value;
	}
}

/**
 @defgroup compositesValidator Validateurs composites
 @ingroup validator
*/

/**
 * On donne un tableau de validateurs au constructeur.
 *
 * Ils sont appliqués à la valeur, dans l'ordre.
 * Par exemple, FormValidatorComposite( array( new FormValidatorInteger(...), new FormValidatorGreaterThan(...) ) )
 * pour avoir un entier supérieur à la valeur d'un autre entier entré dans un autre champ.
 *
 * @ingroup compositesValidator validator
 * @class FormValidatorComposite
 */
class FormValidatorComposite implements iFormValidator
{
	function __construct( $validators )
	{
		$this->validators = $validators;
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		foreach( $this->validators as $validator )
		{
			$value = $validator->Validate( $value, $error, $obj, $form );
			if( $error ) break;
		}
		return $value;
	}
}

/**
 * On donne un validateur au constructeur.
 *
 * La valeur est un tableau, et le validateur est appliqué à chacune des valeurs de ce tableau.
 * @class FormValidatorArrayMap
 * @ingroup compositesValidator validator
 */
class FormValidatorArrayMap implements iFormValidator
{
	function __construct( $validator )
	{
		$this->validator = $validator;
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		foreach( $value as $k=>$v )
		{
			$value[$k] = $this->validator->Validate( $v, $error, $obj, $form );
			if( $error ) break;
		}
		return $value;
	}
}

/**
 * Comparaison "plus grand ou égal à" pour les valeurs des champs.
 *
 * Ce validateur vérifie que la valeur du champ courant est supérieure ou égale à la valeur de l'autre champ.
 * si $type est 'date', on compare des dates au format JJ/MM/AAAA
 *
 * @class FormValidatorGreaterThan
 * @ingroup compositesValidator validator
 */
class FormValidatorGreaterThan implements iFormValidator
{
	/**
	 * @param $other est le nom de l'autre champ.
	 */
	function __construct( $other, $type=false )
	{
		$this->other_field_name = $other;
		$this->type = $type;
	}
	
	/**
	 * @copydoc parent::Validate()
	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		$other = $this->other_field_name;
		$other = $form->$other;

		switch( $this->type )
		{
			case 'date':
				$inerror = pf_compare_jma( pf_date_to_jma( $other->value, true ), pf_date_to_jma( $value, true )) > 0;
				break;
			default:
				$inerror = is_null( $other->value ) || $other->value === '' || $other->value > $value;
				break;

		}
		if( $inerror )	$error[] = "Doit être supérieur à : ".$other->DisplayName();
		return $value;
	}
}

/**
 * Ce validateur vérifie que la valeur du champ courant est égale à la valeur de l'autre champ.
 *
 * @class FormValidatorEquals
 * @ingroup compositesValidator validator
 */
class FormValidatorEquals implements iFormValidator
{
	/**
	 * @param $other est le nom de l'autre champ.
	 */
	function __construct( $other, $type=false )
	{
		$this->other_field_name = $other;
		$this->type = $type;
	}
	
	/**

	 */
	function Validate( $value, &$error, &$obj, &$form )
	{
		$other = $this->other_field_name;
		$other = $form->$other;
		if( $value !== $other->value )
			$error[] = "Doit être égal à : ".$other->DisplayName();
		return $value;
	}
}

