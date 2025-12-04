<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NewEntity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'new:entity {context} {entity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate DDD structure for a new entity within a bounded context';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get and validate arguments
        $context = $this->argument('context');
        $entity = $this->argument('entity');

        // Convert to PascalCase for consistency
        $context = Str::studly($context);
        $entity = Str::studly($entity);

        // Define directory structure
        $basePath = base_path('src');
        $entityPath = "{$basePath}/{$context}/{$entity}";

        $directories = [
            "{$entityPath}/Domain",
            "{$entityPath}/Application",
            "{$entityPath}/Infrastructure",
        ];

        // Create directories with .gitkeep
        $this->info("Creating DDD structure for {$entity} in {$context} context...");
        $this->newLine();

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                File::put("{$directory}/.gitkeep", '');
                $this->info("✓ Created: {$directory}");
            } else {
                $this->warn("⚠ Already exists: {$directory}");
            }
        }

        // Generate Eloquent Model
        $modelPath = app_path("Models/{$entity}.php");

        if (!File::exists($modelPath)) {
            $modelContent = $this->generateEloquentModel($entity);
            File::put($modelPath, $modelContent);
            $this->info("✓ Created: {$modelPath}");
        } else {
            $this->warn("⚠ Model already exists: {$modelPath}");
        }

        // Success summary
        $this->newLine();
        $this->info("🎉 Entity structure generated successfully!");
        $this->line("Context: {$context}");
        $this->line("Entity: {$entity}");
        $this->newLine();
        $this->line("Next steps:");
        $this->line("1. Define domain entity in: src/{$context}/{$entity}/Domain/");
        $this->line("2. Create use cases in: src/{$context}/{$entity}/Application/");
        $this->line("3. Implement repository in: src/{$context}/{$entity}/Infrastructure/");

        $tableName = Str::snake(Str::plural($entity));
        $this->line("4. Add migration: php artisan make:migration create_{$tableName}_table");

        return Command::SUCCESS;
    }

    /**
     * Generate Eloquent model stub
     */
    private function generateEloquentModel(string $entity): string
    {
        return <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {$entity} extends Model
{
    use HasFactory, HasUuids;

    protected \$fillable = [
        // TODO: Add fillable attributes
    ];

    protected \$casts = [
        // TODO: Add type casts
    ];
}

PHP;
    }
}
