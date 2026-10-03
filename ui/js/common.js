/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
/* Fomantic UI components */
var _bindFomanticComponents = function() {
    var
        $sidebar         = $('.ui.sidebar'),
        $dropdown        = $('.ui.dropdown:not(.navigation, .autosubmit), select'),
        $dropdownNav     = $('.ui.dropdown.navigation'),
        $accordion       = $('.ui.accordion'),
        $checkbox        = $('.ui.checkbox, .ui.radio.checkbox'),
        $tabulation      = $('.ui.tabbed .item'),
        $popup           = $('a[title], button[title], .tooltip'),
        $iconOnly        = $('.icon-only'),
        $inlinePopup     = $('.inline-tooltip'),
        $infoPopup       = $('i.circular.basic.question.icon.tooltip'),
        $menuPopupRight  = $('.ui.vertical.accordion.menu a[title], .ui.vertical.accordion.menu button[title]'),
        $menuPopupBottom = $('.ui.top.fixed.menu a.item[title], .ui.top.fixed.menu button.item[title]'),
        $menuPopupLeft   = $('.ui.dropdown.right-aligned a[title], .ui.dropdown.right-aligned button[title]')
    ;

    $sidebar.sidebar('attach events', '.toc.item');

    /* Make all dropdowns clickable when js is enabled for UX consistency.
     * Keep them hoverable only when js is disabled.
     */
    $('.simple.dropdown').removeClass('simple');
    $dropdown.dropdown();

    /* Required for keyboard accessibility on dropdowns used in navigation.
     */
    $dropdownNav.dropdown({
        // Set default action : simply open the link selected.
        action: function(text, value, element) {
            location.href = element[0].href;
        }
    });

    $accordion.accordion();

    $checkbox.checkbox();

    $tabulation.tab();

    /* Fomantic UI Tooltips */
    /* Hide all popups when a dropdown is shown. */
    $.fn.dropdown.settings.onShow = function() {
        $('body').popup('hide all');
    };
    /* Hide all popups when an accordion is opened. */
    $.fn.accordion.settings.onOpening = function() {
        $('body').popup('hide all');
    };
    /* Default behaviour for tooltips on links with a title attribute,
     * or other tags with the "tooltip" class.
     * The title (or data-html) attribute is appended to body and removed
     * from DOM after being hidden (inline: false).
     */
    $popup
        .popup({
            variation: 'inverted',
            inline: false,
            addTouchEvents: false,
        })
    ;
    $iconOnly.each(function() {
        const $this = $(this);
        $this.popup({
            variation: 'inverted',
            inline: false,
            addTouchEvents: false,
            content: $this.find('.visually-hidden').html(),
            });
        })
    ;
    $inlinePopup
        .popup({
            variation: 'inverted',
            inline: true,
            addTouchEvents: false,
        })
    ;
    /* Touch events are allowed on info icons popups.
     */
    $infoPopup
        .popup({
            variation: 'inverted',
            inline: false,
            addTouchEvents: true,
        })
    ;
    /* Position right on the main accordion menu.
     */
    $menuPopupRight
        .popup({
            position: 'right center',
            variation: 'inverted',
            delay: {
                show: 300
            },
            addTouchEvents: false,
        })
    ;
    /* Position bottom on the top fixed menu.
     */
    $menuPopupBottom
        .popup({
            position: 'center bottom',
            variation: 'inverted',
            addTouchEvents: false,
        })
    ;
    /* Position left on the top right language dropdown menu.
     */
    $menuPopupLeft
        .popup({
            position: 'left center',
            variation: 'inverted',
            delay: {
                show: 300
            },
            addTouchEvents: false,
        })
    ;
}

/* Required for keyboard navigation accessibility.
 */
var _keyboardNavigation = function() {
    // Accordion menus
    var _folds = document.querySelectorAll('[data-fold^="fold-"]');
    _folds.forEach(item => {
        item.addEventListener('keydown', event => {
            if (event.keyCode == 13) {
                event.target.click();
            }
        })
    });
    // Mobile menu trigger. Opening it is the business of the button, which
    // answers to Enter and to Space on its own; what is left to do here is to
    // take the reading position into the panel it just opened.
    var _mobile_menu_trigger = document.querySelector('#top-navbar .toc.item');
    if (_mobile_menu_trigger) {
        _mobile_menu_trigger.addEventListener('click', event => {
            // a click with no pointer behind it was a key press
            if (event.detail === 0) {
                var url = location.href;
                location.href = "#sidebarmenu";
                history.replaceState(null,null,url);
            }
        });
    }
}

var _bind_check = function(boxelt) {
    if (boxelt === undefined) {
        boxelt = 'entries_sel'
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
        var _haschecked = false;
        $('table.listing :checkbox[name="' + boxelt + '[]"]').each(function(){
            if ($(this).is(':checked')) {
                this.checked = false;
            } else {
                this.checked = true;
                _haschecked = true;
            }
        });
        if (_haschecked) {
            _is_checked = false;
        } else {
            _is_checked = true;
        }
        return false;
    });
};

/* Display tables legends in Fomantic UI modal */
var _bind_legend = function() {
    $('.show_legend').click(function(e){
        $('#legende').modal('show');
    });
}

/* Turn the server side flash messages into Fomantic UI toasts.
 * The messages are the only rendering browsers without javascript get, so
 * they live in the markup; here they are replayed as toasts, then dropped.
 */
var _bind_messages = function() {
    var $messages = $('#messages');

    if (!$.fn.toast) {
        /* Fomantic did not load: show the messages where they are rather than
         * leaving the user without any feedback at all.
         */
        $messages.show();
        return;
    }

    $messages.children('.ui.message').each(function() {
        var $message = $(this),
            $content = $message.children('.content').first(),
            $header  = $content.children('.header').first(),
            title    = $header.length ? $header.html().trim() : '',
            options  = {
                position: 'top attached',
                closeIcon: true,
                title: title,
                showIcon: $message.data('toastIcon'),
                class: $message.data('toastClass')
            }
        ;

        $header.remove();
        options.message = $content.html().trim();

        if ($message.data('toastPersistent')) {
            options.displayTime = 0;
        } else {
            options.displayTime = 'auto';
            options.minDisplayTime = 5000;
            options.wordsPerMinute = 80;
            options.showProgress = 'bottom';
        }

        $('body').toast(options);
    });

    $messages.remove();

    /* Enable dismissable messages */
    $('.message .close').on('click', function() {
        $(this).closest('.message').transition('fade');
    });

    /* Apply transitions on inline messages */
    $('.message.with-transition').transition('flash');
}

$(function() {
    /* First, so that a failure further down never costs the user a message. */
    _bind_messages();

    _bindFomanticComponents();

    /* Add the accessibility semantics and keyboard behaviour for Fomantic
     * dropdowns, including form submission for autosubmit dropdowns.
     */
    _dropdownA11y.install();

    _keyboardNavigation();

    var _back2Top = document.getElementById("back2top");
    if (_back2Top) {
        document.body.addEventListener('scroll', function() {
            if (document.body.scrollTop > 150 || document.documentElement.scrollTop > 150) {
                _back2Top.style.display = "block";
            } else {
                _back2Top.style.display = "none";
            }
        });
        _back2Top.onclick = function(event){
            event.preventDefault();
            document.body.scrollTop = 0;
            document.documentElement.scrollTop = 0;
        }
    }
});
