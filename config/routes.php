<?php

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ExpenseController;
use Slim\App;
use Slim\Psr7\Response;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app) {
    $app->get('/register', [AuthController::class, 'showRegister']);
    $app->post('/register', [AuthController::class, 'register']);
    $app->get('/login', [AuthController::class, 'showLogin']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->get('/logout', [AuthController::class, 'logout']);

    $app->group('', function (RouteCollectorProxy $firewalled) {
        $firewalled->get('/', [DashboardController::class, 'index']);
        $firewalled->group('/expenses', function (RouteCollectorProxy $expense) {
            $expense->get('', [ExpenseController::class, 'index']);
            $expense->get('/create', [ExpenseController::class, 'create']);
            $expense->post('', [ExpenseController::class, 'store']);
            $expense->get('/{id}/edit', [ExpenseController::class, 'edit']);
            $expense->post('/import', [ExpenseController::class, 'import']);
            $expense->post('/{id}', [ExpenseController::class, 'update']);
            $expense->post('/{id}/delete', [ExpenseController::class, 'destroy']);
        });
    })
        // The middleware below ensures that only a logged-in user has access to the firewalled routes
        ->add(function ($request, $handler) {
            if (!isset($_SESSION['user_id'])) {
                return (new Response())->withHeader('Location', '/login')->withStatus(302);
            }

            return $handler->handle($request);
        });
};