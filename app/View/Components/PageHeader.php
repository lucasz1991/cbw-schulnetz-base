<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\Setting;
use App\Services\WebPages\CurrentPageService;

class PageHeader extends Component
{
    public $page;
    public bool $isWebPage = false;
    public bool $showHeader = false;
    public $title;
    public $icon;
    public $header_image;
    public $app_base_url;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $webPage = app(CurrentPageService::class)->findWebPage();
        $this->app_base_url = rtrim((string) (Setting::where('key', 'base_api_url')->value('value') ?: config('app.url') ?: url('/')), '/');
        $this->isWebPage = $webPage !== null;
        if ($webPage) {
            // Falls eine WebPage existiert, verwende deren Daten, ansonsten Standardwerte
            $this->showHeader = (bool) ($webPage->settings['showHeader'] ?? false);
            $this->title = $webPage->title;
            $this->icon = $webPage->icon;
            $this->header_image = $webPage->header_image;
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.page-header');
    }
}
