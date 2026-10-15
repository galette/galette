/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/*
 * Maps pages behaviour.
 *
 * Templates only provide data, as JSON in #maps-config; every text shown in
 * the map goes through the DOM (textContent), never through HTML strings.
 */
(function ($) {
    'use strict';

    /** content the locate control popup is created with, replaced on opening */
    const LOCATE_POPUP = 'maps-locate-popup';

    /**
     * Build an element
     *
     * @param {string} tag      Tag name
     * @param {string} [text]   Text content
     * @param {string} [classes] CSS classes
     */
    function el(tag, text, classes) {
        const elt = document.createElement(tag);
        if (text !== undefined && text !== null) {
            elt.textContent = text;
        }
        if (classes) {
            elt.className = classes;
        }
        return elt;
    }

    /**
     * Display a message in a modal
     *
     * @param {string}  message Message, displayed as text
     * @param {boolean} reload  Reload page on close
     */
    function showMessage(message, reload) {
        $('body').modal({
            class: 'tiny',
            content: $('<div>').text(message).html(),
            actions: [{
                text: config.strings.close,
                click: function () {
                    if (reload) {
                        window.location.reload();
                    }
                }
            }],
            className: {
                title: 'center aligned header',
                content: 'center aligned content',
                actions: 'center aligned actions'
            }
        }).modal('show');
    }

    /**
     * Post a coordinates change
     *
     * @param {Object}      data     Posted data
     * @param {HTMLElement} button   Button to show as loading
     * @param {string}      fallback Error message when server gave none
     */
    function postCoords(data, button, fallback) {
        $.ajax({
            url: config.member.store_url,
            type: 'POST',
            data: data,
            beforeSend: function () {
                $(button).addClass('loading');
            },
            complete: function () {
                $(button).removeClass('loading');
            },
            success: function (res) {
                showMessage(res.message, true);
            },
            error: function (xhr) {
                showMessage(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback, false);
            }
        });
    }

    /**
     * Build the "I live here" popup content for a position
     *
     * @param {L.LatLng} latlng Position
     * @param {string}   [name] Place name
     */
    function livesHereContent(latlng, name) {
        //a click on a copy of the world gives a longitude beyond 180
        const position = latlng.wrap();
        //the precision coordinates are stored with
        const shown = position.lat.toFixed(6) + '/' + position.lng.toFixed(6);
        const content = el('div');
        const where = el('p');
        if (name) {
            where.appendChild(el('strong', name));
            where.appendChild(el('br'));
            where.appendChild(el('em', shown));
        } else {
            //"You clicked at %1$s", the position in italics
            const parts = config.strings.clicked_at.split('%1$s');
            where.appendChild(document.createTextNode(parts[0]));
            where.appendChild(el('em', shown));
            where.appendChild(document.createTextNode(parts.slice(1).join('%1$s')));
        }
        content.appendChild(where);

        const button = el('button', config.strings.lives_here, 'ui button');
        button.type = 'button';
        button.addEventListener('click', function () {
            postCoords(
                {latitude: position.lat, longitude: position.lng},
                button,
                config.strings.store_error
            );
        });
        const actions = el('p');
        actions.appendChild(button);
        content.appendChild(actions);

        return content;
    }

    /**
     * Build the known position popup content
     */
    function knownPositionContent() {
        const content = el('div');
        content.appendChild(el('strong', config.member.name));
        content.appendChild(el('br'));
        content.appendChild(document.createTextNode(config.strings.lives_here));

        if (config.member.can_edit) {
            content.appendChild(el('br'));
            const button = el('button', config.strings.remove, 'ui button');
            button.type = 'button';
            button.addEventListener('click', function () {
                $('body').modal({
                    title: config.strings.remove_title,
                    class: 'tiny',
                    content: $('<div>').text(config.strings.remove_confirm).html(),
                    actions: [{
                        text: config.strings.remove,
                        class: 'red confirm_remove',
                        icon: 'trash alt',
                        click: function () {
                            postCoords({remove: true}, $('.confirm_remove'), config.strings.remove_error);
                        }
                    }, {
                        text: config.strings.close
                    }],
                    className: {
                        title: 'center aligned header',
                        content: 'center aligned content',
                        actions: 'center aligned actions'
                    }
                }).modal('show');
            });
            content.appendChild(button);
        }

        return content;
    }

    /**
     * Does the browser render vector tiles? They need WebGL 2
     */
    function hasWebGL2() {
        try {
            return !!(window.WebGL2RenderingContext && document.createElement('canvas').getContext('webgl2'));
        } catch (err) {
            return false;
        }
    }

    /**
     * Add background map
     *
     * @param {L.Map} map Map
     */
    function addTiles(map) {
        const tiles = config.tiles;
        if (tiles.vector && hasWebGL2()) {
            const options = {style: tiles.url};
            if (tiles.attribution !== '') {
                //given one, it wins; otherwise the bridge reads it off the style sources
                options.attributionControl = {customAttribution: tiles.attribution};
            }
            L.maplibreGL(options).addTo(map);
            return;
        }

        const raster = tiles.vector ? tiles.fallback : tiles;
        const options = {
            maxZoom: raster.maxzoom,
            attribution: raster.attribution
        };
        if (raster.subdomains) {
            options.subdomains = raster.subdomains;
        }
        L.tileLayer(raster.url, options).addTo(map);
    }

    /**
     * Build the map and its controls
     */
    function buildMap() {
        const map = L.map('map', {
            gestureHandling: true,
            maxZoom: config.tiles.maxzoom
        }).setView([config.center.lat, config.center.lng], config.center.zoom);

        new L.Control.FullScreen({
            position: 'topleft',
            title: config.strings.fullscreen,
            titleCancel: config.strings.fullscreen_exit,
            forceSeparateButton: true
        }).addTo(map);

        L.Control.geocoder({
            collapsed: false,
            placeholder: config.strings.search_placeholder,
            errorMessage: config.strings.search_error,
            iconLabel: config.strings.search
        }).addTo(map);

        map.addControl(new L.Control.Legend({position: 'topright'}));
        $('.legend-container').append($('#legend'));
        const toggle = el('span', null, 'legend-toggle-icon');
        const icon = el('i', null, 'big info circle blue icon');
        icon.setAttribute('aria-hidden', 'true');
        toggle.appendChild(icon);
        toggle.appendChild(document.createTextNode(' ' + config.strings.legend));
        $('.legend-toggle').append(toggle);

        if (config.locate) {
            L.control.locate({
                strings: {
                    title: config.strings.locate,
                    popup: LOCATE_POPUP,
                    outsideMapBoundsMsg: config.strings.locate_outside
                }
            }).addTo(map);
        }

        addTiles(map);
        return map;
    }

    /**
     * Members map
     *
     * @param {L.Map} map Map
     */
    function showMembers(map) {
        const group = L.markerClusterGroup();
        config.markers.forEach(function (m) {
            const content = el('p');
            content.appendChild(el('strong', m.name));
            if (m.nickname) {
                content.appendChild(document.createTextNode(' ' + config.strings.aka + ' '));
                content.appendChild(el('em', m.nickname));
            }
            if (m.company) {
                content.appendChild(el('br'));
                content.appendChild(document.createTextNode(m.company));
            }
            const marker = L.marker(
                [parseFloat(m.lat), parseFloat(m.lng)],
                {icon: m.company ? icons.company : icons.member}
            );
            marker.bindPopup(content);
            group.addLayer(marker);
        });
        //snapped positions must not look like street addresses
        if (config.max_zoom) {
            map.setMaxZoom(Math.min(map.getMaxZoom(), config.max_zoom));
        }
        map.addLayer(group);
        if (config.markers.length > 0) {
            map.fitBounds(group.getBounds(), {
                padding: [50, 50],
                maxZoom: config.max_zoom ? Math.min(12, config.max_zoom) : 12
            });
        }
    }

    /**
     * Member localization
     *
     * @param {L.Map} map Map
     */
    function localizeMember(map) {
        if (config.member.can_edit) {
            map.on('click', function (e) {
                L.popup().setLatLng(e.latlng).setContent(livesHereContent(e.latlng)).openOn(map);
            });
            map.on('popupopen', function (e) {
                if (e.popup.getContent() === LOCATE_POPUP) {
                    e.popup.setContent(livesHereContent(e.popup.getLatLng()));
                }
            });
        }

        if (config.member.position) {
            L.marker(
                [parseFloat(config.member.position.latitude), parseFloat(config.member.position.longitude)],
                {icon: icons.member}
            ).addTo(map).bindPopup(knownPositionContent()).openPopup();
            return;
        }

        const towns = $('#possible_towns');
        if (towns.length === 0) {
            return;
        }
        towns.modal('show');
        towns.find('.maps-town').on('click', function () {
            const town = $(this);
            const latlng = L.latLng(parseFloat(town.data('lat')), parseFloat(town.data('lng')));
            towns.modal('hide');
            map.setView(latlng, 13);
            L.marker(latlng).addTo(map)
                .bindPopup(livesHereContent(latlng, String(town.data('name'))))
                .openPopup();
        });
    }

    let config = null;
    let icons = null;

    $(function () {
        const source = document.getElementById('maps-config');
        if (source === null) {
            return;
        }
        config = JSON.parse(source.textContent);

        const icon_options = {
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        };
        icons = {
            member: L.icon($.extend({iconUrl: config.icons.member}, icon_options)),
            company: L.icon($.extend({iconUrl: config.icons.company}, icon_options))
        };

        const map = buildMap();
        if (config.markers) {
            showMembers(map);
        } else if (config.member) {
            localizeMember(map);
        }
    });
})(jQuery);
