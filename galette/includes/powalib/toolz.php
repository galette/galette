<?php

/************************
	Outils divers
*************************
* Que je n'utilise plus, mais nécessaire dans la version actuelle de la lib.
* J'ai remplacé ces fonctions par les miennes, mais celles-ci sont trop
* étroitement lié a d'autres outils pour être distribués.
* Je fournis donc les outils originaux.
**/

// print_r avec joli formatage
function xdump( $v )	{	echo '<pre>';	print_r( $v );	echo '</pre>';	}

// var_dump avec joli formatage
function xvdump( $v )	{	echo '<pre>';	var_dump( $v );	echo '</pre>';	}

/*	Construit une URL en reprenant l'URL actuelle et certains de ses paramètres GET
	si $keep est un array, seuls les éléments de $keep sont conservés
	si $remove est un array, les éléments de $remove sont enlevés
	si $add est un array( clé => valeur ), ces paramètres sont ajoutés à l'URL
	exemple :
	url actuelle : truc.php?a=1&b=2
	url_from_get_vars( array( 'a' ), false, array( 'c' => 3 ) )
		=> truc.php?a=1&c=3
	url_from_get_vars( false, array( 'a' ), array( 'c' => 3 ) )
		=> truc.php?b=2&c=3
*/
function url_from_get_vars( $keep=false, $remove=false, $add = array() )
{
	$params = array();
	foreach( $_GET as $key => $value )
		if( (!$keep || in_array( $key, $keep )) && !($remove && in_array( $key, $remove )) )
			$params[] = $key.'='.urlencode( $value );
	foreach( $add as $key => $value )
		$params[] = $key.'='.urlencode( $value );
	return $_SERVER['PHP_SELF'].'?'.implode( '&', $params );
}


/*	Calcule le nombre de lignes et de colonnes pour afficher un tableau harmonieux
	$ncells		nombre d'éléments à mettre dans le tableau
	$maxcols	nombre maxi de colonnes
	$maxrows	nombre maxi de lignes
	$prefer_few_cols	lorsque $maxcols*$maxrows < $ncells, le tableau sera plus grand que
	la taille max demandée. Si $prefer_few_cols est true, génère un tableau avec $maxcols colonnes
	et plus de lignes, sinon génère un tableau avec $maxrows lignes et plus de colonnes.
	retourne array( colonnes, lignes )
*/
function calc_table_columns( $ncells, $maxcols, $maxrows, $prefer_few_cols = false )
{
	if( !$ncells )
		return array( 1, "100%" );

	// voir si on peut faire un tableau propre sans coin blanc en bas à droite
	if( $prefer_few_cols )
	{
		for( $cols=1; $cols<=$maxcols; ++$cols )
			if( $cols*$maxrows >= $ncells  && !($ncells % $cols) )
				return array( $cols, sprintf( "%d%%", 100/$cols ));
		for( $cols=1; $cols<=$maxcols; ++$cols )
			if( $cols*$maxrows >= $ncells )
				return array( $cols, sprintf( "%d%%", 100/$cols ));
	}
	else
	{
		for( $cols=$maxcols; $cols>0; --$cols )
			if( $cols*$maxrows >= $ncells  && !($ncells % $cols) )
				return array( $cols, sprintf( "%d%%", 100/$cols ));
		for( $cols=$maxcols; $cols>0; --$cols )
			if( $cols*$maxrows >= $ncells )
				return array( $cols, sprintf( "%d%%", 100/$cols ));
	}

	return array( $maxcols, sprintf( "%d%%", 100/$maxcols ));
}

/*	Retourne un tableau $table[ligne][colonne], rempli avec les éléments
	du tableau $stuff, et formaté sur $cols colonnes.
	exemple, $stuff = array( a,b,c,d,e,f ) et $cols=3 renvoie
	[[a,b,c],[d,e,f]]
*/
function make_table( $stuff, $cols )
{
	$table = array();
	$rows = ceil( sizeof( $stuff ) / $cols );
	for( $y=0; $y<$rows; $y++ )
		for( $x=0; $x<$cols; $x++ )
			$table[$y][$x] = null;

	$pos = 0;
	foreach( $stuff as $item )
	{
		$table[ $pos % $rows ][ floor( $pos / $rows ) ] = $item;
		$pos++;
	}

	return $table;
}

function prettyq( $q )
{
	return preg_replace( "/(^| )([A-Z]{2,})( |$)/", " <strong>$2</strong> ", $q );
}

// quote une valeur pour la base de données de manière intelligente
function db_quote( $val )
{
    switch( $type = gettype( $val ))
    {
        case 'boolean':
        case 'integer':
            return intval( $val );
            break;

        case 'double':
            return floatval( $val );
            break;

        case 'NULL':
            return  'NULL';
            break;

        case 'array':
            return implode( ', ', array_map( 'intval', $val ));
            break;

        default:
            return "'".$val."'";
            break;
    }
}

function db_quote_query( )
{
    $args = func_get_args();
    $sql = array_shift($args);

    // pas de paramètres : renvoyer la requête telle quelle
    if (count( $args ) < 1) {
        return $sql;
    }

    // quote params
    foreach($args[0] as $v) {
        $params[] = db_quote($v);
    }

    return vsprintf( $sql, $params );
}



