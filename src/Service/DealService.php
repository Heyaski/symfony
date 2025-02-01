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
		// Определяем, кто покупатель, а кто продавец по действию в заявке
		$buyApplication = $firstApplication->getAction() === ActionEnum::BUY ? $firstApplication : $secondApplication;
		$sellApplication = $firstApplication->getAction() === ActionEnum::SELL ? $firstApplication : $secondApplication;

		$buyer = $buyApplication->getUser();
		$seller = $sellApplication->getUser();

		$buyerPortfolio = $buyer->getPortfolios()->first();
		$sellerPortfolio = $seller->getPortfolios()->first();

		if (!$buyerPortfolio || !$sellerPortfolio) {
			throw new \Exception("User portfolios not found");
		}

		// достаточно ли средств у покупателя
		if ($buyerPortfolio->getBalance() < $buyApplication->getTotal()) {
			throw new \Exception("Insufficient funds: required {$buyApplication->getTotal()}, available {$buyerPortfolio->getBalance()}");
		}

		// достаточно ли акций у продавца
		$sellerStock = null;
		foreach ($sellerPortfolio->getDepositaries() as $depositary) {
			if ($depositary->getStock() === $sellApplication->getStock()) {
				if ($depositary->getQuantity() < $sellApplication->getQuantity()) {
					throw new \Exception("Insufficient stocks: required {$sellApplication->getQuantity()}, available {$depositary->getQuantity()}");
				}
				$sellerStock = $depositary;
				break;
			}
		}
		if (!$sellerStock && $sellApplication->getQuantity() > 0) {
			throw new \Exception("Seller doesn't have any stocks");
		}

		$buyerPortfolio->subBalance($buyApplication->getTotal());
		$buyerPortfolio->addStock($buyApplication->getStock(), $buyApplication->getQuantity());

		$sellerPortfolio->addBalance($sellApplication->getTotal());
		$sellerPortfolio->removeStock($sellApplication->getStock(), $sellApplication->getQuantity());
		$this->entityManager->remove($buyApplication);
		$this->entityManager->remove($sellApplication);
		$this->entityManager->flush();
	}
}