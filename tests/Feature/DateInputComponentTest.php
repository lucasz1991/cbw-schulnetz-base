<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DateInputComponentTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'queue.default' => 'sync', 'session.driver' => 'array']);
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    private function renderField(string $template, array $data = []): \DOMXPath
    {
        view()->share('errors', new ViewErrorBag());
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.Blade::render($template, $data));
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        return new \DOMXPath($doc);
    }

    private function pickerOptions(\DOMXPath $dom): array
    {
        return json_decode($dom->query('//*[@data-date-picker]')->item(0)->getAttribute('data-date-picker'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_existing_inline_dates_keep_their_serialized_format_and_limits(): void
    {
        $dom = $this->renderField('<x-ui.forms.date-input id="absence" model="fehlDatum" label="Datum" :inline="true" :required="true" :disableWeekends="true" min="2026-09-01" max="2026-10-31" />');
        $config = $this->pickerOptions($dom);
        $this->assertSame('Y-m-d', $config['dateFormat']);
        $this->assertSame('d.m.Y', $config['altFormat']);
        foreach (['inline', 'required', 'disableWeekends'] as $key) $this->assertTrue($config[$key]);
        $this->assertSame('2026-09-01', $config['min']);
        $this->assertSame('2026-10-31', $config['max']);
        $this->assertSame('fehlDatum', $dom->query('//*[@data-date-value]')->item(0)->getAttribute('data-date-model'));
        $this->assertSame(1, $dom->query('//*[@data-date-inline]')->length);
    }

    public function test_combined_picker_keeps_separate_date_and_time_models(): void
    {
        $dom = $this->renderField('<x-ui.forms.date-input id="slot" model="items.0.date" timeModel="items.0.start" label="Datum & Beginn" value="2026-09-21" timeValue="09:00" />');
        $this->assertTrue($this->pickerOptions($dom)['enableTime']);
        $this->assertSame('2026-09-21', $dom->query('//*[@data-date-value]')->item(0)->getAttribute('value'));
        $this->assertSame('items.0.start', $dom->query('//*[@data-time-value]')->item(0)->getAttribute('data-date-model'));
        $this->assertSame('09:00', $dom->query('//*[@data-time-value]')->item(0)->getAttribute('value'));
        $this->assertSame('Datum & Beginn', $dom->query('//label')->item(0)->textContent);
    }

    public function test_explicit_range_format_and_safe_label_are_preserved(): void
    {
        $dom = $this->renderField('<x-ui.forms.date-input mode="range" dateFormat="d.m.Y" altFormat="j. F Y" :label="$label" :altInput="false" />', ['label' => '<script>alert(1)</script>']);
        $config = $this->pickerOptions($dom);
        $this->assertSame('range', $config['mode']);
        $this->assertSame('d.m.Y', $config['dateFormat']);
        $this->assertSame('j. F Y', $config['altFormat']);
        $this->assertFalse($config['altInput']);
        $this->assertSame(0, $dom->query('//script')->length);
    }

    public function test_time_only_disabled_input_and_reindexed_model_identity(): void
    {
        $template = '<x-ui.forms.date-input id="same-slot" :model="$model" :timeOnly="true" :disabled="true" />';
        $first = $this->renderField($template, ['model' => 'items.1.end']);
        $second = $this->renderField($template, ['model' => 'items.0.end']);
        $this->assertSame('H:i', $this->pickerOptions($first)['dateFormat']);
        $this->assertTrue($this->pickerOptions($first)['timeOnly']);
        $this->assertSame(1, $first->query('//button[@disabled]')->length);
        $this->assertNotSame($first->query('//body/div')->item(0)->getAttribute('wire:key'), $second->query('//body/div')->item(0)->getAttribute('wire:key'));
    }
}
