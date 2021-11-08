<?php
/*
 * @package      Webcode_magento2
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2021 Webcode Ltd. (https://webcode.bg/)
 * @license      Visit https://webcode.bg/license/ for license details.
 */

namespace HotDeals\Feed\Cron;

class GenerateFeed
{
    /**
     * @var \HotDeals\Feed\Service\GenerateFeed
     */
    private $generateFeed;

    public function __construct(\HotDeals\Feed\Service\GenerateFeed $generateFeed)
    {
        $this->generateFeed = $generateFeed;
    }

    /**
     * Cronjob Description
     *
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        $this->generateFeed->execute();
    }
}
