<?php
/*
 * @package      Webcode_magento2
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2021 Webcode Ltd. (https://webcode.bg/)
 * @license      Visit https://webcode.bg/license/ for license details.
 */

namespace HotDeals\Feed\Console\Command;

use HotDeals\Feed\Service\GenerateFeed as GenerateFeedService;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFeed extends Command
{
    /**
     * @var GenerateFeedService
     */
    private $generateFeedService;

    /**
     * GenerateFeed constructor.
     *
     * @param \HotDeals\Feed\Service\GenerateFeed $generateFeedService
     */
    public function __construct(GenerateFeedService $generateFeedService)
    {
        $this->generateFeedService = $generateFeedService;
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Start Generating HotDeals Feed...');
        $this->generateFeedService->execute();
        $output->write(PHP_EOL);
        $output->writeln('HotDeals Feed was generated.');

        return Cli::RETURN_SUCCESS;
    }
}
