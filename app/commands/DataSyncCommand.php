<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DataSyncCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'data:sync';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = '同步数据命令。';

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
		echo 'data sync at ',date('H:i:s');
		// $article = DB::table('article');

		// $nowTime = date('Y-m-d H:i:s');

		// for ($i = 196574 + 1; $i <= 196574 * 3; $i++) {
		//     $article->insert(
		//             array(
		//                     'aid' => $i,
		//                     'created_at' => $nowTime,
		//                     'updated_at' => $nowTime,
		//                 )
		//         );
		// }
		// echo "\n"; // ,
		// echo 'Updating ok!';
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
