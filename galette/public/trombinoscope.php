<?php

// Copyright © 2006 Alexandre 'laotseu' DE DOMMELIN
// Copyright © 2007-2009 Johan Cwiklinski
//
// This file is part of Galette (http://galette.tuxfamily.org).
//
// Galette is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Galette is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Galette. If not, see <http://www.gnu.org/licenses/>.

/**
 * Trombinoscope
 *
 * On affiche les adhérents qui ont souhaité rendre leurs informations 
 * publiques et qui ont placé une photo sur Galette
 *
 * @package    Galette
 *
 * @author     Alexandre 'laotseu' DE DOMMELIN
 * @author     Johan Cwiklinski <johan@x-tnd.be>
 * @copyright  2006 Alexandre 'laotseu' DE DOMMELIN
 * @copyright  2007-2009 Johan Cwiklinski
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version    $Id$
 * @since      Disponible depuis la Release 0.62
 */

$base_path = '../';
require_once( $base_path . 'includes/galette.inc.php');

$members = Members::getPublicList(true);

$tpl->assign('page_title', _T("Trombinoscope"));
$tpl->assign('members', $members);
$content = $tpl->fetch('trombinoscope.tpl');
$tpl->assign('content', $content);
$tpl->display('public_page.tpl');
?>

