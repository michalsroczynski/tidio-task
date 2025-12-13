<?php

namespace App\Entity;

use App\Repository\BonusConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BonusConfigRepository::class)]
#[ORM\Table(name: 'bonus_config')]
class BonusConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bonusConfigs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Department $department = null;

    #[ORM\Column(length: 255)]
    private ?string $bonusType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $bonusValue = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxYears = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function getBonusType(): ?string
    {
        return $this->bonusType;
    }

    public function setBonusType(string $bonusType): static
    {
        $this->bonusType = $bonusType;

        return $this;
    }

    public function getBonusValue(): ?string
    {
        return $this->bonusValue;
    }

    public function setBonusValue(string $bonusValue): static
    {
        $this->bonusValue = $bonusValue;

        return $this;
    }

    public function getMaxYears(): ?int
    {
        return $this->maxYears;
    }

    public function setMaxYears(?int $maxYears): static
    {
        $this->maxYears = $maxYears;

        return $this;
    }
}
