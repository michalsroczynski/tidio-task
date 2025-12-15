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
    private const KEY_NAME = 'name';
    private const KEY_SURNAME = 'surname';
    private const KEY_DEPARTMENT = 'department';
    private const KEY_BASE_SALARY = 'base_salary';
    private const KEY_BONUS_AMOUNT = 'bonus_amount';
    private const KEY_BONUS_TYPE = 'bonus_type';
    private const KEY_TOTAL_SALARY = 'total_salary';
    private const KEY_EMPLOYEE = 'employee';

    private const SORT_COLUMNS = ['name', 'surname', 'department', 'base_salary', 'bonus_amount', 'bonus_type', 'total_salary'];
    private const SORT_ORDERS = ['ASC', 'DESC'];
    private const NUMERIC_COLUMNS = ['base_salary', 'bonus_amount', 'total_salary'];

    private const OPTION_SORT_BY = 'sort-by';
    private const OPTION_SORT_ORDER = 'sort-order';
    private const OPTION_FILTER_DEPARTMENT = 'filter-department';
    private const OPTION_FILTER_NAME = 'filter-name';
    private const OPTION_FILTER_SURNAME = 'filter-surname';

    private const DEFAULT_SORT_BY = 'name';
    private const DEFAULT_SORT_ORDER = 'ASC';

    private const ERROR_INVALID_SORT_BY = 'Invalid sort-by option. Must be one of: name, surname, department, base_salary, bonus_amount, bonus_type, total_salary';
    private const ERROR_INVALID_SORT_ORDER = 'Invalid sort-order. Must be ASC or DESC';
    private const WARNING_NO_EMPLOYEES = 'No employees found.';
    private const WARNING_NO_MATCHING_EMPLOYEES = 'No employees match the filter criteria.';
    private const SUCCESS_MESSAGE = 'Payroll report generated successfully.';

    private const TABLE_HEADERS = ['Name', 'Surname', 'Department', 'Remuneration Base', 'Addition to Base', 'Bonus Type', 'Salary with Bonus'];
    private const N_A = 'N/A';
    private const CURRENCY_FORMAT = '$';

    public function __construct(
        private readonly PayrollCalculatorService $calculator,
        private readonly EmployeeRepository $employeeRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(self::OPTION_SORT_BY, 's', InputOption::VALUE_OPTIONAL, 'Sort by column (name, surname, department, base_salary, bonus_amount, bonus_type, total_salary)', self::DEFAULT_SORT_BY)
            ->addOption(self::OPTION_SORT_ORDER, 'o', InputOption::VALUE_OPTIONAL, 'Sort order (ASC or DESC)', self::DEFAULT_SORT_ORDER)
            ->addOption(self::OPTION_FILTER_DEPARTMENT, 'd', InputOption::VALUE_OPTIONAL, 'Filter by department name')
            ->addOption(self::OPTION_FILTER_NAME, 'na', InputOption::VALUE_OPTIONAL, 'Filter by first name')
            ->addOption(self::OPTION_FILTER_SURNAME, 'l', InputOption::VALUE_OPTIONAL, 'Filter by last name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sortBy = strtolower($input->getOption(self::OPTION_SORT_BY));
        $sortOrder = strtoupper($input->getOption(self::OPTION_SORT_ORDER));

        if (!$this->validateInputs($io, $sortBy, $sortOrder)) {
            return Command::FAILURE;
        }

        $employees = $this->employeeRepository->findAll();

        if (empty($employees)) {
            $io->warning(self::WARNING_NO_EMPLOYEES);
            return Command::SUCCESS;
        }

        $reportRows = $this->buildReportRows($employees);
        $reportRows = $this->applyFilters($input, $reportRows);
        $reportRows = $this->applySorting($reportRows, $sortBy, $sortOrder);

        if (empty($reportRows)) {
            $io->warning(self::WARNING_NO_MATCHING_EMPLOYEES);
            return Command::SUCCESS;
        }

        $this->displayTable($io, $reportRows);
        $io->success(self::SUCCESS_MESSAGE);

        return Command::SUCCESS;
    }

    private function validateInputs(SymfonyStyle $io, string $sortBy, string $sortOrder): bool
    {
        if (!in_array($sortBy, self::SORT_COLUMNS, true)) {
            $io->error(self::ERROR_INVALID_SORT_BY);
            return false;
        }

        if (!in_array($sortOrder, self::SORT_ORDERS, true)) {
            $io->error(self::ERROR_INVALID_SORT_ORDER);
            return false;
        }

        return true;
    }

    private function buildReportRows(array $employees): array
    {
        $reportRows = [];
        /** @var Employee $employee */
        foreach ($employees as $employee) {
            $bonus = $this->calculator->calculateBonus($employee);
            $totalSalary = $this->calculator->calculateTotalSalary($employee);

            $reportRows[] = [
                self::KEY_NAME => $employee->getFirstName(),
                self::KEY_SURNAME => $employee->getLastName(),
                self::KEY_DEPARTMENT => $employee->getDepartment()?->getName() ?? self::N_A,
                self::KEY_BASE_SALARY => $employee->getBaseSalary(),
                self::KEY_BONUS_AMOUNT => $bonus[self::KEY_BONUS_AMOUNT],
                self::KEY_BONUS_TYPE => $bonus[self::KEY_BONUS_TYPE],
                self::KEY_TOTAL_SALARY => $totalSalary,
                self::KEY_EMPLOYEE => $employee,
            ];
        }

        return $reportRows;
    }

    private function applyFilters(InputInterface $input, array $reportRows): array
    {
        $filterName = $input->getOption(self::OPTION_FILTER_NAME);
        $filterSurname = $input->getOption(self::OPTION_FILTER_SURNAME);
        $filterDepartment = $input->getOption(self::OPTION_FILTER_DEPARTMENT);

        if ($filterName) {
            $reportRows = array_filter($reportRows, fn($row) => stripos($row[self::KEY_NAME], $filterName) !== false);
        }
        if ($filterSurname) {
            $reportRows = array_filter($reportRows, fn($row) => stripos($row[self::KEY_SURNAME], $filterSurname) !== false);
        }
        if ($filterDepartment) {
            $reportRows = array_filter($reportRows, fn($row) => stripos($row[self::KEY_DEPARTMENT], $filterDepartment) !== false);
        }

        return $reportRows;
    }

    private function applySorting(array $reportRows, string $sortBy, string $sortOrder): array
    {
        usort($reportRows, function ($a, $b) use ($sortBy, $sortOrder) {
            $valueA = $a[$sortBy] ?? '';
            $valueB = $b[$sortBy] ?? '';

            if (in_array($sortBy, self::NUMERIC_COLUMNS, true)) {
                $valueA = (float)$valueA;
                $valueB = (float)$valueB;
            }

            $result = $valueA <=> $valueB;

            return $sortOrder === 'DESC' ? -$result : $result;
        });

        return $reportRows;
    }

    private function displayTable(SymfonyStyle $io, array $reportRows): void
    {
        $rows = array_map(fn($row) => [
            $row[self::KEY_NAME],
            $row[self::KEY_SURNAME],
            $row[self::KEY_DEPARTMENT],
            self::CURRENCY_FORMAT . number_format((float)$row[self::KEY_BASE_SALARY], 2),
            self::CURRENCY_FORMAT . number_format((float)$row[self::KEY_BONUS_AMOUNT], 2),
            $row[self::KEY_BONUS_TYPE],
            self::CURRENCY_FORMAT . number_format((float)$row[self::KEY_TOTAL_SALARY], 2),
        ], $reportRows);

        $io->table(self::TABLE_HEADERS, $rows);
    }
}
