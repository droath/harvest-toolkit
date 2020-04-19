<?php

declare(strict_types=1);

namespace Droath\HarvestToolkit\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Define the Harvest Time Sheet adjustment command.
 */
class HarvestTimesheetAdjust extends HarvestCommandBase
{
    /**
     * {@inheritDoc}
     */
    protected static $defaultName = 'timesheet:adjust';

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->addArgument(
            'timespan',
            InputArgument::OPTIONAL,
            'The timespan on which to adjust the time sheet.',
            'today'
        )->addOption(
            'max-hours',
            null,
            InputOption::VALUE_REQUIRED,
            'Set the required amount of hours needed per day.',
            8
        )->setDescription('Adjust time entries stored in Harvest.');
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            static $staticProject = [];

            $client = $this->getHarvestClient();

            $maxHours = $input->getOption('max-hours');
            $timespan = $input->getArgument('timespan');

            foreach ($this->getUserWorkingHourDates($timespan) as $date => $dayHours) {
                if ($dayHours >= $maxHours) {
                    continue;
                }
                $missingHours = $maxHours - $dayHours;

                $makeAdjustment = $this->confirm(sprintf(
                    "You're missing %01.2f hour(s) on %s, continue with adjustments?",
                    $missingHours,
                    $date
                ));

                if (!$makeAdjustment) {
                    continue;
                }

                if (!isset($staticProject) || empty($staticProject)) {
                    $projectCode = $this->choice(
                        'Select the Harvest project associated with missing time',
                        $this->getUserHarvestProjectOptions()
                    );
                    $project = $this->getUserHarvestProject($projectCode);

                    if (empty($project)) {
                        throw new \RuntimeException(
                            sprintf(
                                'Unable to find a project for %s!',
                                $projectCode
                            )
                        );
                    }
                    $projectId = $project['id'];

                    $taskName = $this->choice(
                        'Select the Harvest project task',
                        array_values($project['tasks'])
                    );
                    $taskId = array_search($taskName, $project['tasks']);

                    if (
                        $this->confirm(
                            'Store the Project/Task ID for any upcoming changes?'
                        )
                    ) {
                        $staticProject = [$projectId, $taskId];
                    }
                } else {
                    list($projectId, $taskId) = $staticProject;
                }
                $client->timeEntries()->create([
                    'hours' => $missingHours,
                    'task_id' => $taskId,
                    'spent_date' => $date,
                    'project_id' => $projectId,
                ]);
            }
            return 0;
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
        return 1;
    }

    /**
     * Get the Harvest users working hours dates.
     *
     * @param string $timespan
     *
     * @return array
     */
    protected function getUserWorkingHourDates(string $timespan): array
    {
        return $this->getUserHarvestTimeEntryInfo($timespan)['dates'] ?? [];
    }
}
