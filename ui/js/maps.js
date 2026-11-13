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

    /** marker icons size and anchors */
    const ICON_OPTIONS = {
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    };

    /** data from #maps-config, marker icons and map; set once the map is shown */
    let config = null;
    let icons = null;
    let current_map = null;
    /** changed when a modal map closes: a map still loading for it is not built */
    let map_generation = 0;
    /** modal the map is displayed in, if any */
    let position_modal = null;
    /** a position modal is being requested, or is displayed */
    let position_opening = false;
    /** members whose position is being saved */
    const saving = new Set();
    /** member positions next to member cards, by their container */
    const minimaps = new WeakMap();

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
     * Get rid of a modal built on the fly, once it is hidden
     *
     * Fomantic does it by itself only for modals without onHidden callback.
     *
     * @param {jQuery} modal Modal
     */
    function disposeModal(modal) {
        //not while Fomantic is still hiding it
        setTimeout(function () {
            modal.modal('destroy').remove();
        }, 0);
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
            //the map may be in a modal already
            allowMultiple: true,
            content: $('<div>').text(message).html(),
            actions: [{
                text: config.strings.close
            }],
            //however it is closed: button, Escape or dimmer
            onHidden: function () {
                if (reload) {
                    window.location.reload();
                    return;
                }
                disposeModal($(this));
            },
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
        //what the request is about, whatever happens until it completes
        const modal = position_modal;
        const member = String(config.member.id);
        //one save at a time: answers in another order would show a position other than the stored one
        if (saving.has(member)) {
            return;
        }
        saving.add(member);
        $.ajax({
            url: config.member.store_url,
            type: 'POST',
            data: data,
            //anything else, such as the login page of an expired session, is an error
            dataType: 'json',
            beforeSend: function () {
                $(button).addClass('loading');
            },
            complete: function () {
                saving.delete(member);
                $(button).removeClass('loading');
            },
            success: function (res) {
                if (!res || res.res !== true) {
                    showMessage(res && res.message ? res.message : fallback, false);
                    return;
                }
                //standalone page shows the stored position once reloaded
                if (modal === null) {
                    showMessage(res.message, true);
                    return;
                }
                //a page with a form being filled must not be reloaded; the
                //modal may have been closed, or another one opened, since
                if (position_modal === modal) {
                    modal.modal('hide');
                }
                document.querySelectorAll('.maps-member').forEach(function (container) {
                    if (container.dataset.member === member) {
                        showMemberPosition(container, data.remove ? null : {lat: data.latitude, lng: data.longitude});
                    }
                });
                showMessage(res.message, false);
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
                    allowMultiple: true,
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
     * @param {L.Map}  map   Map
     * @param {Object} tiles Background description, with its raster fallback
     *
     * @return {{layer: L.Layer, maxzoom: number}} Background layer, and its maximum zoom
     */
    function addTiles(map, tiles) {
        //bridge is missing when its bundle could not be loaded
        if (tiles.vector && hasWebGL2() && typeof L.maplibreGL === 'function') {
            const options = {style: tiles.url};
            if (tiles.attribution !== '') {
                //given one, it wins; otherwise the bridge reads it off the style sources
                options.attributionControl = {customAttribution: tiles.attribution};
            }
            return {layer: L.maplibreGL(options).addTo(map), maxzoom: tiles.maxzoom};
        }

        const raster = tiles.vector ? tiles.fallback : tiles;
        const options = {
            maxZoom: raster.maxzoom,
            attribution: raster.attribution
        };
        if (raster.subdomains) {
            options.subdomains = raster.subdomains;
        }
        return {layer: L.tileLayer(raster.url, options).addTo(map), maxzoom: raster.maxzoom};
    }

    /**
     * Build the map and its controls
     */
    function buildMap() {
        const map = L.map('map', {
            gestureHandling: true,
            maxZoom: config.tiles.maxzoom
        }).setView([config.center.lat, config.center.lng], config.center.zoom);

        //gesture handling enables mouse dragging when the pointer enters the
        //map: one already over it when the page loads never does. Pressing a
        //mouse button is enough; dragging handler then catches this same event.
        map.getContainer().addEventListener('pointerdown', function (e) {
            if (e.pointerType === 'mouse' && !map.dragging.enabled()) {
                map.dragging.enable();
            }
        }, true);

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

        //the raster fallback of a vector background may not zoom as far
        map.setMaxZoom(addTiles(map, config.tiles).maxzoom);
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
        towns.modal({allowMultiple: true}).modal('show');
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

    /**
     * Load the vector background bundle, when it is needed and not loaded yet
     *
     * @param {Object}   tiles     Background description
     * @param {string}   gl_script Bundle address
     * @param {Function} callback  Called once it is ready, or failed
     */
    function loadVectorSupport(tiles, gl_script, callback) {
        if (!tiles.vector || typeof L.maplibreGL === 'function' || !hasWebGL2()) {
            callback();
            return;
        }
        const script = document.createElement('script');
        script.src = gl_script;
        //on failure, the raster fallback is displayed
        script.onload = callback;
        script.onerror = callback;
        document.head.appendChild(script);
    }

    /**
     * Show the map #maps-config describes
     *
     * @param {HTMLElement} source #maps-config element
     */
    function start(source) {
        config = JSON.parse(source.textContent);
        icons = {
            member: L.icon($.extend({iconUrl: config.icons.member}, ICON_OPTIONS)),
            company: L.icon($.extend({iconUrl: config.icons.company}, ICON_OPTIONS))
        };

        const generation = map_generation;
        loadVectorSupport(config.tiles, config.gl_script, function () {
            //the modal it was loaded for has been closed meanwhile
            if (generation !== map_generation) {
                return;
            }
            current_map = buildMap();
            if (config.markers) {
                showMembers(current_map);
            } else if (config.member) {
                localizeMember(current_map);
            }
        });
    }

    /**
     * Display a member position page in a modal
     *
     * @param {HTMLAnchorElement} link Link to the page
     */
    function openPositionModal(link) {
        //one at a time: two opening together would share a single map
        if (position_opening) {
            return;
        }
        position_opening = true;
        $.ajax({
            url: link.href,
            beforeSend: function () {
                $(link).addClass('loading');
            },
            complete: function () {
                $(link).removeClass('loading');
            },
            success: function (res) {
                const content = $('<div>').html(res);
                const source = content.find('#maps-config');
                //not the map: an error page, or the login one of an expired session
                if (source.length === 0) {
                    position_opening = false;
                    showOpenError(link);
                    return;
                }
                const strings = JSON.parse(source.text()).strings;
                $.modal({
                    title: $('<div>').text(content.find('[data-modal-title]').data('modal-title') || '').html(),
                    class: 'large maps-modal',
                    content: res,
                    actions: [{
                        text: strings.close,
                        class: 'cancel'
                    }],
                    //Leaflet needs the size of the map
                    onVisible: function () {
                        position_modal = $(this);
                        if (current_map === null) {
                            start(this.querySelector('#maps-config'));
                        }
                    },
                    onHidden: function () {
                        position_modal = null;
                        position_opening = false;
                        ++map_generation;
                        if (current_map !== null) {
                            current_map.remove();
                            current_map = null;
                        }
                        //Fomantic moved it out of the modal; the next ones come with the same ids
                        disposeModal($('#possible_towns'));
                        disposeModal($(this));
                    }
                }).modal('show');
            },
            error: function () {
                position_opening = false;
                showOpenError(link);
            }
        });
    }

    /**
     * The map could not be loaded in a modal
     *
     * Not going to its page: a member form being filled would be lost.
     *
     * @param {HTMLAnchorElement} link Link to the map page
     */
    function showOpenError(link) {
        const strings = JSON.parse(document.getElementById('maps-page-strings').textContent);
        $('body').modal({
            class: 'tiny',
            content: $('<div>').text(strings.open_error).html(),
            actions: [{
                text: strings.retry,
                class: 'primary',
                click: function () {
                    //once this one is hidden
                    setTimeout(function () {
                        openPositionModal(link);
                    }, 0);
                }
            }, {
                text: strings.close
            }],
            className: {
                content: 'center aligned content',
                actions: 'center aligned actions'
            }
        }).modal('show');
    }

    /**
     * Member position next to the member card; its map only illustrates, a
     * click opens the full one
     *
     * @param {HTMLElement}  container Member position container
     * @param {?Object}      position  Position, {lat, lng}; null when there is none
     */
    function showMemberPosition(container, position) {
        const block = container.querySelector('.maps-member-position');
        const none = container.querySelector('.maps-member-none');
        if (none !== null) {
            none.classList.toggle('displaynone', position !== null);
        }
        block.classList.toggle('displaynone', position === null);
        if (position === null) {
            return;
        }

        const latlng = L.latLng(parseFloat(position.lat), parseFloat(position.lng));
        const known = minimaps.get(container);
        if (known !== undefined) {
            known.marker.setLatLng(latlng);
            known.map.invalidateSize();
            known.map.setView(latlng, known.zoom);
            return;
        }

        const data = JSON.parse(container.dataset.minimap);
        const zoom = Math.min(10, data.tiles.maxzoom);
        const map = L.map(block.querySelector('.maps-minimap'), {
            dragging: false,
            touchZoom: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            zoomControl: false
        }).setView(latlng, zoom);

        const options = {
            maxZoom: data.tiles.maxzoom,
            attribution: data.tiles.attribution
        };
        if (data.tiles.subdomains) {
            options.subdomains = data.tiles.subdomains;
        }
        L.tileLayer(data.tiles.url, options).addTo(map);
        const marker = L.marker(latlng, {
            icon: L.icon($.extend({iconUrl: data.icon}, ICON_OPTIONS)),
            interactive: false,
            keyboard: false
        }).addTo(map);

        map.on('click', function () {
            block.querySelector('a.maps-localize').click();
        });
        minimaps.set(container, {map: map, marker: marker, zoom: zoom});
    }

    /** where sample positions of the settings preview lie, relative to the step */
    const PREVIEW_SAMPLES = [
        [0.13, 0.42], [-0.37, 0.21], [0.71, -0.55], [-0.62, -0.8],
        [0.05, -0.18], [0.9, 0.66], [-0.85, 0.35], [0.33, 0.95]
    ];

    /**
     * Snap a coordinate to the grid, as the server does
     *
     * @param {number} value Coordinate
     * @param {number} step  Grid step, in degrees
     */
    function snap(value, step) {
        //half away from zero, as PHP round() does
        return Math.sign(value) * Math.round(Math.abs(value) / step) * step;
    }

    /**
     * Settings preview: background and precision, as the form currently sets them
     *
     * @param {HTMLElement} elt Preview container
     */
    function showPreview(elt) {
        const data = JSON.parse(document.getElementById('maps-preview-config').textContent);
        const form = $(elt.dataset.form);
        const map = L.map(elt, {gestureHandling: true}).setView([data.center.lat, data.center.lng], data.center.zoom);
        const samples = L.layerGroup().addTo(map);
        const icon = L.icon($.extend({iconUrl: data.icon}, ICON_OPTIONS));
        let background = null;
        let tiles_maxzoom = 20;
        let pending = 0;

        //background the form describes
        const currentTiles = function () {
            const provider = form.find('#pref_maps_tiles_provider').val();
            if (provider !== data.custom) {
                return $.extend({fallback: data.fallback}, data.presets[provider]);
            }
            const maxzoom = parseInt(form.find('#pref_maps_tiles_maxzoom').val(), 10);
            return {
                vector: form.find('#pref_maps_tiles_vector').is(':checked'),
                url: String(form.find('#pref_maps_tiles_url').val()).trim(),
                attribution: String(form.find('#pref_maps_tiles_attribution').val()),
                maxzoom: isNaN(maxzoom) ? 18 : maxzoom,
                subdomains: String(form.find('#pref_maps_tiles_subdomains').val()).trim(),
                fallback: data.fallback
            };
        };

        //zoom stops where the background, or the precision, does
        const applyMaxZoom = function () {
            const precision = data.precisions[form.find('#pref_maps_public_precision').val()];
            const maxzoom = precision && precision.maxzoom !== null
                ? Math.min(tiles_maxzoom, precision.maxzoom)
                : tiles_maxzoom;
            map.setMaxZoom(maxzoom);
            if (map.getZoom() > maxzoom) {
                map.setZoom(maxzoom);
            }
        };

        const showTiles = function () {
            const tiles = currentTiles();
            //only the last change counts, earlier ones may still load their bundle
            const call = ++pending;
            if (background !== null) {
                map.removeLayer(background);
                background = null;
            }
            if (tiles.url === '') {
                return;
            }
            loadVectorSupport(tiles, data.gl_script, function () {
                if (call !== pending) {
                    return;
                }
                const added = addTiles(map, tiles);
                background = added.layer;
                tiles_maxzoom = added.maxzoom;
                applyMaxZoom();
            });
        };

        //center the form sets, if it is a valid one
        const currentCenter = function () {
            const lat = parseFloat(form.find('#pref_maps_center_latitude').val());
            const lng = parseFloat(form.find('#pref_maps_center_longitude').val());
            if (isNaN(lat) || isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
                return data.default_center;
            }
            return {lat: lat, lng: lng};
        };

        //sample positions, where they are stored and where the public map shows them
        const showPrecision = function () {
            const center = currentCenter();
            const precision = data.precisions[form.find('#pref_maps_public_precision').val()];
            const step = precision ? precision.step : null;
            const spread = step === null ? 0.005 : step * 1.5;
            samples.clearLayers();
            const bounds = L.latLngBounds([]);
            PREVIEW_SAMPLES.forEach(function (offset) {
                const stored = L.latLng(center.lat + offset[0] * spread, center.lng + offset[1] * spread);
                bounds.extend(stored);
                if (step === null) {
                    L.marker(stored, {icon: icon, interactive: false, keyboard: false}).addTo(samples);
                    return;
                }
                const shown = L.latLng(snap(stored.lat, step), snap(stored.lng, step));
                const cell = L.latLngBounds(
                    [shown.lat - step / 2, shown.lng - step / 2],
                    [shown.lat + step / 2, shown.lng + step / 2]
                );
                bounds.extend(cell);
                L.rectangle(cell, {weight: 2, color: '#333', fillOpacity: 0.1, interactive: false}).addTo(samples);
                L.polyline([stored, shown], {weight: 2, color: '#333', dashArray: '4', interactive: false}).addTo(samples);
                L.circleMarker(stored, {radius: 4, weight: 1, color: '#555', fillOpacity: 0.8, interactive: false}).addTo(samples);
                L.marker(shown, {icon: icon, interactive: false, keyboard: false}).addTo(samples);
            });
            applyMaxZoom();
            map.fitBounds(bounds, {padding: [30, 30]});
        };

        let typing = null;
        form.on('change', '#pref_maps_tiles_provider, #pref_maps_tiles_vector', showTiles);
        //wait for the end of typing before loading anything
        form.on('input', '#maps_custom_tiles input', function () {
            clearTimeout(typing);
            typing = setTimeout(showTiles, 600);
        });
        form.on('change', '#pref_maps_public_precision', showPrecision);
        let moving = null;
        form.on('input', '#pref_maps_center_latitude, #pref_maps_center_longitude', function () {
            clearTimeout(moving);
            moving = setTimeout(showPrecision, 600);
        });

        //towns maps may be centered on
        const towns = form.find('#maps_center_towns');
        const setCenter = function (lat, lng) {
            form.find('#pref_maps_center_latitude').val(lat);
            form.find('#pref_maps_center_longitude').val(lng);
            showPrecision();
        };
        //Enter searches, instead of saving the form
        form.on('keydown', '#maps_center_town, #maps_center_country', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                form.find('#maps_center_search').trigger('click');
            }
        });
        let search = 0;
        form.on('click', '#maps_center_search', function () {
            const button = $(this);
            //a slower earlier search must not show its towns over this one
            const current = ++search;
            towns.empty();
            $.ajax({
                url: form.find('#maps_center').data('url'),
                data: {
                    town: form.find('#maps_center_town').val(),
                    country: form.find('#maps_center_country').val()
                },
                beforeSend: function () {
                    button.addClass('loading');
                },
                complete: function () {
                    if (current === search) {
                        button.removeClass('loading');
                    }
                },
                success: function (res) {
                    if (current !== search) {
                        return;
                    }
                    if (res.towns.length === 0) {
                        towns.append(el('p', data.strings.no_town));
                        return;
                    }
                    const list = el('div', null, 'ui list');
                    res.towns.forEach(function (town) {
                        const choice = el('button', null, 'ui labeled button');
                        choice.type = 'button';
                        choice.appendChild(el('span', town.full_name, 'ui green button'));
                        choice.appendChild(el('span', town.latitude + '/' + town.longitude, 'ui basic label'));
                        choice.addEventListener('click', function () {
                            setCenter(parseFloat(town.latitude).toFixed(6), parseFloat(town.longitude).toFixed(6));
                            towns.empty();
                        });
                        const item = el('div', null, 'item');
                        item.appendChild(choice);
                        list.appendChild(item);
                    });
                    towns.append(list);
                },
                error: function (xhr) {
                    if (current !== search) {
                        return;
                    }
                    towns.append(el(
                        'p',
                        xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : data.strings.search_error
                    ));
                }
            });
        });

        showTiles();
        showPrecision();
    }

    $(document).on('click', 'a.maps-localize', function (e) {
        //opening in a new tab or window keeps the page
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) {
            return;
        }
        e.preventDefault();
        openPositionModal(this);
    });

    $(function () {
        const source = document.getElementById('maps-config');
        if (source !== null) {
            start(source);
        }
        document.querySelectorAll('.maps-member').forEach(function (container) {
            const block = container.querySelector('.maps-member-position');
            showMemberPosition(
                container,
                block.dataset.lat === undefined ? null : {lat: block.dataset.lat, lng: block.dataset.lng}
            );
        });
        document.querySelectorAll('.maps-preview').forEach(showPreview);
    });
})(jQuery);
