<?php

namespace App\Service;

class StockService {

	public function getAuthorName(): string {
		$names = ['Степан', 'Сергей', 'Иван', "Олег"];
		return $names[array_rand($names)];
	}

	public function getRandomNumber(): int {
		return rand(1, 10);
	}

}