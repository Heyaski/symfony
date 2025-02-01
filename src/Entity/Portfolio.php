<?php

namespace App\Entity;

use App\Repository\PortfolioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortfolioRepository::class)]
class Portfolio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'portfolios')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?float $balance = null;

    /**
     * @var Collection<int, Depositary>
     */
    #[ORM\OneToMany(targetEntity: Depositary::class, mappedBy: 'portfolio', cascade: ['persist', 'remove'])]
    private Collection $depositaries;

    public function __construct()
    {
        $this->depositaries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?User
    {
        return $this->user;
    }

    public function setUserId(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getBalance(): ?float
    {
        return $this->balance;
    }

    public function setBalance(float $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    /**
     * @return Collection<int, Depositary>
     */
    public function getDepositaries(): Collection
    {
        return $this->depositaries;
    }

    public function addDepositary(Depositary $depositary): static
    {
        if (!$this->depositaries->contains($depositary)) {
            $this->depositaries->add($depositary);
            $depositary->setPortfolio($this);
        }

        return $this;
    }

    public function addBalance(float $sum): static
    {
        assert($sum > 0);
        $this->balance += $sum;

        return $this;
    }

    public function subBalance(float $sum): static
    {
        $this->balance -= $sum;

        return $this;
    }

    public function addStock(Stock $stock, int $quantity): void
    {
        foreach ($this->depositaries as $depositary) {
            if ($depositary->getStock() === $stock) {
                $depositary->setQuantity($depositary->getQuantity() + $quantity);
                return;
            }
        }

        $depositary = new Depositary();
        $depositary->setStock($stock);
        $depositary->setQuantity($quantity);
        $depositary->setPortfolio($this);
        $this->depositaries->add($depositary);
    }

    public function removeStock(Stock $stock, int $quantity): void
    {
        foreach ($this->depositaries as $depositary) {
            if ($depositary->getStock() === $stock) {
                $newQuantity = $depositary->getQuantity() - $quantity;
                if ($newQuantity <= 0) {
                    $this->depositaries->removeElement($depositary);
                } else {
                    $depositary->setQuantity($newQuantity);
                }
                return;
            }
        }
    }

    // public function removeDepositary(Depositary $depositary): static
    // {
    //     if ($this->depositaries->removeElement($depositary)) {
    //         // set the owning side to null (unless already changed)
    //         if ($depositary->getPortfolio() === $this) {
    //             $depositary->setPortfolio(null);
    //         }
    //     }

    //     return $this;
    // }
}
