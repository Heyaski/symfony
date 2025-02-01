<?php

namespace App\Controller;

use App\Entity\Application;
use App\Entity\Stock;
use App\Enums\ActionEnum;
use App\Form\ApplicationType;
use App\Form\DTO\CreateApplicationRequest;
use App\Repository\StockRepository;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Service\DealService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class GlassController extends AbstractController
{
    public function __construct(
        private readonly StockRepository $stockRepository,
        private readonly UserRepository $userRepository,
        private readonly ApplicationRepository $applicationRepository,
        private readonly DealService $dealService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/glass/stock/{stockId}', name: 'app_stock_glass', methods: ['GET'])]
    public function getStockGlass(int $stockId): Response
    {
        $stock = $this->stockRepository->findById($stockId);
        if ($stock === null) {
            throw $this->createNotFoundException("Stock not found");
        }

        // Подготавливаем данные для шаблона
        $applications = array_map(function ($app) {
            return [
                'id' => $app->getId(),
                'quantity' => $app->getQuantity(),
                'price' => $app->getPrice(),
                'stockId' => $app->getStock()->getId(),
                'action' => $app->getAction()->value
            ];
        }, $stock->getApplications()->toArray());

        return $this->render('glass/stock_glass_index.html.twig', [
            'stock' => $stock,
            'BUY' => ActionEnum::BUY,
            'SELL' => ActionEnum::SELL,
            'applications' => $applications
        ]);
    }

    #[Route('/glass/stock/{stockId}/application', name: 'app_stock_glass_create_application', methods: ['POST'])]
    public function createApplication(int $stockId, Request $request): Response
    {
        // Используем сервис логирования
        $this->logger->debug('Received create application request', [
            'stockId' => $stockId,
            'request_data' => $request->request->all()
        ]);

        $stock = $this->stockRepository->find($stockId);
        if (!$stock) {
            return new Response("Stock not found", Response::HTTP_NOT_FOUND);
        }

        // Проверяем все необходимые параметры
        $requiredParams = ['user_id', 'quantity', 'price', 'action', 'portfolio_id'];
        foreach ($requiredParams as $param) {
            if (!$request->request->has($param)) {
                return new Response("Missing required parameter: $param", Response::HTTP_BAD_REQUEST);
            }
        }

        $userId = $request->request->get('user_id');
        if (!$userId) {
            return new Response("User ID is missing", Response::HTTP_BAD_REQUEST);
        }

        $quantity = $request->request->get('quantity');
        $price = $request->request->get('price');
        $action = ActionEnum::from(strtolower($request->request->get('action')));
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return new Response("User not found", Response::HTTP_NOT_FOUND);
        }

        // Изменяем получение портфеля
        $portfolioId = $request->request->get('portfolio_id');
        $portfolio = null;
        foreach ($user->getPortfolios() as $p) {
            if ($p->getId() == $portfolioId) {
                $portfolio = $p;
                break;
            }
        }

        if (!$portfolio) {
            $this->addFlash('error', 'Portfolio not found');
            return $this->redirectToRoute('app_profile');
        }

        if ($action === ActionEnum::BUY) {
            $totalCost = $quantity * $price;
            if ($portfolio->getBalance() < $totalCost) {
                $this->addFlash('error', sprintf(
                    'Недостаточно средств для покупки. Требуется: %s₽, доступно: %s₽',
                    $totalCost,
                    $portfolio->getBalance()
                ));
                return $this->redirectToRoute('app_profile');
            }
        } elseif ($action === ActionEnum::SELL) {
            $availableQuantity = $portfolio->getAvailableStockQuantity($stock);
            if ($quantity > $availableQuantity) {
                $this->addFlash('error', sprintf(
                    'Недостаточно доступных акций для продажи. Требуется: %d шт., доступно: %d шт. (%d шт. заморожено в других заявках)',
                    $quantity,
                    $availableQuantity,
                    $portfolio->getFrozenStockQuantity($stock)
                ));
                return $this->redirectToRoute('app_profile');
            }
        }

        $application = new Application();
        $application->setStock($stock);
        $application->setQuantity($quantity);
        $application->setAction($action);
        $application->setPrice($price);
        $application->setUser($user);
        $application->setPortfolio($portfolio); // Добавляем установку портфеля

        try {
            $appropriateApplication = $this->dealService->findAppropriateApplication($application);

            if ($appropriateApplication) {
                try {
                    $this->dealService->executeDeal($application, $appropriateApplication);
                    $this->addFlash('success', 'Сделка успешно выполнена');
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            } else {
                $this->applicationRepository->saveApplication($application);
                $this->addFlash('success', 'Заявка успешно создана');
            }

            return $this->redirectToRoute('app_profile');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_profile');
        }
    }

    #[Route('/glass/stock/{stockId}/application/{applicationId}', name: 'app_stock_glass_update_application', methods: ['POST', 'PATCH'])]
    public function updateApplication(int $stockId, int $applicationId, Request $request): Response
    {
        $application = $this->applicationRepository->find($applicationId);
        if (!$application) {
            return new Response("Application not found", Response::HTTP_NOT_FOUND);
        }

        // Получаем данные и проверяем их наличие
        $quantity = $request->request->get('quantity');
        $price = $request->request->get('price');

        // Добавим отладочную информацию
        if ($quantity === null || $price === null) {
            return new Response(
                sprintf(
                    "Quantity and price are required. Received: quantity=%s, price=%s",
                    var_export($quantity, true),
                    var_export($price, true)
                ),
                Response::HTTP_BAD_REQUEST
            );
        }

        $application->setQuantity((int) $quantity);
        $application->setPrice((float) $price);

        try {
            $appropriateApplication = $this->dealService->findAppropriateApplication($application);

            if ($appropriateApplication) {
                try {
                    $this->dealService->executeDeal($application, $appropriateApplication);
                    $this->addFlash('success', 'Сделка успешно выполнена');
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            } else {
                $this->applicationRepository->saveApplication($application);
                $this->addFlash('success', 'Заявка успешно создана');
            }

            return $this->redirectToRoute('app_profile');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_profile');
        }
    }

    #[Route('/glass/stock/{stockId}/application/{applicationId}', name: 'app_stock_glass_delete_application', methods: ['DELETE'])]
    public function deleteApplication(int $stockId, int $applicationId): Response
    {
        $application = $this->applicationRepository->find($applicationId);
        if (!$application) {
            return new Response("Application not found", Response::HTTP_NOT_FOUND);
        }

        try {
            $this->applicationRepository->remove($application, true);
            return $this->redirectToRoute('app_profile');
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/trade', name: 'app_execute_trade', methods: ['POST'])]
    public function executeTrade(Request $request): Response
    {
        $applicationId = $request->request->get('applicationId');
        $quantity = (int) $request->request->get('quantity');
        $tradeType = $request->request->get('tradeType');
        $portfolioId = $request->request->get('portfolioId'); // Добавляем получение ID портфеля

        $application = $this->applicationRepository->find($applicationId);
        if (!$application) {
            return new Response('Заявка не найдена', Response::HTTP_NOT_FOUND);
        }

        // Проверяем, что пользователь не пытается торговать с самим собой
        if ($application->getUser() === $this->getUser()) {
            return new Response('Нельзя торговать с собственными заявками', Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->getUser();
            // Находим нужный портфель по ID
            $portfolio = null;
            foreach ($user->getPortfolios() as $p) {
                if ($p->getId() == $portfolioId) {
                    $portfolio = $p;
                    break;
                }
            }

            if (!$portfolio) {
                return new Response('Портфель не найден', Response::HTTP_BAD_REQUEST);
            }

            if ($tradeType === 'buy') {
                $totalCost = $quantity * $application->getPrice(); // Используем только запрошенное количество
                if ($portfolio->getBalance() < $totalCost) {
                    return new Response('Недостаточно средств для покупки', Response::HTTP_BAD_REQUEST);
                }
            } else {
                $availableQuantity = $portfolio->getAvailableStockQuantity($application->getStock());
                if ($quantity > $availableQuantity) {
                    return new Response(sprintf(
                        'Недостаточно доступных акций для продажи. Доступно: %d шт. (%d шт. заморожено в других заявках)',
                        $availableQuantity,
                        $portfolio->getFrozenStockQuantity($application->getStock())
                    ), Response::HTTP_BAD_REQUEST);
                }
            }

            // Создаем новую заявку для частичного исполнения
            $newApplication = new Application();
            $newApplication->setStock($application->getStock());
            $newApplication->setQuantity($quantity); // Используем только запрошенное количество
            $newApplication->setPrice($application->getPrice());
            $newApplication->setAction($tradeType === 'buy' ? ActionEnum::BUY : ActionEnum::SELL);
            $newApplication->setUser($user);
            $newApplication->setPortfolio($portfolio); // Устанавливаем портфель для заявки

            // Выполняем сделку
            $this->dealService->executeDeal($newApplication, $application);

            return new Response('Сделка успешно выполнена', Response::HTTP_OK);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
