<?php

namespace App\Form\DTO;
use App\Enums\ActionEnum;

class CreateApplicationRequest
{
	private ?int $userId = null;
	private ?float $price = null;
	private ?int $quantity = null;
	private ?ActionEnum $actionEnum = null;
}