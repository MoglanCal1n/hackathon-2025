<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

abstract class BaseController
{
    public function __construct(
        protected Twig $view,
        protected ?UserRepositoryInterface $userRepository = null // Optional DI for flexibility
    ) {}

    protected function render(Response $response, string $template, array $data = []): Response
    {
        $data['currentUserId'] = $_SESSION['user_id'] ?? null;
        $data['currentUserName'] = $_SESSION['username'] ?? 'User';

        return $this->view->render($response, $template, $data);
    }
}
