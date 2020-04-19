<?php

declare(strict_types=1);

namespace Droath\HarvestToolkit;

use Droath\HarvestToolkit\Datastore\JsonDatastore;
use Required\Harvest\Client;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Define the Harvest Toolkit CLI application.
 */
class HarvestToolkit extends Application
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    protected $container;

    /**
     * @var string
     */
    protected const VERSION = '1.0.0';

    /**
     * @var string
     */
    protected const AUTHENTICATION_FILENAME = 'harvest.auth.json';

    /**
     * The Harvest Toolkit application constructor.
     */
    public function __construct()
    {
        parent::__construct(static::displayBanner(), static::VERSION);
    }

    /**
     * Display the Harvest Toolkit application banner.
     *
     * @return string
     *   The application artwork, or name if not found.
     */
    public static function displayBanner(): string
    {
        return file_get_contents(APP_ROOT . '/banner.txt')
            ?? 'Harvest Toolkit';
    }

    /**
     * GEt the Harvest Toolkit cache directory.
     *
     * @return string
     */
    public static function cacheDirectory(): string
    {
        return implode(
            DIRECTORY_SEPARATOR,
            [self::userDirectory(), 'cache']
        );
    }

    /**
     * Get the Harvest Toolkit user directory.
     *
     * @return string
     */
    public static function userDirectory(): string
    {
        return implode(
            DIRECTORY_SEPARATOR,
            [getenv('HOME'), '.harvest-toolkit']
        );
    }

    /**
     * Has a Harvest Toolkit authentication file.
     *
     * @return bool
     */
    public static function hasHarvestAuth(): bool
    {
        return !empty(static::readHarvestAuth());
    }

    /**
     * Write credentials to the authentication file.
     *
     * @param array $data
     *   An array of credential data.
     *
     * @return bool
     */
    public static function writeHarvestAuth(array $data): bool
    {
        return static::writeToHarvestTemp(
            static::AUTHENTICATION_FILENAME,
            $data
        );
    }

    /**
     * Read an arbitrary file from the Harvest Toolkit user directory.
     *
     * @param string $filename
     *
     * @return array|string
     */
    public static function readFromHarvestTemp(string $filename)
    {
        return (new JsonDatastore(static::userDirectory() . "/{$filename}"))
            ->read();
    }

    /**
     * Write an arbitrary file to the Harvest Toolkit user directory.
     *
     * @param string $filename
     * @param array $data
     *
     * @return bool
     */
    public static function writeToHarvestTemp(string $filename, array $data): bool
    {
        return (new JsonDatastore(static::userDirectory() . "/{$filename}"))
            ->write($data);
    }

    /**
     * Get Harvest Toolkit service container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     *   The symfony container builder instance.
     *
     * @throws \Exception
     */
    public function getContainer(): ContainerBuilder
    {
        if (!isset($this->container)) {
            $this->container = $this->buildContainer();
        }

        return $this->container;
    }

    /**
     * Get the container service.
     *
     * @param string $id
     *   The service container.
     *
     * @return object
     *
     * @throws \Exception
     */
    public function getService(string $id): object
    {
        return $this->getContainer()->get($id);
    }

    /**
     * Load Harvest Toolkit console commands.
     *
     * @throws \Exception
     */
    public function loadCommands()
    {
        $container = $this->getContainer();

        $commandLoader = new ContainerCommandLoader(
            $container,
            $this->getCommandMap()
        );
        $this->setCommandLoader($commandLoader);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function add(Command $command)
    {
        $command = parent::add($command);

        if ($command instanceof ContainerAwareInterface) {
            $command->setContainer($this->getContainer());
        }

        return $command;
    }

    /**
     * Execute the Harvest Toolkit application.
     *
     * @return int
     */
    public function execute(): int
    {
        /** @var \Symfony\Component\Console\Input\InputInterface $input */
        $input = $this->getService('input');

        /** @var \Symfony\Component\Console\Output\OutputInterface $output */
        $output = $this->getService('output');

        try {
            return $this->loadCommands()->run($input, $output);
        } catch (\Exception $exception) {
            $formatter = new FormatterHelper();
            $output->writeln(
                $formatter->formatSection(
                    'ERROR',
                    $exception->getMessage(),
                    'error'
                )
            );
        }

        return 1;
    }

    /**
     * Read the harvest authenticate file.
     *
     * @return array|string
     */
    protected static function readHarvestAuth()
    {
        return static::readFromHarvestTemp(
            static::AUTHENTICATION_FILENAME
        );
    }

    /**
     * Get the console command maps.
     *
     * @return array
     *   An array console command mappings.
     *
     * @throws \Exception
     */
    protected function getCommandMap(): array
    {
        $commandMap = [];

        foreach ($this->findCommandServices() as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $commandMap[$tag['command']] = $serviceId;
            }
        }

        return $commandMap;
    }

    /**
     * Build the console container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     * @throws \Exception
     */
    protected function buildContainer(): ContainerBuilder
    {
        $harvestAuth = static::readHarvestAuth();
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->setParameter(
            'harvest.accountId',
            $harvestAuth['account-id'] ?? ''
        );
        $containerBuilder->setParameter(
            'harvest.accountToken',
            $harvestAuth['account-token'] ?? ''
        );

        $loader = new YamlFileLoader(
            $containerBuilder,
            new FileLocator(APP_ROOT)
        );
        $loader->load('services.yaml');

        $containerBuilder
            ->register('harvest.client', Client::class)
            ->addMethodCall('authenticate', [
                '%harvest.accountId%',
                '%harvest.accountToken%'
            ]);

        $containerBuilder->register('input', ArgvInput::class);
        $containerBuilder->register('output', ConsoleOutput::class);
        $containerBuilder->register('cache.filesystem', FilesystemAdapter::class);

        return $containerBuilder;
    }

    /**
     * Find the console command services.
     *
     * @return array
     *   An array of console command services.
     *
     * @throws \Exception
     */
    protected function findCommandServices(): array
    {
        return $this->getContainer()
            ->findTaggedServiceIds('console.command');
    }
}
