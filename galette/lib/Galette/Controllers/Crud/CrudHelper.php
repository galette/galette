<?php

namespace Galette\Controllers\Crud;

use Slim\App;
use Galette\Middleware\Authenticate;

/**
 * CrudHelper
 *
 * @author Manuel <manuelh78dev@ik.me>
 */
class CrudHelper
{
    /**
     * Ajouter des routes automatiquement pour un object Entity via le nom de son controller
     *
     * @param Slim\App     $app                 Slim application
     * @param string       $controllerClassName controller class name AController::class
     * @param Authenticate $authenticate        Middleware for user authentification
     *
     * @return void
     */
    public static function addRoutesBasicEntityCRUD(App $app, string $controllerClassName, Authenticate $authenticate): void
    {
        //Galette\Controllers\Crud\TitlesController -> Title
        $entity = str_replace(['Controller', '\\'], ['', '/'], $controllerClassName);
        $entity = basename($entity);
        $entity = substr($entity, 0, -1);

        $baseName = lcfirst($entity);
        $basePath = strtolower($entity);

        $app->get(
            "/{$basePath}s",
            [$controllerClassName, 'list']
        )->setName("{$baseName}s")->add($authenticate);

        $app->post(
            "/{$basePath}",
            [$controllerClassName, 'doAdd']
        )->setName("add{$entity}"/*"{$basePath}"*/)->add($authenticate);

        $app->get(
            "/{$basePath}/remove/{id:\d+}",
            [$controllerClassName, 'confirmDelete']
        )->setName("remove{$entity}")->add($authenticate);

        $app->post(
            "/{$basePath}/remove/{id:\d+}",
            [$controllerClassName, 'delete']
        )->setName("doRemove{$entity}")->add($authenticate);

        $app->get(
            "/{$basePath}/edit/{id:\d+}",
            [$controllerClassName, 'edit']
        )->setname("edit{$entity}")->add($authenticate);

        $app->post(
            "/{$basePath}/edit/{id:\d+}",
            [$controllerClassName, 'doEdit']
        )->setname("edit{$entity}")->add($authenticate);
    }
}


/*$app->get(
    '/titles',
    [Crud\TitlesController::class, 'list']
)->setName('titles')->add($authenticate);

$app->post(
    '/title',
    [Crud\TitlesController::class, 'doAdd']
)->setName('titles')->add($authenticate);

$app->get(
    '/titles/remove/{id:\d+}',
    [Crud\TitlesController::class, 'confirmDelete']
)->setName('removeTitle')->add($authenticate);

$app->post(
    '/titles/remove/{id:\d+}',
    [Crud\TitlesController::class, 'delete']
)->setName('doRemoveTitle')->add($authenticate);

$app->get(
    '/titles/edit/{id:\d+}',
    [Crud\TitlesController::class, 'edit']
)->setname('editTitle')->add($authenticate);

$app->post(
    '/titles/edit/{id:\d+}',
    [Crud\TitlesController::class, 'doEdit']
)->setname('editTitle')->add($authenticate);
*/
