/**
 * Copyright © 2011-2013 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Javascript
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2011-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2011-07-23
 */

//makes ie8 know about html5 tags
document.createElement("header");
document.createElement("footer");
document.createElement("section");
document.createElement("aside");
document.createElement("nav");
document.createElement("article");
document.createElement("figure");
document.createElement("figcaption");
document.createElement("hgroup");
document.createElement("time");

//bad hack for galette's dashboard
$(function(){
    $('#desktop > div > a, #subscribe, #lostpassword, #btn_lostpassword, #memberslist, #trombino, #backhome, #logout, #btnadd, #btnadd_small, #btncancel, #btnvalid, #btnback, #btnvalid, #btnpreview, #btnsend, #btnusers, #btnusers_small, #btnmanagers, #btnmanagers_small, #btngroups, #btnmanagedgroups, #histreset, #next, #prev, #btnlabels, #btn_membercard, #btn_edit, #btn_contrib, #btn_addcontrib').each(function(){
        $(this).append($('<span class="secondimg"></span>'));
    });
});
