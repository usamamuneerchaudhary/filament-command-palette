<?php

namespace Usamamuneerchaudhary\CommandPalette\Commands;

use Illuminate\Console\Command;

class CommandPalettePublishViewsCommand extends Command
{
    protected $signature = 'command-palette:publish-views {--force : Overwrite existing files}';

    protected $description = 'Publish the command palette views for customization';

    public function handle(): int
    {
        $this->info('Publishing command palette views...');

        $this->call('vendor:publish', [
            '--tag' => 'command-palette-views',
            '--force' => $this->option('force'),
        ]);

        $this->newLine();
        $this->info('Views published successfully! They can be customized in');
        $this->line('  <comment>resources/views/vendor/command-palette/</comment>');

        return Command::SUCCESS;
    }
}
