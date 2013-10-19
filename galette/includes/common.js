/**
 * Copyright © 2007-2013 The Galette Team
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
 * @copyright 2007-2013 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-06
 */

//set up fieldsets spindowns
//the function will spin the element just after legend, and will update the icon
$.fn.spinDown = function() {

    return this.click(function() {
        var $this = $(this);

        $this.parent('legend').next().slideToggle(100);
        $this.toggleClass('ui-icon-circle-arrow-e').toggleClass('ui-icon-circle-arrow-s');

        return false;
    });

};

//make fieldsets collapsibles. This requires a legend and all the following elements to be grouped (for example in a div element)
//The function will 'hide'
var _collapsibleFieldsets = function(){
    $('legend').each(function(){
        var _collapse = $('<a href="#" class="ui-icon ui-icon-circle-arrow-s collapsible">Collapse/Expand</a>');
        $(this).prepend(_collapse);
        _collapse.spinDown();
    });
}

var _fieldsInSortable = function(){
    //so our forms elements continue to work as expected
    $('.fields_list input, .fields_list select').bind(
        'click.sortable mousedown.sortable',
        function(ev) {
            ev.target.focus();
        }
    );
}

var _initSortable = function(){
    $('.fields_list').sortable({
        items: 'li:not(.listing)'
    }).disableSelection();

    _fieldsInSortable();

    $('#members_tab').sortable({
        items: 'fieldset'
    });
}

/* On document ready
-------------------------------------------------------- */

var _messagesEffects = function(){
    /**
    * Errorbox animation
    */
    $('#errorbox').backgroundFade({sColor:'#ffffff',eColor:'#ff9999',steps:50},function() {
        $(this).backgroundFade({sColor:'#ff9999',eColor:'#ffffff'});
    });
    $('#warningbox').backgroundFade({sColor:'#ffffff',eColor:'#FFB619',steps:50},function() {
        $(this).backgroundFade({sColor:'#FFB619',eColor:'#ffffff'});
    });
    $('#infobox, #successbox').backgroundFade({sColor:'#ffffff',eColor:'#99FF99',steps:50},function() {
        $(this).backgroundFade({sColor:'#99FF99',eColor:'#ffffff'});
    });
}

$(function() {
    _messagesEffects();
    $('.debuginfos span').hide();
    /** TODO: find a way to translate this message ==> ajax ? */
    $('.debuginfos').attr('title', 'Click to get more details.');
    $('.debuginfos').click(function(){
        $('.debuginfos span').slideToggle('slow');
    });

    $('#login').focus();

    //for tootltips
    //first, we hide tooltips in the page
    $('.tip').hide();
    //and then, we show them on rollover
    $('.tooltip').tooltip({
        content: function(event, ui) {
            return $(this).next().html();
        }
    });

    $('.nojs').removeClass('nojs');
    $('#menu h1').each(function(){
        $(this).html('<a href="#">' + $(this).text() + '</a>');
    });

    if( $('#menu').size() > 0 ) {
        $('#menu').accordion({
            header: 'h1:not(#logo)',
            icons: {
                header: "ui-icon-circle-arrow-e",
                activeHeader: "ui-icon-circle-arrow-s"
            },
            heightStyle: 'content',
            active: $('#menu ul li[class*="selected"]').parent('ul').prevAll('ul').size()
        });
    }

    $('input:submit, .button, input:reset' ).button({
        create: function(event, ui) {
            if ( $(event.target).hasClass('disabled') ) {
                $(event.target).button('disable');
            }
        }
    });
    $('.selected').addClass('ui-state-disabled');
});
