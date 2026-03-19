i<?php

use Firebase\JWT\JWT;
use Galette\Api\ApiResponseFormatter;
use Galette\Api\Middleware\JwtMiddleware;
use Galette\Api\Middleware\RoleMiddleware;
use Slim\Factory\AppFactory;
use DI\Container;
use Galette\Api\Actions\MemberActionGet;

$secretKey = "VOTRE_CLE_TRES_LONGUE_ET_SECURISEE"; //FIXME: obviously
$container = new Container();

// Configuration du conteneur pour injecter les services Galette
$container->set('MemberService', function() {
    return new \Galette\Repository\MemberRepository(); // Exemple
});

AppFactory::setContainer($container);
$app = AppFactory::create();

// Définition de la route
$app->get('/api/members/{id}', MemberActionGet::class);

use Galette\Api\Actions\MemberActionCreate;
use Galette\Api\Middleware\ValidationMiddleware;

// Définition des règles pour la création d'un membre
$memberRules = [
        'nom_adh' => 'required',
        'prenom_adh' => 'required',
        'email_adh' => 'required'
];

$jwtAuth = new JwtMiddleware($secretKey);
// On applique le middleware uniquement sur cette route POST
$app->post('/api/members', MemberActionCreate::class)
        ->add(new ValidationMiddleware($memberRules))
        ->add($jwtAuth);

// Appliquer le middleware à un groupe de routes (toute l'API sauf le login)
$app->group('/api', function ($group) {
    $group->get('/members', \Galette\Api\Actions\MembersActionList::class);
    $group->get('/members/{id}', \Galette\Api\Actions\MemberActionGet::class);
})->add($jwtAuth);

// Route de login (non protégée par le middleware JWT)
$app->post('/api/login', \Galette\Api\Actions\LoginAction::class);

// Groupe de routes pour l'administration
$app->group('/api/admin', function ($group) {
    $group->delete('/members/{id}', \Galette\Api\Actions\DeleteMemberAction::class);
    $group->post('/settings', \Galette\Api\Actions\UpdateSettingsAction::class);
})
    ->add(new RoleMiddleware('admin')) // Réservé aux admins
    ->add($jwtAuth);                   // Et nécessite un JWT valide

// Groupe de routes pour les membres classiques
$app->get('/api/me', \Galette\Api\Actions\GetMyProfileAction::class)
        ->add($jwtAuth);

$app->post('/api/refresh', function ($request, $response) use ($secretKey) {
    $data = $request->getParsedBody();
    $tokenFromClient = $data['refresh_token'] ?? '';

    // Vérification en base de données via le repository de Galette
    $tokenInfo = $this->tokenRepository->findValid($tokenFromClient);

    if (!$tokenInfo) {
        return $response->withStatus(401)->withJson(['error' => 'Refresh token invalide ou expiré']);
    }

    // Génération d'un nouvel Access Token
    $newPayload = [
            'iat' => time(),
            'exp' => time() + 900,
            'sub' => $tokenInfo['user_id'],
            'role' => $tokenInfo['role']
    ];

    $newAccessToken = JWT::encode($newPayload, $secretKey, 'HS256');

    return $response->withJson([
            'access_token' => $newAccessToken,
            'expires_in' => 900
    ]);
});

$app->get('/api/member/status', GetStatusAction::class)
        ->add(new ScopeMiddleware('membership:check'));

$displayErrorDetails = false; // Mettre à true uniquement en local (dev)
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

// On remplace le rendu par défaut par notre formateur JSON
$errorMiddleware->setDefaultErrorHandler(new ApiResponseFormatter());

$app->run();
