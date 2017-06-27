<?php

namespace KWRI\ApiaryGenerator\Console;

use Illuminate\Console\Command;

class ApiaryCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'apiary:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates Apiary MSON';

    protected $signature = 'apiary:generate 
                             {--route= : The router to be used}
                             {--user= : The user ID to use for API response calls}
                        ';


    public function __construct()
    {
        parent::__construct();

    }


    public function handle()
    {
        $this->setUserToBeImpersonated($this->option('user'));

    }

    



    /**
     * @param $actAs
     */
    private function setUserToBeImpersonated($actAs)
    {
        if (!empty($actAs)) {
            $user = app()->make(config('api-docs.user'))->find($actAs);

            if ($user) {
                return $this->laravel['auth']->guard()->setUser($user);
            }
        }
    }

}
