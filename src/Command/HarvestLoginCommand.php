<?php

declare(strict_types=1);

namespace Droath\HarvestToolkit\Command;

use Droath\HarvestToolkit\HarvestToolkit;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HarvestLoginCommand extends HarvestCommandBase
{
    /**
     * @var string
     */
    protected static $defaultName = 'login';

    /**
     * {@inheritDoc}
     */
    public function configure(): void
    {
        $this->addOption(
            'reauthenticate',
            null,
            InputOption::VALUE_NONE,
            'Set if you need to reauthenticate using a different Harvest account.'
        )->setDescription('Authenticate with the Harvest time tracking service.');
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            if (
                !HarvestToolkit::hasHarvestAuth()
                || $input->getOption('reauthenticate')
            ) {
                $accountId = $this->ask('Input an account ID', null, true);
                $accountToken = $this->ask('Input an account API token', null, true, true);

                $status = HarvestToolkit::writeHarvestAuth([
                    'account-id' => $accountId,
                    'account-token' => $accountToken
                ]);

                if (!$status) {
                    throw new \RuntimeException(
                        'Unable to write to the harvest authentication file.'
                    );
                }
                $this->container->setParameter('harvest.accountId', $accountId);
                $this->container->setParameter('harvest.accountToken', $accountToken);
            }
            /** @var \Required\Harvest\Client $client */
            $client = $this->container->get('harvest.client');

            /** @var \Required\Harvest\Api\CurrentUser $currentUser */
            $currentUser = $client->api('currentUser');

            if ($accountInfo = $currentUser->show()) {
                $this->success(sprintf(
                    "You're logged into Harvest as %s %s!",
                    $accountInfo['first_name'],
                    $accountInfo['last_name']
                ));
            }
            return 0;
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
        return 1;
    }
}
