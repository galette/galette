
/*
 * common.js, 06 octobre 2007
 * 
 * This file is part of Galette.
 *
 * Copyright © 2007 Johan Cwiklinski
 *
 * File :               	common.js
 * Author's email :     	johan@x-tnd.be
 * Author's Website :   	http://galette.tuxfamily.org
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * 
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

/* On document ready
-------------------------------------------------------- */
$(function() {
	/**
	* Errorbox animation
	*/
	$('#errorbox').backgroundFade({sColor:'#ffffff',eColor:'#ff9999',steps:50},function() {
		$(this).backgroundFade({sColor:'#ff9999',eColor:'#ffffff'});
	});
	$('#warningbox').backgroundFade({sColor:'#ffffff',eColor:'#FFB619',steps:50},function() {
		$(this).backgroundFade({sColor:'#FFB619',eColor:'#ffffff'});
	});
	$('#infobox').backgroundFade({sColor:'#ffffff',eColor:'#99FF99',steps:50},function() {
		$(this).backgroundFade({sColor:'#99FF99',eColor:'#ffffff'});
	});
	$('.debuginfos span').hide();
	/** TODO: find a way to translate this message ==> ajax ? */
	$('.debuginfos').attr('title', 'Click to get more details.');
	$('.debuginfos').click(function(){
		$('.debuginfos span').slideToggle('slow');
	});

	/**
	* Let's round some corners !
	*/
	//should work for IE6 but... ?
	$('#titre').corner();
	$('#menu').corner();
	$('#listfilter').corner();
	$('.trombino').corner();

	$('#login').focus();

	//for tootltips
	//first, we hide tooltips in the page
	$('.tip').hide();
	//and then, we show them on rollover
	$('.tooltip').tooltip({
		//track: true,
		delay: 0,
		showURL: false, 
		showBody: ' - ',
		extraClass: 'tt',
		bodyHandler: function() {
			return $(this).next().html();
		}
	});
});