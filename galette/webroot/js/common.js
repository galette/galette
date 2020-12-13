/**
 * Copyright © 2007-2014 The Galette Team
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
 * @copyright 2007-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7dev - 2007-10-06
 */

//set up fieldsets spindowns
//the function will spin the element just after legend, and will update the icon
$.fn.spinDown = function() {

    return this.click(function() {
        var $this = $(this);

        $this.parent('legend').next().slideToggle(100);
        var __i = $this.find('i');
        __i.toggleClass('fa-arrow-alt-circle-down').toggleClass('fa-arrow-alt-circle-right');

        return false;
    });

};

//make fieldsets collapsibles. This requires a legend and all the following elements to be grouped (for example in a div element)
//The function will 'hide'
var _collapsibleFieldsets = function(){
    $('legend').each(function(){
        var _collapse = $('<a href="#" class="collapsible tooltip"><i class="fas fa-arrow-alt-circle-down"></i> <span class="sr-only">Collapse/Expand</span></a>');
        $(this).prepend(_collapse);
        _collapse.spinDown();
    });
}

var _fieldsInSortable = function(){
    //so our forms elements continue to work as expected
    $('.fields_list input, .fields_list select').bind(
        'click.sortable mousedown.sortable',
        function(ev) {
            ev.stopPropagation();
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

var _bind_check = function(boxelt){
    if (typeof(boxelt) == 'undefined') {
        boxelt = 'member_sel'
    }
    var _is_checked = true;
    $('.checkall').click(function(){
        $('table.listing :checkbox[name="' + boxelt + '[]"]').each(function(){
            this.checked = _is_checked;
        });
        _is_checked = !_is_checked;
        return false;
    });
    $('.checkinvert').click(function(){
        $('table.listing :checkbox[name="' + boxelt + '[]"]').each(function(){
            this.checked = !$(this).is(':checked');
        });
        return false;
    });
};

var _bind_legend = function() {
    $('#legende h1').remove();
    $('#legende').dialog({
        autoOpen: false,
        modal: true,
        hide: 'fold',
        width: '40%',
        create: function (event, ui) {
            if ($(window ).width() < 767) {
                $(this).dialog('option', {
                        'width': '95%',
                        'draggable': false
                });
            }
        }
    }).dialog('close');

    $('.show_legend').click(function(e){
        e.preventDefault();
        $('#legende').dialog('open');
    });
}


var _initTooltips = function(selector) {
    if (typeof(selector) == 'undefined') {
        selector = '';
    } else {
        selector = selector + ' ';
    }

    //for tootltips
    //first, we hide tooltips in the page
    $(selector + '.tip').hide();
    $(selector + ' label.tooltip, ' + selector + ' span.bline.tooltip').each(function() {
        var __i = $('<i class="fas fa-exclamation-circle"></i>')
        $(this).append(__i);
    });
    //and then, we show them on rollover
    $(document).tooltip({
        items: selector + ".tooltip, a[title]",
        content: function(event, ui) {
            var _this = $(this);
            var _content;

            //first, value from @class=tip element
            var _next = _this.nextAll('.tip');
            if (_next.length > 0) {
                _content = _next.html();
            }

            //and finally, value from @class=sr-only element
            if (typeof _content == 'undefined') {
                var _sronly = _this.find('.sr-only');
                if (_sronly.length > 0) {
                    _content = _sronly.html();
                }
            }

            //second, value from @title
            if (typeof _content == 'undefined') {
                var _title = _this.attr('title');
                if (typeof _title != 'undefined') {
                    _content = _title;
                }
            }

            return _content;
        }
    });
}

var _bindNbshow = function(selector) {
    if (typeof(selector) == 'undefined') {
        selector = '';
    } else {
        selector = selector + ' ';
    }

    $(selector + '#nbshow').change(function() {
        $(this.form).trigger('submit');
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

    _initTooltips();
    $('select:not(.nochosen)').selectize({
        maxItems: 1
    });

    _bindNbshow();
    $('.nojs').removeClass('nojs');
    $('#menu h1').each(function(){
        $(this).html('<a href="#">' + $(this).text() + '</a>');
    });

    if( $('#menu').length > 0 ) {
        $('#menu').accordion({
            header: 'h1:not(#logo)',
            heightStyle: 'content',
            active: $('#menu ul li[class*="selected"]').parent('ul').prevAll('ul').length
        });
    }

    $('input:submit, .button, input:reset, button[type=submit]' ).button({
        create: function(event, ui) {
            if ( $(event.target).hasClass('disabled') ) {
                $(event.target).button('disable');
            }
        }
    });

    if ( $('#back2top').length > 0 ) {
        if (!$('#wrapper').scrollTop() && !$('html').scrollTop() ) {
            $('#back2top').fadeOut();
        }
        $(window).scroll(function() {
            if ($(this).scrollTop()) {
                $('#back2top').fadeIn();
            } else {
                $('#back2top').fadeOut();
            }
        });
    }

    $('select#lang_selector').change(function() {
        this.form.submit();
    });

    /* Language selector.
     * Works per default with CSS only, use javascript to replace with a click event,
     * which is required because of the current way the menu is hidden on mobile devices.
     */
    $('#plang_selector').removeClass('onhover');
    var _langs = $('#plang_selector ul');
    _langs.hide();

    $('#plang_selector > a').on('click', function(event) {
        event.preventDefault();
        var _this = $(this);
        var _open = _this.attr('aria-expanded');
        _this.attr('aria-expanded', !_open);
        _langs.toggle();
    });
});
