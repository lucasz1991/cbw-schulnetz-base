<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Carbon;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Tests\TestCase;

class CoachingDropdownPortalTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, fn ($app) => $app['config']->set([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync',
        ]));
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    public function test_both_editor_steps_keep_the_teleport_target_stable_and_out_of_livewire_child_morphing(): void
    {
        view()->share('errors', new ViewErrorBag());
        $component = new class extends Component {};
        $component->setId('coaching-dropdown-fixture');
        view()->share('__livewire', $component);
        $contract = (object) ['title' => 'Test coaching', 'agreed_minutes' => 90, 'unit_minutes' => 45,
            'valid_from' => Carbon::parse('2026-09-01'), 'valid_until' => Carbon::parse('2026-10-30')];
        foreach ([1, 2] as $step) {
            $html = view('livewire.coaching.plan-editor', [
                '__livewire' => $component, 'contract' => $contract, 'editorStep' => $step,
                'errors' => new ViewErrorBag(), 'generationStatus' => '', 'expandedSlotId' => null,
                'calendarMonth' => '2026-09', 'calendarDate' => '2026-09-28', 'planView' => 'list',
                'items' => [['id' => 'slot-1', 'date' => '2026-09-28', 'start' => '09:00', 'end' => '10:30',
                    'topic' => 'Test topic', 'format' => 'online', 'location' => 'Test room']],
            ])->render();
            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML($html);
            libxml_clear_errors(); libxml_use_internal_errors($previous);
            $xpath = new \DOMXPath($document);
            $portal = $xpath->query('//*[@id="coaching-slot-editor-portal"]');
            $this->assertCount(1, $portal);
            $this->assertTrue($portal->item(0)->hasAttribute('wire:ignore'));
            $this->assertSame('coaching-slot-editor-portal', $portal->item(0)->getAttribute('wire:key'));
            $this->assertSame('coaching-editor-form', $portal->item(0)->parentNode->getAttribute('id'));
            $templates = $xpath->query('//template[@x-teleport="#coaching-slot-editor-portal"]');
            $this->assertCount(1, $templates);
            $this->assertStringNotContainsString('x-teleport="#coaching-editor-form"', $html);
            $this->assertStringContainsString('@resize.window=', $html);
            $this->assertStringNotContainsString("window.addEventListener('resize'", $html);
        }
    }
}
