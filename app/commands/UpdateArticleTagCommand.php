<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateArticleTagCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'article-tag:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the article tags.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        // set memory limit
        ini_set('memory_limit', '-1');
        ini_set("default_socket_timeout", 480);

        $start = 387753 + 1;
        $end   = MultiplexController::getLatestArticleId();

        $tagArr = MultiplexController::getAllTags();

        MultiplexController::articleMatchTags($start, $end, $tagArr);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            // array('example', InputArgument::REQUIRED, 'An example argument.'),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            // array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
        );
    }

}
