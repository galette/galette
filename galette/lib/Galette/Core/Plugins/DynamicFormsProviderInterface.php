<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

use Galette\Core\Login;
use Slim\Routing\RouteParser;

/**
 * Dynamic forms provider interface
 *
 * A plugin implementing this gets dynamic fields on its own objects: the forms
 * it declares get a tab on the dynamic fields configuration page, and their
 * fields are stored and displayed as the core ones are.
 *
 * Declared classes use the `Galette\Features\Dynamics` trait; the plugin calls
 * its `dynamicsCheck()`, `dynamicsStore()`, `dynamicsFiles()` and
 * `dynamicsRemove()` methods from its own check, store and remove code, and
 * includes `components/dynamic_fields.html.twig` and
 * `elements/display_dynamic_fields.html.twig` in its templates.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface DynamicFormsProviderInterface
{
    /**
     * Get the forms the plugin declares
     *
     * Keys are form names: at most 50 lowercase letters, digits or
     * underscores, starting with a letter, and unused by the core and other
     * plugins; values are the class of the objects the form belongs to.
     * A form that does not match is dropped and reported.
     *
     * @return array<string, class-string>
     */
    public function getDynamicForms(): array;

    /**
     * Get the label of a declared form
     *
     * @param string $form Form name
     */
    public function getDynamicFormLabel(string $form): string;

    /**
     * Get an object of a declared form, if the user can see it
     *
     * Used to send the files of its dynamic fields; return null when the
     * object does not exist or when the user cannot see it.
     *
     * @param string $form  Form name
     * @param int    $id    Object identifier
     * @param Login  $login Current user
     */
    public function getDynamicFormObject(string $form, int $id, Login $login): ?object;

    /**
     * Get the page to go back to when a file of a declared form cannot be sent
     *
     * The object may not exist, or the user may not see it: the list of the
     * plugin objects is a good choice.
     *
     * @param string      $form        Form name
     * @param int         $id          Object identifier
     * @param RouteParser $routeparser Route parser
     */
    public function getDynamicFormReturnUrl(string $form, int $id, RouteParser $routeparser): string;
}
