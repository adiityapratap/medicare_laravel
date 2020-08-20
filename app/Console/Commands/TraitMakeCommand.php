<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TraitMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:trait 
                            {name : Trait (singular) for example User}
                            {--M|module : Module (singular) for example Auth}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new trait';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return file_get_contents(base_path() . '/app/Console/Stubs/trait.stub');
    }

    /**
     * Get the default namespace for the class.
     *
     * @return string
     */
    protected function getDefaultNamespace()
    {
        return 'App\Traits';
    }

    /**
     * Generate full trait
     * 
     * @param string $name
     * 
     * @return null
     */
    protected function trait($name, $module = NULL)
    {
        $namespace = $this->getDefaultNamespace();
        if($module) {
            $namespace = "Modules\{$module}\Traits";
        }
        $stub = $this->getStub();
        $traitTemplate = str_replace(
            ['{{NAMESPACE}}'],
            [$namespace],
            $stub
        );
        $traitTemplate = str_replace(
            ['{{TRAIT}}'],
            [$name],
            $traitTemplate
        );
        if (!is_dir(app_path("Traits/"))) {
            // dir doesn't exist, make it
            mkdir(app_path("Traits/"));
        }

        $filePath = app_path("Traits/{$name}.php");
        $status = file_put_contents($filePath, $traitTemplate);

        return $status ? $filePath : $status;
    }


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
    public function handle()
    {
        $name = $this->argument('name');
        $module = $this->option('module');
        $status = $this->trait($name, $module);
        if($status) {
            $this->info("Created Trait {$status}");
        }
    }
}
