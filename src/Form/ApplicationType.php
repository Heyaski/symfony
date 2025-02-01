<?php

namespace App\Form;

use App\Enums\ActionEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use \Symfony\Component\Form\FormBuilderInterface;
use \Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Application;

class ApplicationType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('price', NumberType::class)
			->add('quantity', IntegerType::class)
			->add('user_id', IntegerType::class)
			->add('stock', IntegerType::class)
			->add('action', EnumType::class, [
				'class' => ActionEnum::class
			])
		;
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults([
			'data_class' => Application::class
		]);
	}
}