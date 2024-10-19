<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StockController extends AbstractController
{
    public function __construct(private readonly StockService $stockService)
    {

    }

    #[Route(path: '/hello')]
    public function index(): Response
    {
        return new Response('Hello World!');
    }

    #[Route('/hello/{name}')]
    public function getHelloName(string $name): Response
    {
        return new Response('Hello ' . $name);
    }

    #[Route(path: '/game/guess/{number}/{name}', methods: ['GET'])]
    public function guessGame(int $number, string $name): Response
    {
        $randomNumber = $this->stockService->getRandomNumber();
		$randomName = $this->stockService->getAuthorName();

		if ($randomNumber === $number && $randomName === $name) {
			return new Response('Congratulates, you guess number and name');
		}
		elseif ($randomNumber === $number) {
			return new Response("Number guess but name not");
		}
		elseif ($randomName === $name) {
			return new Response("Name guess but number not");
		}
		else {
			return new Response("Try again!");
		} 
    }
}
