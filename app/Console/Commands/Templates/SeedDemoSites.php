<?php

declare(strict_types=1);

namespace App\Console\Commands\Templates;

use App\Actions\Templates\ProvisionDemoSite;
use App\Templates\SiteTemplate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Build the showcase sites behind the public template gallery.
 *
 * Run once after a deploy, and again whenever a template's copy changes — the
 * whole thing is idempotent, so re-running is the normal way to update a demo
 * site rather than an exceptional recovery.
 *
 * One template failing does not stop the rest: the sites are independent, and
 * a gallery missing one card beats a gallery missing eight.
 */
#[Signature('demo:seed {template?* : Which templates to build, by slug — all of them when omitted} {--skip-photos : Build the pages without touching the photo provider}')]
#[Description('Build or rebuild the demo site behind each industry template')]
final class SeedDemoSites extends Command
{
    public function handle(ProvisionDemoSite $provisionDemoSite): int
    {
        $templates = $this->templates();

        if ($templates === null) {
            return self::FAILURE;
        }

        $skipPhotos = (bool) $this->option('skip-photos');
        $failed = 0;

        foreach ($templates as $template) {
            try {
                $tenant = $provisionDemoSite->handle($template, $skipPhotos);

                $this->components->info(sprintf('%s → %s', $template->label(), $tenant->domain?->getUrl() ?? $template->demoSubdomain()));
            } catch (Throwable $throwable) {
                $failed++;

                $this->components->error(sprintf('%s failed: %s', $template->label(), $throwable->getMessage()));
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The templates named on the command line, or every one of them. Null when
     * an argument names no template — reported with the valid slugs, because
     * the whole point of the argument is to save typing and a silent no-op
     * would cost more than it saves.
     *
     * @return list<SiteTemplate>|null
     */
    private function templates(): ?array
    {
        /** @var list<string> $arguments */
        $arguments = (array) $this->argument('template');

        if ($arguments === []) {
            return SiteTemplate::cases();
        }

        $templates = [];

        foreach ($arguments as $argument) {
            $template = SiteTemplate::tryFrom($argument);

            if (! $template instanceof SiteTemplate) {
                $this->components->error(sprintf('Unknown template "%s".', $argument));

                // On its own line, not folded into the error: the error
                // component wraps and truncates, and the list of slugs is the
                // only actionable part of this message.
                $this->line('Available: '.implode(', ', array_column(SiteTemplate::cases(), 'value')));

                return null;
            }

            $templates[] = $template;
        }

        return $templates;
    }
}
