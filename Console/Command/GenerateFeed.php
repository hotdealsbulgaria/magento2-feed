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
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
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
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * GenerateFeed constructor.
     *
     * @param \Magento\Framework\App\State $state
     * @param \HotDeals\Feed\Service\GenerateFeed $generateFeedService
     */
    public function __construct(State $state, GenerateFeedService $generateFeedService)
    {
        $this->state = $state;
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
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Exception $e) {
        }

        $output->writeln('Start Generating HotDeals Feed...');
        $this->generateFeedService->execute();
        $output->write(PHP_EOL);
        $output->writeln('HotDeals Feed was generated.');

        return Cli::RETURN_SUCCESS;
    }
}
