/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Accessibility for Fomantic UI dropdowns revealing a menu.
 *
 * Everything a dropdown standing for a form control needs -- the combobox and
 * its listbox, the name taken from the label of the form, the state of the
 * options, and the keys the component was missing -- lives in the component
 * itself, added by patches/fomantic-dropdown-a11y.patch at build time, so that
 * the very same diff can be proposed upstream.
 *
 * What is left here is what cannot be described with attributes alone. A
 * dropdown revealing a menu is a disclosure: a button, and what it shows. But
 * the menu the component builds is a child of the very element it is opened
 * from, so a role on that element nests the entries of the menu inside their
 * own button -- announced inconsistently by assistive technologies, and
 * reported by axe as nested-interactive. The button has to be an inner element,
 * which is a change of markup, and one upstream would rightly weigh against
 * every other layout built on that component.
 *
 * Deliberately not role="menu": these hold plain links and actions, and a menu
 * promises a keyboard model of its own (arrows mandatory, Tab leaving the whole
 * menu) that this does not implement. A half kept promise reads worse than
 * none, so the pattern is the disclosure the W3C recommends for site
 * navigation.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
var _dropdownA11y = (function() {
    var _uid = 0;

    /**
     * Give an element an id if it has none, so that it can be referenced
     */
    var _ensureId = function($element, prefix) {
        if (!$element.attr('id')) {
            _uid = _uid + 1;
            $element.attr('id', prefix + '_a11y_' + _uid);
        }
        return $element.attr('id');
    };

    /**
     * Keep the blanks of the source from showing up once an element has been
     * added around them.
     *
     * The ones at both ends belong outside, where they were. And a flex
     * container drops blank children altogether, so those which sat between two
     * of them were showing nothing; inside the added box they would suddenly be
     * a space, and everything around would move by a couple of pixels.
     */
    var _keepBlanksHarmless = function($element, $parent) {
        var isBlank = function(node) {
                return node && node.nodeType === 3 && !node.nodeValue.trim();
            },
            display = window.getComputedStyle($parent[0]).display
        ;

        while (isBlank($element[0].firstChild)) {
            $element.before($element[0].firstChild);
        }
        while (isBlank($element[0].lastChild)) {
            $element.after($element[0].lastChild);
        }

        if (display === 'flex' || display === 'inline-flex') {
            $element.contents().filter(function() {
                return isBlank(this);
            }).remove();
        }
    };

    /**
     * A dropdown that reveals a menu: a button, and what it shows
     */
    var _asDisclosure = function($dd, $menu) {
        //the caret stays a direct child, which is how the stylesheet of the
        //component addresses it -- unless it is all there is to the trigger, and
        //then the button has to hold it or it would have no box at all
        var $caret = $dd.children('.dropdown.icon'),
            $trigger = $('<span class="a11y-trigger"></span>')
        ;
        $dd.contents().not($menu).not($caret).not($dd.children('input, select')).appendTo($trigger);
        if (!$trigger.contents().length) {
            $caret.appendTo($trigger);
        }
        $trigger.prependTo($dd);
        _keepBlanksHarmless($trigger, $dd);

        $trigger.attr({
            'role': 'button',
            'tabindex': '0',
            'aria-haspopup': 'true',
            'aria-controls': _ensureId($menu, 'menu'),
            'aria-expanded': 'false'
        });
        //one stop on the keyboard, not two
        $dd.attr('tabindex', '-1');

        var name = $dd.attr('aria-label')
            || $dd.attr('title')
            || $trigger.text().replace(/\s+/g, ' ').trim();
        if (name) {
            $trigger.attr('aria-label', name);
        }

        $dd.data('a11yTrigger', $trigger);
    };

    /**
     * Report whether the menu is open.
     *
     * Fomantic marks an open dropdown with the classes "active visible" on its
     * container, whoever opened it, so the state follows the DOM rather than
     * depending on the component callbacks -- which a score of template call
     * sites override with their own.
     */
    var _refreshState = function($dd) {
        var $trigger = $dd.data('a11yTrigger');
        if (!$trigger) {
            return;
        }

        $trigger.attr('aria-expanded', $dd.hasClass('visible') ? 'true' : 'false');
    };

    /**
     * Describe one dropdown, once
     */
    var _setup = function($dd) {
        if ($dd.data('a11yBound')) {
            return;
        }

        //a submenu, inside the menu of another dropdown: it belongs to what
        //is around it, and describing it on its own would contradict that
        if ($dd.parentsUntil('body', '.ui.dropdown').length) {
            return;
        }

        var $menu = $dd.children('.menu').first();
        if (!$menu.length) {
            //not initialised yet, or nothing to open
            return;
        }

        //what the value is kept in, the two places the component looks at;
        //"selection" is how it marks a dropdown standing for a form control.
        //Those are described by the component itself.
        if ($dd.children('select, input[type="hidden"]').length || $dd.hasClass('selection')) {
            return;
        }

        _asDisclosure($dd, $menu);
        $dd.data('a11yBound', true);
        _refreshState($dd);
    };

    /**
     * Describe every dropdown of a subtree, the whole page by default
     */
    var _sweep = function(root) {
        $(root || document).find('.ui.dropdown').each(function() {
            _setup($(this));
        });
    };

    /**
     * Follow what Fomantic does to the DOM.
     *
     * Both what it adds -- dropdowns are built at page load, but also by a
     * score of template call sites, and by the advanced search as fields are
     * added -- and the classes it toggles, which is where the state is.
     */
    var _observe = function() {
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        var pending = false,
            sweepSoon = function() {
                if (pending) {
                    return;
                }
                pending = true;
                window.requestAnimationFrame(function() {
                    pending = false;
                    _sweep();
                });
            }
        ;

        new MutationObserver(function(records) {
            records.forEach(function(record) {
                if (record.type === 'childList') {
                    if (record.addedNodes.length) {
                        sweepSoon();
                    }
                    return;
                }

                var $target = $(record.target);
                if ($target.hasClass('dropdown')) {
                    _setup($target);
                    _refreshState($target);
                }
            });
        }).observe(document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class']
        });
    };

    return {
        /**
         * Describe the dropdowns of the page, and keep doing so
         */
        install: function() {
            _sweep();
            _observe();
        }
    };
})();
