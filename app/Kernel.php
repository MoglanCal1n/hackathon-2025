<?php

declare(strict_types=1);

namespace App;

use App\Domain\Repository\ExpenseRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\CategoryBudgetConfig;
use App\Infrastructure\Persistence\PdoExpenseRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PDO;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

use function DI\autowire;
use function DI\factory;

class Kernel
{
    public static function createApp(): App
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $builder->addDefinitions([
            LoggerInterface::class => function () {
                $logger = new Logger('app');
                $logger->pushHandler(new StreamHandler(__DIR__.'/../var/app.log', Level::Debug));

                return $logger;
            },

            CategoryBudgetConfig::class => function () {
                $json = $_ENV['CATEGORY_BUDGETS_JSON'] ?? null;

                if (!$json) {
                    throw new \RuntimeException("CATEGORY_BUDGETS_JSON is not set in .env");
                }

                return new CategoryBudgetConfig($json);
            },

            Twig::class => function () {
                return Twig::create(__DIR__.'/../templates', ['cache' => false]);
            },

            PDO::class => factory(function () {
                static $pdo = null;
                if ($pdo === null) {
                    $pdo = new PDO('sqlite:'.$_ENV['DB_PATH']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                }

                return $pdo;
            }),

            UserRepositoryInterface::class => autowire(PdoUserRepository::class),
            ExpenseRepositoryInterface::class => autowire(PdoExpenseRepository::class),
        ]);

        $container = $builder->build();

        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->add(TwigMiddleware::createFromContainer($app, Twig::class));
        (require __DIR__.'/../config/settings.php')($app);
        (require __DIR__.'/../config/routes.php')($app);

        $app->add(function ($request, $handler) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            return $handler->handle($request);
        });

        $loggedInUserId = $_SESSION['user_id'] ?? null;
        $twig = $container->get(Twig::class);
        $twig->getEnvironment()->addGlobal('currentUserId', $loggedInUserId);

        return $app;
    }
}
