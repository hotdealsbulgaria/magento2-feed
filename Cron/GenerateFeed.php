<?php
/*
 * Copyright (c) 2021. HotDeals Ltd.
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
