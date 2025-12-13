<?php

namespace App\Entity;

use App\Repository\DepartmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepartmentRepository::class)]
#[ORM\Table(name: 'department')]
class Department
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Employee>
     */
    #[ORM\OneToMany(targetEntity: Employee::class, mappedBy: 'department', orphanRemoval: true)]
    private Collection $employees;

    /**
     * @var Collection<int, BonusConfig>
     */
    #[ORM\OneToMany(targetEntity: BonusConfig::class, mappedBy: 'department')]
    private Collection $bonusConfigs;

    public function __construct()
    {
        $this->employees = new ArrayCollection();
        $this->bonusConfigs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Employee>
     */
    public function getEmployees(): Collection
    {
        return $this->employees;
    }

    public function addEmployee(Employee $employee): static
    {
        if (!$this->employees->contains($employee)) {
            $this->employees->add($employee);
            $employee->setDepartment($this);
        }

        return $this;
    }

    public function removeEmployee(Employee $employee): static
    {
        if ($this->employees->removeElement($employee)) {
            // set the owning side to null (unless already changed)
            if ($employee->getDepartment() === $this) {
                $employee->setDepartment(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BonusConfig>
     */
    public function getBonusConfigs(): Collection
    {
        return $this->bonusConfigs;
    }

    public function addBonusConfig(BonusConfig $bonusConfig): static
    {
        if (!$this->bonusConfigs->contains($bonusConfig)) {
            $this->bonusConfigs->add($bonusConfig);
            $bonusConfig->setDepartment($this);
        }

        return $this;
    }

    public function removeBonusConfig(BonusConfig $bonusConfig): static
    {
        if ($this->bonusConfigs->removeElement($bonusConfig)) {
            // set the owning side to null (unless already changed)
            if ($bonusConfig->getDepartment() === $this) {
                $bonusConfig->setDepartment(null);
            }
        }

        return $this;
    }
}
