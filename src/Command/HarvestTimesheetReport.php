<?php

declare(strict_types=1);

namespace Droath\HarvestToolkit\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HarvestTimesheetReport extends HarvestCommandBase
{
    /**
     * {@inheritDoc}
     */
    protected static $defaultName = 'timesheet:report';

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->addArgument(
            'timespan',
            InputArgument::OPTIONAL,
            'The timespan to show the time sheet report for.',
            'today'
        )->addOption(
            'only-billable',
            null,
            InputOption::VALUE_NONE,
            'Only show time entries that are billable in the report.'
        )
        ->addOption(
            'oneline',
            null,
            InputOption::VALUE_NONE,
            'Show the total time entries report in a condensed line.'
        )
        ->addOption(
            'copy',
            null,
            InputOption::VALUE_NONE,
            'Copy last output to clipboard (only supported when used with --oneline).'
        )->setDescription('Show all the time entries stored in Harvest.');
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $timespan = $input->getArgument('timespan');
            $onlyBillable = $input->getOption('only-billable');

            $clientTime = $this->getUserHarvestTimeByClient($timespan, $onlyBillable);

            if (!empty($clientTime)) {
                foreach ($clientTime as $date => $entries) {
                    if ($input->getOption('oneline')) {
                        $projects = [];

                        foreach ($entries['items'] as $entry) {
                            $projects = array_merge($projects, array_unique(
                                array_column($entry, 'project')
                            ));
                        }
                        $timeFormat = trim("{$entries['total']}H - " . implode(', ', $projects));

                        if ($input->getOption('copy') && PHP_OS === 'Darwin') {
                            shell_exec("echo '{$timeFormat}' | pbcopy");
                        }
                        $output->writeln("\n$timeFormat\n");
                    } else {
                        $this->renderUserHarvestTimesheetTable(
                            $date,
                            $entries
                        );
                    }
                }
            } else {
                $output->writeln("\n<info>No time entries were found!</info>\n");
            }
            return 0;
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
        return 1;
    }

    /**
     * Get the user Harvest time broken down by client.
     *
     * @param string $timespan
     * @param bool $onlyBillable
     *
     * @return array
     */
    protected function getUserHarvestTimeByClient(
        string $timespan,
        $onlyBillable = false
    ): array {
        return $this->getUserHarvestTimeEntryInfo($timespan, $onlyBillable)['clients'] ?? [];
    }

    /**
     * Render the users Harvest timesheet table.
     *
     * @param string $date
     * @param array $entries
     */
    protected function renderUserHarvestTimesheetTable(string $date, array $entries): void
    {
        $table = new Table($this->output());
        $table->setHeaderTitle($date);

        foreach ($entries['items'] as $client => $items) {
            $table->setColumnMaxWidth(0, 30);
            $table->setColumnMaxWidth(2, 30);
            $table->setColumnWidths([30, 20, 30, 5]);
            $table->setHeaders(['Client/Project', 'Task', 'Notes', 'Hours']);

            foreach ($items as $item) {
                $table->addRow([
                    "{$client}/{$item['project']}",
                    $item['task'],
                    $item['notes'] ?? 'N/A',
                    $item['hours']
                ]);
            }
            $table->addRow(new TableSeparator());
        }
        $table->addRow(
            [new TableCell('Total Hours', ['colspan' => 3]), $entries['total']]
        );
        $table->render();
    }
}
