<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\BonusType;
use App\Entity\Employee;
use App\Repository\BonusConfigRepository;

class PayrollCalculatorService
{
    private const BONUS_AMOUNT_KEY = 'bonus_amount';
    private const BONUS_TYPE_KEY = 'bonus_type';

    public function __construct(private readonly BonusConfigRepository $bonusConfigRepository)
    {
    }

    public function calculateBonus(Employee $employee): array
    {
        if (!$department = $employee->getDepartment()) {
            return $this->getBonusData();
        }

        if (!$bonusConfig = $this->bonusConfigRepository->findOneBy(['department' => $department])) {
            return $this->getBonusData();
        }

        $yearsActive = $employee->getYearsOfWork();
        if ($bonusConfig->getMaxYears()) {
            $yearsActive = min($yearsActive, $bonusConfig->getMaxYears());
        }

        return $bonusConfig->isFixed()
            ? $this->getFixedBonus((float)$bonusConfig->getBonusValue(), $yearsActive)
            : $this->getPercentageBonus(
                (float)$employee->getBaseSalary(),
                (float)$bonusConfig->getBonusValue(),
                $yearsActive
            );
    }

    public function calculateTotalSalary(Employee $employee): float
    {
        $baseSalary = (float)$employee->getBaseSalary();
        $bonus = $this->calculateBonus($employee);
        $bonusAmount = (float)$bonus[self::BONUS_AMOUNT_KEY];
        $totalSalary = $baseSalary + $bonusAmount;

        return (float)number_format($totalSalary, 2, '.', '');
    }

    private function getBonusData(float $bonusAmount = 0.0, string $bonusType = 'N/A'): array
    {
        return [
            self::BONUS_AMOUNT_KEY => $bonusAmount,
            self::BONUS_TYPE_KEY => $bonusType,
        ];
    }

    private function getFixedBonus(float $bonusValue, int $yearsActive): array
    {
        $bonusAmount = $bonusValue * $yearsActive;
        $bonusAmount = (float)number_format($bonusAmount, 2, '.', '');

        return $this->getBonusData($bonusAmount, BonusType::Fixed->value);

    }

    private function getPercentageBonus(float $baseSalary, float $percentagePerYear, int $yearsActive): array
    {
        $bonusAmount = $baseSalary * ($percentagePerYear / 100) * $yearsActive;
        $bonusAmount = (float)number_format($bonusAmount, 2, '.', '');

        return $this->getBonusData($bonusAmount, BonusType::Percentage->value);
    }
}
