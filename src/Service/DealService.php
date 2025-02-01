<?php

namespace App\Service;

use App\Entity\Application;
use App\Enums\ActionEnum;
use App\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;

class DealService
{
	private $applicationRepository;
	private $entityManager;

	public function __construct(ApplicationRepository $applicationRepository, EntityManagerInterface $entityManager)
	{
		$this->applicationRepository = $applicationRepository;
		$this->entityManager = $entityManager;
	}

	public function findAppropriateApplication(Application $application): ?Application
	{
		$criteria = [
			'stock' => $application->getStock(),
			'quantity' => $application->getQuantity(),
			'price' => $application->getPrice(),
			'action' => $application->getAction()->getOpposite(),
		];

		$applications = $this->applicationRepository->findBy($criteria);

		foreach ($applications as $app) {
			if ($app->getUser() !== $application->getUser()) {
				return $app;
			}
		}

		return null;
	}

	public function executeDeal(Application $firstApplication, Application $secondApplication): void
	{
		// Определяем, кто покупатель, а кто продавец
		$buyApplication = $firstApplication->getAction() === ActionEnum::BUY ? $firstApplication : $secondApplication;
		$sellApplication = $firstApplication->getAction() === ActionEnum::SELL ? $firstApplication : $secondApplication;

		// Определяем какая заявка является оригинальной (из базы данных)
		$originalApplication = $this->entityManager->find(Application::class, $secondApplication->getId());
		if (!$originalApplication) {
			throw new \Exception("Original application not found");
		}

		$buyer = $buyApplication->getUser();
		$seller = $sellApplication->getUser();

		// Получаем портфели для покупателя и продавца
		$buyerPortfolio = $buyApplication->getPortfolio() ?? $buyer->getPortfolios()->first();
		$sellerPortfolio = $sellApplication->getPortfolio() ?? $seller->getPortfolios()->first();

		if (!$buyerPortfolio || !$sellerPortfolio) {
			throw new \Exception("User portfolios not found");
		}

		// Устанавливаем портфели для заявок, если они не установлены
		if (!$buyApplication->getPortfolio()) {
			$buyApplication->setPortfolio($buyerPortfolio);
		}
		if (!$sellApplication->getPortfolio()) {
			$sellApplication->setPortfolio($sellerPortfolio);
		}

		// Вычисляем количество и сумму для текущей сделки
		$tradeQuantity = min($buyApplication->getQuantity(), $sellApplication->getQuantity());
		$tradeAmount = $tradeQuantity * $originalApplication->getPrice();

		// Проверяем наличие средств и акций с учетом частичного исполнения
		if ($buyerPortfolio->getBalance() < $tradeAmount) {
			throw new \Exception("Insufficient funds");
		}

		$sellerStock = null;
		foreach ($sellerPortfolio->getDepositaries() as $depositary) {
			if ($depositary->getStock() === $sellApplication->getStock()) {
				if ($depositary->getQuantity() < $tradeQuantity) {
					throw new \Exception("Insufficient stocks");
				}
				$sellerStock = $depositary;
				break;
			}
		}

		$this->entityManager->beginTransaction();
		try {
			// Переводим деньги за фактическое количество
			$buyerPortfolio->subBalance($tradeAmount);
			$sellerPortfolio->addBalance($tradeAmount);

			// Передаем акции в фактическом количестве
			$buyerPortfolio->addStock($buyApplication->getStock(), $tradeQuantity);
			$sellerPortfolio->removeStock($sellApplication->getStock(), $tradeQuantity);

			$this->entityManager->persist($buyerPortfolio);
			$this->entityManager->persist($sellerPortfolio);

			// Обновляем количество в оригинальной заявке
			$remainingQuantity = $originalApplication->getQuantity() - $tradeQuantity;
			if ($remainingQuantity > 0) {
				$originalApplication->setQuantity($remainingQuantity);
				$this->entityManager->persist($originalApplication);
			} else {
				$this->entityManager->remove($originalApplication);
			}

			$this->entityManager->flush();
			$this->entityManager->commit();
		} catch (\Exception $e) {
			$this->entityManager->rollback();
			throw $e;
		}
	}
}