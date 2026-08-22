/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Accessibility for Fomantic UI dropdowns.
 *
 * Fomantic 2.9.4 (and the 2.10 nightly) -- ships exactly one ARIA
 * attribute in its whole dropdown component: an aria-labelledby on the search
 * input. No role, no aria-expanded, no aria-selected. A dropdown built from a
 * <select> hides that select with display: none, so its <label for="..."> names
 * a control which is no longer in the accessibility tree, and what is left for
 * everyone else is a nameless, roleless div[tabindex="0"].
 *
 * The attributes are added here, once, rather than in each of the dropdowns
 * spread over the templates. The state is kept in step with a MutationObserver:
 * Fomantic marks an open dropdown with the classes "active visible" on its
 * container, whoever opened it, so the state follows the DOM rather than
 * depending on the component callbacks -- which a score of template call sites
 * override with their own.
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
     * Name the control of a dropdown that picks a value.
     *
     * The label of the form points at the select, which is hidden, or at the
     * container; either way the name has to be carried over to whatever is left
     * focusable. Fomantic names the search variant itself, so an existing name
     * is never overwritten.
     */
    var _nameControl = function($control, $value, $dd) {
        if ($control.attr('aria-label') || $control.attr('aria-labelledby')) {
            return;
        }

        var $label = $();
        //the name of the value element as a last resort: a dropdown keeping its
        //value in a hidden input has nothing labelable in it, so the templates
        //point the label at that name, which ties the two together for nobody
        //-- only ARIA can, from here
        [$value.attr('id'), $dd.attr('id'), $value.attr('name')].forEach(function(reference) {
            if (!$label.length && reference) {
                $label = $('label').filter(function() {
                    return this.getAttribute('for') === reference;
                }).first();
            }
        });
        if (!$label.length) {
            $label = $dd.closest('label');
        }
        if ($label.length) {
            $control.attr('aria-labelledby', _ensureId($label.first(), 'label'));
            return;
        }

        var fallback = $value.attr('title') || $dd.attr('aria-label') || $dd.attr('title');
        if (fallback) {
            $control.attr('aria-label', fallback);
        }
    };

    /**
     * A dropdown that picks a value: a combobox over a listbox of options
     */
    var _asListbox = function($dd, $menu, $value) {
        var $search = $dd.children('input.search').first(),
            //the search variant moves the focus to its input, and then the
            //container is not focusable at all
            $control = $search.length ? $search : $dd
        ;

        $control.attr({
            'role': 'combobox',
            'aria-haspopup': 'listbox',
            'aria-controls': _ensureId($menu, 'listbox'),
            'aria-expanded': 'false'
        });
        if ($search.length) {
            $control.attr('aria-autocomplete', 'list');
        }
        _nameControl($control, $value, $dd);

        if ($value.prop('required')) {
            $control.attr('aria-required', 'true');
        }
        if ($value.prop('disabled') || $dd.hasClass('disabled')) {
            $control.attr('aria-disabled', 'true');
        }

        $menu.attr('role', 'listbox');
        if ($value.prop('multiple')) {
            $menu.attr('aria-multiselectable', 'true');
        }

        $dd.data('a11yControl', $control);
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
     * A dropdown that reveals a menu: a button, and what it shows.
     *
     * Deliberately not role="menu": these hold plain links and actions, and a
     * menu promises a keyboard model of its own (arrows mandatory, Tab leaving
     * the whole menu) that this does not implement. A half kept promise reads
     * worse than none, so the pattern is the disclosure the W3C recommends for
     * site navigation.
     */
    var _asDisclosure = function($dd, $menu) {
        //the menu the component builds is a child of the very element it is
        //opened from. A role on that element would nest the entries of the menu
        //inside their own button -- announced inconsistently by assistive
        //technologies, and reported by axe as nested-interactive -- so the
        //button is an inner element holding what is visible of the trigger, and
        //nothing else.
        var $caret = $dd.children('.dropdown.icon'),
            $trigger = $('<span class="a11y-trigger"></span>')
        ;
        //the caret stays a direct child, which is how the stylesheet of the
        //component addresses it -- unless it is all there is to the trigger, and
        //then the button has to hold it or it would have no box at all
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

        $dd.data('a11yControl', $trigger);
    };

    /**
     * Which option is selected, and which one the keyboard cursor is on.
     *
     * Fomantic keeps both in classes: "active" is the chosen value, "selected"
     * is where the cursor sits.
     */
    var _refreshOptions = function($dd, $menu) {
        var $control = $dd.data('a11yControl') || $dd;

        $menu.children('.item').each(function() {
            var $item = $(this);
            $item.attr({
                'role': 'option',
                'aria-selected': $item.hasClass('active') ? 'true' : 'false'
            });
        });

        var $cursor = $menu.children('.item.selected').first();
        if ($dd.hasClass('visible') && $cursor.length) {
            $control.attr('aria-activedescendant', _ensureId($cursor, 'option'));
        } else {
            $control.removeAttr('aria-activedescendant');
        }
    };

    /**
     * Report whether the dropdown is open
     */
    var _refreshState = function($dd) {
        if (!$dd.data('a11yBound')) {
            return;
        }

        var $control = $dd.data('a11yControl') || $dd,
            $menu = $dd.children('.menu').first()
        ;

        $control.attr('aria-expanded', $dd.hasClass('visible') ? 'true' : 'false');

        if ($menu.attr('role') === 'listbox') {
            _refreshOptions($dd, $menu);
        }
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
        //"selection" is how it marks a dropdown standing for a form control,
        //and some of them are built without any value element at all
        var $value = $dd.children('select, input[type="hidden"]').first();
        if ($value.length || $dd.hasClass('selection')) {
            _asListbox($dd, $menu, $value);
        } else {
            _asDisclosure($dd, $menu);
        }

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
                } else if ($target.hasClass('item')) {
                    //the cursor moved, or a value was picked
                    _refreshState($target.closest('.ui.dropdown'));
                }
            });
        }).observe(document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class']
        });
    };

    /**
     * What the component leaves to do on the keyboard.
     *
     * Fomantic opens on the down arrow only, closes on Escape only when its
     * cursor already sits on an entry, and drops the focus on the document body
     * once a value has been picked. Delegated from the document, so dropdowns
     * created later are covered too.
     */
    var _keyboard = function() {
        $(document).on('keydown', '.ui.dropdown', function(event) {
            var $dd = $(event.target).closest('.ui.dropdown');
            if (!$dd.data('a11yBound') || $dd.hasClass('disabled')) {
                return;
            }

            var $control = $dd.data('a11yControl') || $dd,
                $menu = $dd.children('.menu').first(),
                isSearch = $(event.target).is('input.search'),
                open = $dd.hasClass('visible')
            ;

            //Enter and Space open what is closed. Space is left alone in a
            //search field, where it is a character like any other.
            if (event.key === 'Enter' || (event.key === ' ' && !isSearch)) {
                if (!open) {
                    $dd.dropdown('show');
                    event.preventDefault();
                    return;
                }
                if (event.key === ' ' && !$menu.children('.item.selected').length) {
                    //open, but the cursor is nowhere: close rather than pick
                    $dd.dropdown('hide');
                    event.preventDefault();
                    return;
                }
                //otherwise Fomantic picks the entry; it then blurs, and a
                //keyboard user would have to tab from the top of the page
                window.setTimeout(function() {
                    if (document.activeElement === document.body) {
                        $control.trigger('focus');
                    }
                }, 0);
                return;
            }

            if (event.key === 'Escape' && open) {
                $dd.dropdown('hide');
                $control.trigger('focus');
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            //Fomantic knows neither Home nor End. In a search field they
            //belong to the text being typed, and are left alone.
            if ((event.key === 'Home' || event.key === 'End') && open && !isSearch) {
                var $items = $menu.children('.item:not(.filtered):not(.disabled)'),
                    $target = event.key === 'Home' ? $items.first() : $items.last()
                ;
                if ($target.length) {
                    $items.removeClass('selected');
                    $target.addClass('selected');
                    $target[0].scrollIntoView({block: 'nearest'});
                    //the arrow keys of the component pick the value as the
                    //cursor moves; these two behave the same way, or the same
                    //list would answer differently to two ways of walking it
                    if ($dd.dropdown('setting', 'selectOnKeydown') && !$dd.hasClass('multiple')) {
                        $dd.dropdown('set selected', $target.attr('data-value'));
                    }
                    _refreshState($dd);
                    event.preventDefault();
                }
            }
        });
    };

    return {
        /**
         * Describe the dropdowns of the page, and keep doing so
         */
        install: function() {
            _sweep();
            _observe();
            _keyboard();
        },

        /**
         * Describe the dropdowns of a subtree that was just built
         */
        refresh: function(root) {
            _sweep(root);
        }
    };
})();
