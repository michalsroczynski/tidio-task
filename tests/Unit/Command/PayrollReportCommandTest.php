<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\PayrollReportCommand;
use App\Entity\Department;
use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use App\Service\PayrollCalculatorService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[AllowMockObjectsWithoutExpectations]
class PayrollReportCommandTest extends TestCase
{
    private PayrollReportCommand $command;
    private PayrollCalculatorService&MockObject $calculatorService;
    private EmployeeRepository&MockObject $employeeRepository;

    protected function setUp(): void
    {
        $this->calculatorService = $this->createMock(PayrollCalculatorService::class);
        $this->employeeRepository = $this->createMock(EmployeeRepository::class);

        $this->command = new PayrollReportCommand(
            $this->calculatorService,
            $this->employeeRepository
        );
    }

    public function testExecuteReturnsSuccessWhenNoEmployeesFound(): void
    {
        $this->employeeRepository->method('findAll')->willReturn([]);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $this->assertStringContainsString('No employees found', $output->fetch());
    }

    public function testExecuteReturnsSuccessWithValidInput(): void
    {
        $employee = $this->createEmployee('Jan', 'Kowalski', 5000, 5);
        $this->employeeRepository->method('findAll')->willReturn([$employee]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 500.0,
            'bonus_type' => 'FIXED',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturn(5500.0);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $this->assertStringContainsString('Payroll report generated successfully', $output->fetch());
    }

    public function testExecuteWithInvalidSortByOption(): void
    {
        $input = new ArrayInput(['--sort-by' => 'invalid_column']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::FAILURE, $result);
        $this->assertStringContainsString('Invalid sort-by option', $output->fetch());
    }

    public function testExecuteWithInvalidSortOrderOption(): void
    {
        $input = new ArrayInput(['--sort-order' => 'INVALID']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::FAILURE, $result);
        $this->assertStringContainsString('Invalid sort-order', $output->fetch());
    }

    public function testExecuteSortsEmployeesByNameAscending(): void
    {
        $employee1 = $this->createEmployee('Adam', 'Nowak', 4000, 3);
        $employee2 = $this->createEmployee('Zuzanna', 'Wisniewski', 5000, 5);

        $this->employeeRepository->method('findAll')->willReturn([$employee2, $employee1]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturnOnConsecutiveCalls(4000.0, 5000.0);

        $input = new ArrayInput(['--sort-by' => 'name', '--sort-order' => 'ASC']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('Adam', $outputContent);
        $this->assertStringContainsString('Zuzanna', $outputContent);
        // Adam should appear before Zuzanna
        $this->assertLessThan(
            strpos($outputContent, 'Zuzanna'),
            strpos($outputContent, 'Adam')
        );
    }

    public function testExecuteSortsEmployeesByNameDescending(): void
    {
        $employee1 = $this->createEmployee('Adam', 'Nowak', 4000, 3);
        $employee2 = $this->createEmployee('Zuzanna', 'Wisniewski', 5000, 5);

        $this->employeeRepository->method('findAll')->willReturn([$employee1, $employee2]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturnOnConsecutiveCalls(4000.0, 5000.0);

        $input = new ArrayInput(['--sort-by' => 'name', '--sort-order' => 'DESC']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        // Zuzanna should appear before Adam
        $this->assertLessThan(
            strpos($outputContent, 'Adam'),
            strpos($outputContent, 'Zuzanna')
        );
    }

    public function testExecuteFiltersByDepartment(): void
    {
        $dept1 = $this->createDepartment('Engineering');
        $dept2 = $this->createDepartment('HR');

        $employee1 = $this->createEmployee('Jan', 'Kowalski', 5000, 5, $dept1);
        $employee2 = $this->createEmployee('Maria', 'Nowak', 4000, 3, $dept2);

        $this->employeeRepository->method('findAll')->willReturn([$employee1, $employee2]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturnOnConsecutiveCalls(5000.0, 4000.0);

        $input = new ArrayInput(['--filter-department' => 'Engineering']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('Engineering', $outputContent);
        $this->assertStringNotContainsString('HR', $outputContent);
    }

    public function testExecuteFiltersByFirstName(): void
    {
        $dept = $this->createDepartment('Engineering');
        $employee1 = $this->createEmployee('Jan', 'Kowalski', 5000, 5, $dept);
        $employee2 = $this->createEmployee('Maria', 'Nowak', 4000, 3, $dept);

        $this->employeeRepository->method('findAll')->willReturn([$employee1, $employee2]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturnOnConsecutiveCalls(5000.0, 4000.0);

        $input = new ArrayInput(['--filter-name' => 'Jan']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('Jan', $outputContent);
        $this->assertStringNotContainsString('Maria', $outputContent);
    }

    public function testExecuteFiltersByLastName(): void
    {
        $dept = $this->createDepartment('Engineering');
        $employee1 = $this->createEmployee('Jan', 'Kowalski', 5000, 5, $dept);
        $employee2 = $this->createEmployee('Maria', 'Nowak', 4000, 3, $dept);

        $this->employeeRepository->method('findAll')->willReturn([$employee1, $employee2]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturnOnConsecutiveCalls(5000.0, 4000.0);

        $input = new ArrayInput(['--filter-surname' => 'Kowalski']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('Kowalski', $outputContent);
        $this->assertStringNotContainsString('Nowak', $outputContent);
    }

    public function testExecuteWithNoMatchingFilters(): void
    {
        $dept = $this->createDepartment('Engineering');
        $employee = $this->createEmployee('Jan', 'Kowalski', 5000, 5, $dept);

        $this->employeeRepository->method('findAll')->willReturn([$employee]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturn(5000.0);

        $input = new ArrayInput(['--filter-name' => 'NonExistent']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $this->assertStringContainsString('No employees match the filter criteria', $output->fetch());
    }

    public function testExecuteSortsByBaseSalaryNumeric(): void
    {
        $dept = $this->createDepartment('Engineering');
        $employee1 = $this->createEmployee('Jan', 'Kowalski', 3000, 2, $dept);
        $employee2 = $this->createEmployee('Maria', 'Nowak', 5000, 5, $dept);
        $employee3 = $this->createEmployee('Piotr', 'Wisniewski', 4000, 3, $dept);

        $this->employeeRepository->method('findAll')->willReturn([$employee2, $employee1, $employee3]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturnOnConsecutiveCalls(
            5000.0,
            3000.0,
            4000.0
        );

        $input = new ArrayInput(['--sort-by' => 'base_salary', '--sort-order' => 'ASC']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        // Verify order: 3000, 4000, 5000
        $pos3000 = strpos($outputContent, '$3,000.00');
        $pos4000 = strpos($outputContent, '$4,000.00');
        $pos5000 = strpos($outputContent, '$5,000.00');

        $this->assertLessThan($pos4000, $pos3000);
        $this->assertLessThan($pos5000, $pos4000);
    }

    public function testExecuteDisplaysCurrencyFormat(): void
    {
        $employee = $this->createEmployee('Jan', 'Kowalski', 5000, 5);
        $this->employeeRepository->method('findAll')->willReturn([$employee]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 500.50,
            'bonus_type' => 'FIXED',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturn(5500.50);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('$', $outputContent);
        $this->assertStringContainsString('5,000.00', $outputContent);
        $this->assertStringContainsString('500.50', $outputContent);
    }

    public function testExecuteWithEmployeeWithoutDepartment(): void
    {
        $employee = $this->createEmployee('Jan', 'Kowalski', 5000, 5, null);
        $this->employeeRepository->method('findAll')->willReturn([$employee]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturn(5000.0);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('N/A', $outputContent);
    }

    public function testCommandName(): void
    {
        $this->assertEquals('app:payroll:report', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $description = $this->command->getDescription();
        $this->assertStringContainsString('payroll report', strtolower($description));
    }

    public function testExecuteWithCaseInsensitiveFilter(): void
    {
        $dept = $this->createDepartment('Engineering');
        $employee = $this->createEmployee('Jan', 'Kowalski', 5000, 5, $dept);

        $this->employeeRepository->method('findAll')->willReturn([$employee]);
        $this->calculatorService->method('calculateBonus')->willReturn([
            'bonus_amount' => 0.0,
            'bonus_type' => 'N/A',
        ]);
        $this->calculatorService->method('calculateTotalSalary')->willReturn(5000.0);

        $input = new ArrayInput(['--filter-name' => 'jan']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('Jan', $outputContent);
    }

    private function createEmployee(
        string $firstName,
        string $lastName,
        int $baseSalary,
        int $yearsOfWork,
        ?Department $department = null
    ): Employee&MockObject {
        $employee = $this->createMock(Employee::class);
        $employee->method('getFirstName')->willReturn($firstName);
        $employee->method('getLastName')->willReturn($lastName);
        $employee->method('getBaseSalary')->willReturn($baseSalary);
        $employee->method('getYearsOfWork')->willReturn($yearsOfWork);
        $employee->method('getDepartment')->willReturn($department);

        return $employee;
    }

    private function createDepartment(string $name): Department&MockObject
    {
        $department = $this->createMock(Department::class);
        $department->method('getName')->willReturn($name);

        return $department;
    }
}
