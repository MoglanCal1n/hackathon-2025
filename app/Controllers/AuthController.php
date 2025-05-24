<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private AuthService $authService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        $this->logger->info('Register page requested');
        return $this->render($response, 'auth/register.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $username = trim($data['username']);
        $password = trim($data['password']);

        if (empty($username) || empty($password)) {
            $this->logger->info('Registration failed, empty username and password.');
            return $this->render($response, 'auth/register.twig',[
                'error' => 'Username and password are required'
            ]);
        }

        try {
            $this->authService->register($username, $password);
            $this->logger->info('Registration successful: username {$username}');
            return $response->withStatus(302)->withHeader('Location', '/login');

        }catch (\RuntimeException $exception){
            $this->logger->warning("Registration failed: {$exception->getMessage()}");
            return $this->render($response, 'auth/register.twig',[
               'error' => $exception->getMessage()
            ]);
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {

        $data = (array) $request->getParsedBody();
        $username = trim($data['username']);
        $password = trim($data['password']);

        if (empty($username) || empty($password)) {
            $this->logger->info('Login failed, empty username and password.');
            return $this->render($response, 'auth/login.twig',[
               'error' => 'Username and password are required'
            ]);
        }

        if ($this->authService->attempt($username, $password)) {
            $this->logger->info('Login successful');
            return $response->withStatus(302)->withHeader('Location', '/');
        } else {
            $this->logger->info('Login failed: username {$username} and password {$password}');
            return $this->render($response, 'auth/login.twig',[
               'error' => "Invalid username or password"
            ]);
        }
    }

    public function logout(Request $request, Response $response): Response
    {

        $this->logger->info("User logged out: " . ($_SESSION['username']));

        session_unset();
        session_destroy();

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
