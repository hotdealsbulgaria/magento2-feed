<?php
/*
 * Copyright (c) 2021. HotDeals Ltd.
 */

namespace HotDeals\Feed\Console\Command;

use HotDeals\Feed\Service\GenerateFeed as GenerateFeedService;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFeed extends Command
{
    /**
     * @var ProgressBarFactory
     */
    private $progressBarFactory;

    /**
     * @var GenerateFeedService
     */
    private $generateFeedService;

    /**
     * @var State
     */
    private $state;

    /**
     * GenerateFeed constructor.
     *
     * @param \Symfony\Component\Console\Helper\ProgressBarFactory $progressBarFactory
     * @param \HotDeals\Feed\Service\GenerateFeed $generateFeedService
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        ProgressBarFactory $progressBarFactory,
        GenerateFeedService $generateFeedService,
        State $state
    ) {
        $this->progressBarFactory = $progressBarFactory;
        $this->generateFeedService = $generateFeedService;
        $this->state = $state;
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('hotdeals:feed:generate');
        $this->setDescription('Generate HotDeals Feed for Store');
        parent::configure();
    }

    /**
     * CLI command description
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $progressBar = $this->progressBarFactory->create(['output' => $output]);
        $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%');

        $output->writeln(__('<info>%1</info>', 'Start Generating HotDeals Feed...'));

        $progressBar->start();
        $this->generateFeedService->setProgressBar($progressBar);
        $this->generateFeedService->execute();

        $output->write(PHP_EOL);
        $output->writeln(__('<info>%1</info>', 'HotDeals Feed was generated.'));

        return Cli::RETURN_SUCCESS;
    }
}
