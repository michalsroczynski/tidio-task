<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Config\BonusType;
use App\Entity\BonusConfig;
use App\Entity\Department;
use App\Entity\Employee;
use App\Repository\BonusConfigRepository;
use App\Service\PayrollCalculatorService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PayrollCalculatorServiceTest extends TestCase
{
    private PayrollCalculatorService $payrollCalculatorService;
    private BonusConfigRepository&MockObject $bonusConfigRepository;

    protected function setUp(): void
    {
        $this->bonusConfigRepository = $this->createMock(BonusConfigRepository::class);
        $this->payrollCalculatorService = new PayrollCalculatorService($this->bonusConfigRepository);
    }

    public function testCalculateBonusReturnsZeroBonusWhenEmployeeHasNoDepartment(): void
    {
        $employee = $this->createEmployeeWithoutDepartment();

        $result = $this->payrollCalculatorService->calculateBonus($employee);

        $this->assertIsArray($result);
        $this->assertEquals(0.0, $result['bonus_amount']);
        $this->assertEquals('N/A', $result['bonus_type']);
    }

    public function testCalculateBonusReturnsZeroBonusWhenNoBonusConfigFound(): void
    {
        $employee = $this->createEmployeeWithDepartment();
        $this->bonusConfigRepository->method('findOneBy')->willReturn(null);

        $result = $this->payrollCalculatorService->calculateBonus($employee);

        $this->assertIsArray($result);
        $this->assertEquals(0.0, $result['bonus_amount']);
        $this->assertEquals('N/A', $result['bonus_type']);
    }

    public function testCalculateBonusWithFixedBonusType(): void
    {
        $employee = $this->createEmployeeWithDepartment(yearsOfWork: 5, baseSalary: 5000);
        $bonusConfig = $this->createBonusConfig(bonusValue: '100', isFixed: true);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $result = $this->payrollCalculatorService->calculateBonus($employee);

        $this->assertEquals(500.0, $result['bonus_amount']);
        $this->assertEquals(BonusType::Fixed->value, $result['bonus_type']);
    }

    public function testCalculateBonusWithPercentageBonusType(): void
    {
        $employee = $this->createEmployeeWithDepartment(yearsOfWork: 5, baseSalary: 2000);
        $bonusConfig = $this->createBonusConfig(bonusValue: '10.0', isFixed: false);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $result = $this->payrollCalculatorService->calculateBonus($employee);

        // 2000 * (10 / 100) * 5 = 1000
        $this->assertEquals(1000.0, $result['bonus_amount']);
        $this->assertEquals(BonusType::Percentage->value, $result['bonus_type']);
    }

    public function testCalculateBonusRespectsMaxYearsLimit(): void
    {
        $employee = $this->createEmployeeWithDepartment(yearsOfWork: 20);
        $bonusConfig = $this->createBonusConfig(bonusValue: '100', isFixed: true, maxYears: 10);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $result = $this->payrollCalculatorService->calculateBonus($employee);

        // 100 * 10 (capped max years) = 1000
        $this->assertEquals(1000.0, $result['bonus_amount']);
    }

    public function testCalculateBonusFormatsToTwoDecimalPlaces(): void
    {
        $employee = $this->createEmployeeWithDepartment(yearsOfWork: 3, baseSalary: 1000);
        $bonusConfig = $this->createBonusConfig(bonusValue: '7.5', isFixed: false);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $result = $this->payrollCalculatorService->calculateBonus($employee);

        // 1000 * (7.5 / 100) * 3 = 225.00
        $this->assertEquals(225.0, $result['bonus_amount']);
        $this->assertIsNumeric($result['bonus_amount']);
    }

    public function testCalculateTotalSalaryWithoutBonus(): void
    {
        $employee = $this->createEmployeeWithoutDepartment(baseSalary: 5000);
        $this->bonusConfigRepository->method('findOneBy')->willReturn(null);

        $totalSalary = $this->payrollCalculatorService->calculateTotalSalary($employee);

        $this->assertEquals(5000.0, $totalSalary);
    }

    public function testCalculateTotalSalaryWithFixedBonus(): void
    {
        $employee = $this->createEmployeeWithDepartment(baseSalary: 3000, yearsOfWork: 2);
        $bonusConfig = $this->createBonusConfig(bonusValue: '200', isFixed: true);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $totalSalary = $this->payrollCalculatorService->calculateTotalSalary($employee);

        // 3000 + (200 * 2) = 3400
        $this->assertEquals(3400.0, $totalSalary);
    }

    public function testCalculateTotalSalaryWithPercentageBonus(): void
    {
        $employee = $this->createEmployeeWithDepartment(baseSalary: 2000, yearsOfWork: 4);
        $bonusConfig = $this->createBonusConfig(bonusValue: '5.0', isFixed: false);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $totalSalary = $this->payrollCalculatorService->calculateTotalSalary($employee);

        // 2000 + (2000 * (5 / 100) * 4) = 2000 + 400 = 2400
        $this->assertEquals(2400.0, $totalSalary);
    }

    public function testCalculateTotalSalaryFormatsToTwoDecimalPlaces(): void
    {
        $employee = $this->createEmployeeWithDepartment(baseSalary: 1000, yearsOfWork: 1);
        $bonusConfig = $this->createBonusConfig(bonusValue: '12', isFixed: false);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $totalSalary = $this->payrollCalculatorService->calculateTotalSalary($employee);

        $this->assertEquals(1120, $totalSalary);
    }

    public function testCalculateBonusWithZeroYearsOfWork(): void
    {
        $employee = $this->createEmployeeWithDepartment(yearsOfWork: 0);
        $bonusConfig = $this->createBonusConfig(bonusValue: '100', isFixed: true);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $result = $this->payrollCalculatorService->calculateBonus($employee);

        $this->assertEquals(0.0, $result['bonus_amount']);
    }

    public function testCalculateBonusWithLargeBaseSalaryPercentage(): void
    {
        $employee = $this->createEmployeeWithDepartment(baseSalary: 100000, yearsOfWork: 10);
        $bonusConfig = $this->createBonusConfig(bonusValue: '15.0', isFixed: false);
        $this->bonusConfigRepository->method('findOneBy')->willReturn($bonusConfig);

        $result = $this->payrollCalculatorService->calculateBonus($employee);

        // 100000 * (15 / 100) * 10 = 150000
        $this->assertEquals(150000.0, $result['bonus_amount']);
    }

    private function createEmployeeWithoutDepartment(int $baseSalary = 5000): Employee&MockObject
    {
        $employee = $this->createMock(Employee::class);
        $employee->method('getDepartment')->willReturn(null);
        $employee->method('getBaseSalary')->willReturn($baseSalary);

        return $employee;
    }

    private function createEmployeeWithDepartment(
        int $yearsOfWork = 5,
        int $baseSalary = 5000
    ): Employee&MockObject {
        $department = $this->createMock(Department::class);
        $department->method('getName')->willReturn('Engineering');

        $employee = $this->createMock(Employee::class);
        $employee->method('getDepartment')->willReturn($department);
        $employee->method('getYearsOfWork')->willReturn($yearsOfWork);
        $employee->method('getBaseSalary')->willReturn($baseSalary);

        return $employee;
    }

    private function createBonusConfig(
        string $bonusValue,
        bool $isFixed,
        ?int $maxYears = null
    ): BonusConfig&MockObject {
        $bonusConfig = $this->createMock(BonusConfig::class);
        $bonusConfig->method('getBonusValue')->willReturn($bonusValue);
        $bonusConfig->method('isFixed')->willReturn($isFixed);
        $bonusConfig->method('getMaxYears')->willReturn($maxYears);

        return $bonusConfig;
    }
}
