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
 * @author    Guillaume AGNIERAY
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2019 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 */

$(function() {
    /* Fomantic UI components */
    $('.ui.sidebar').sidebar('attach events', '.toc.item');
    $('.ui.dropdown,select:not(.nochosen)').dropdown();
    $('.ui.accordion').accordion();
    $('.ui.checkbox, .ui.radio.checkbox').checkbox();
    $('.ui.tabbed .item').tab({});
    $('[id$="rangestart"], [id$="rangeend"]').calendar({
      type: 'date',
      firstDayOfWeek: 1,
      monthFirst: false,
      /* TODO : Find a way to translate widget content.
       * https://fomantic-ui.com/modules/calendar.html#language
       */
      text: {
        days: ['S', 'M', 'T', 'W', 'T', 'F', 'S'],
        months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        today: 'Today',
        now: 'Now',
      },
      formatter: {
        date: function (date, settings) {
          if (!date) return '';
          var day = date.getDate() + '';
          if (day.length < 2) {
            day = '0' + day;
          }
          var month = (date.getMonth() + 1) + '';
          if (month.length < 2) {
            month = '0' + month;
          }
          var year = date.getFullYear();
          return day + '/' + month + '/' + year;
        }
      }
    });

    /* Fomantic UI Tooltips */
    /* Default behaviour for each link with a title attribute.
     * Created next to current element, and not removed from the DOM
     * after being hidden (inline: true).
     */
    $('a[title]').popup({
        inline: true
    });
    /* Disabled on links in menus.
     * TODO : find a proper way to use popups in menus. Maybe
     * using data-tooltip, because either inline false or true has side
     * effects (wrong position in fixed top bar or erratic display when
     * folding and unfolding accordions or dropdowns).
     */
    $('.ui.menu a[title]').popup({
        onShow: function (el) {
            return false;
        }
    });
    /* Click event on popup icon tooltip.
     * data-html attribute appended to body and removed after being
     * hidden (inline: false).
     */
    $('i.tooltip').popup({
        inline: false, // default value
        on: 'click',
    });
});

/* Display tables legends in Fomantic UI modal */
var _bind_legend = function() {
    $('.show_legend').click(function(e){
        $('#legende').modal('show');
    });
}
