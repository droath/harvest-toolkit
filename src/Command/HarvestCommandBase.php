<?php

declare(strict_types=1);

namespace Droath\HarvestToolkit\Command;

use Droath\HarvestToolkit\HarvestToolkit;
use Droath\HarvestToolkit\IOTrait;
use Psr\Cache\CacheItemInterface;
use Required\Harvest\Client;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Define the command base for the harvest commands.
 */
abstract class HarvestCommandBase extends Command implements ContainerAwareInterface
{
    use IOTrait;
    use ContainerAwareTrait;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        print HarvestToolkit::displayBanner();
    }

    /**
     * Get the users time entries stored in Harvest.
     *
     * @param string $timespan
     *   A string representation of a time range, e.g. (today, +1 day, -1 week)
     * @param null|bool $onlyBillable
     *   Filter out time entries based on if they're billable or not.
     *
     * @return array
     */
    protected function getUserHarvestTimeEntryInfo(string $timespan, $onlyBillable = false): array
    {
        $info = [
            'dates' => [],
            'clients' => []
        ];

        $timeEntries = $this->getHarvestClient()->timeEntries()->all([
            'from' => (new \DateTime())->modify($timespan)
        ]);

        foreach ($timeEntries as $timeEntry) {
            if (
                !isset($timeEntry['spent_date'])
                || (!isset($timeEntry['hours']) || empty($timeEntry['hours']))
            ) {
                continue;
            }

            if (
                $onlyBillable
                && (!isset($timeEntry['billable']) || !$timeEntry['billable'])
            ) {
                continue;
            }
            $hours = $timeEntry['hours'];
            $spendDate = $timeEntry['spent_date'];

            if (!isset($info['dates'][$spendDate])) {
                $info['dates'][$spendDate] = 0;
            }
            $info['dates'][$spendDate] += $hours;

            if ($clientName = $timeEntry['client']['name']) {
                $info['clients'][$spendDate]['items'][$clientName][] = [
                    'task' => $timeEntry['task']['name'] ?? null,
                    'hours' => $hours,
                    'notes' => $timeEntry['notes'] ?? null,
                    'project' => $timeEntry['project']['name'] ?? null,
                ];
                if (!isset($info['clients'][$spendDate]['total'])) {
                    $info['clients'][$spendDate]['total'] = 0;
                }
                $info['clients'][$spendDate]['total'] += $hours;
            }
        }

        return $info;
    }

    /**
     * Get the Harvest project options.
     *
     * @return array
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getUserHarvestProjectOptions(): array
    {
        return array_keys($this->getUserHarvestProjects());
    }

    /**
     * Get a Harvest project associated with a user.
     *
     * @param string $projectCode
     *   The Harvests project code.
     *
     * @return array
     *   An array of user Harvest projects.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getUserHarvestProject(string $projectCode): array
    {
        return $this->getUserHarvestProjects()[$projectCode] ?? [];
    }

    /**
     * Get the Harvest projects associated with a user.
     *
     * @return array
     *   An array of the Harvest projects.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getUserHarvestProjects(): array
    {
        return $this->commandCache()->get('harvest.projects', function (CacheItemInterface $cacheItem) {
            $cacheItem->expiresAfter(86400);

            $client = $this->getHarvestClient();
            $assignments = $client->currentUser()->projectAssignments();

            $projects = [];
            foreach ($assignments->all() as $assignment) {
                if (isset($assignment['is_active']) && !$assignment['is_active']) {
                    continue;
                }
                if (
                    !isset($assignment['project'])
                    || !isset($assignment['project']['id'])
                ) {
                    continue;
                }
                $project = $assignment['project'];
                $projectCode = $project['code'];

                $projects[$projectCode] = [
                    'client' => $assignment['client']
                ] + $project;

                $taskAssignments = $assignment['task_assignments'] ?? [];

                foreach ($taskAssignments as $taskAssignment) {
                    if (
                        (!isset($taskAssignment['task']) || empty($taskAssignment['task']))
                        || (isset($taskAssignment['is_active']) && !$taskAssignment['is_active'])
                    ) {
                        continue;
                    }
                    [
                        'id' => $taskId,
                        'name' => $taskName
                    ] = $taskAssignment['task'];

                    $projects[$projectCode]['tasks'][$taskId] = $taskName;
                }
            }
            return $projects;
        }) ?? [];
    }

    /**
     * @return \Symfony\Component\Cache\Adapter\FilesystemAdapter
     */
    protected function commandCache()
    {
        return new FilesystemAdapter(
            'harvest.commands',
            0,
            HarvestToolkit::cacheDirectory()
        );
    }

    /**
     * Get the Harvest API client.
     *
     * @return \Required\Harvest\Client
     */
    protected function getHarvestClient(): Client
    {
        return $this->container->get('harvest.client');
    }
}
