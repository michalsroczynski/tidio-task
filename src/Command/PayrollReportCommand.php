<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use App\Service\PayrollCalculatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:payroll:report',
    description: 'Generate a payroll report for the current month',
)]
class PayrollReportCommand extends Command
{
    public function __construct(
        private readonly PayrollCalculatorService $calculator,
        private readonly EmployeeRepository $employeeRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sort-by', 's', InputOption::VALUE_REQUIRED, 'Sort by column (name, surname, department, base_salary, bonus_amount, bonus_type, total_salary)', 'name')
            ->addOption('sort-order', 'o', InputOption::VALUE_REQUIRED, 'Sort order (ASC or DESC)', 'ASC')
            ->addOption('filter-department', 'd', InputOption::VALUE_REQUIRED, 'Filter by department name')
            ->addOption('filter-name', 'na', InputOption::VALUE_REQUIRED, 'Filter by first name')
            ->addOption('filter-surname', 'l', InputOption::VALUE_REQUIRED, 'Filter by last name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sortBy = strtolower($input->getOption('sort-by'));
        $sortOrder = strtoupper($input->getOption('sort-order'));
        $filterDepartment = $input->getOption('filter-department');
        $filterName = $input->getOption('filter-name');
        $filterSurname = $input->getOption('filter-surname');

        // Validate inputs
        if (!in_array($sortBy, ['name', 'surname', 'department', 'base_salary', 'bonus_amount', 'bonus_type', 'total_salary'], true)) {
            $io->error('Invalid sort-by option. Must be one of: name, surname, department, base_salary, bonus_amount, bonus_type, total_salary');
            return Command::FAILURE;
        }

        if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $io->error('Invalid sort-order. Must be ASC or DESC');
            return Command::FAILURE;
        }

        // Get all employees
        $employees = $this->employeeRepository->findAll();

        if (empty($employees)) {
            $io->warning('No employees found.');
            return Command::SUCCESS;
        }

        // Build report rows
        $reportRows = [];
        /** @var Employee $employee */
        foreach ($employees as $employee) {
            $bonus = $this->calculator->calculateBonus($employee);
            $totalSalary = $this->calculator->calculateTotalSalary($employee);

            $reportRows[] = [
                'name' => $employee->getFirstName(),
                'surname' => $employee->getLastName(),
                'department' => $employee->getDepartment()?->getName() ?? 'N/A',
                'base_salary' => $employee->getBaseSalary(),
                'bonus_amount' => $bonus['bonus_amount'],
                'bonus_type' => $bonus['bonus_type'],
                'total_salary' => $totalSalary,
                'employee' => $employee,
            ];
        }

        // Apply filters
        if ($filterName) {
            $reportRows = array_filter($reportRows, fn($row) => stripos($row['name'], $filterName) !== false);
        }
        if ($filterSurname) {
            $reportRows = array_filter($reportRows, fn($row) => stripos($row['surname'], $filterSurname) !== false);
        }
        if ($filterDepartment) {
            $reportRows = array_filter($reportRows, fn($row) => stripos($row['department'], $filterDepartment) !== false);
        }

        // Apply sorting
        usort($reportRows, function ($a, $b) use ($sortBy, $sortOrder) {
            $valueA = $a[$sortBy] ?? '';
            $valueB = $b[$sortBy] ?? '';

            // Convert to float for numeric comparisons
            if (in_array($sortBy, ['base_salary', 'bonus_amount', 'total_salary'], true)) {
                $valueA = (float)$valueA;
                $valueB = (float)$valueB;
            }

            $result = $valueA <=> $valueB;

            return $sortOrder === 'DESC' ? -$result : $result;
        });

        if (empty($reportRows)) {
            $io->warning('No employees match the filter criteria.');
            return Command::SUCCESS;
        }

        // Display table
        $headers = ['Name', 'Surname', 'Department', 'Remuneration Base', 'Addition to Base', 'Bonus Type', 'Salary with Bonus'];
        $rows = array_map(fn($row) => [
            $row['name'],
            $row['surname'],
            $row['department'],
            '$' . number_format((float)$row['base_salary'], 2),
            '$' . number_format((float)$row['bonus_amount'], 2),
            $row['bonus_type'],
            '$' . number_format((float)$row['total_salary'], 2),
        ], $reportRows);

        $io->table($headers, $rows);

        $io->success('Payroll report generated successfully.');

        return Command::SUCCESS;
    }
}
