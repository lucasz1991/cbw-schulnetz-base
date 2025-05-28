<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Welcome;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Dashboard;
use App\Livewire\MessageBox;


use App\Livewire\Pages\TermsAndConditions;
use App\Livewire\Pages\PrivacyPolicy;
use App\Livewire\Pages\Imprint;
use App\Livewire\Pages\HowTo;
use App\Livewire\Pages\AboutUs;
use App\Livewire\Pages\Contact;
use App\Livewire\Pages\Faqs;
use App\Livewire\Pages\Sitemap;



use App\Livewire\Auth\RequestPasswordResetLink;
use App\Livewire\Auth\ResetPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;







Route::get('/forgot-password', RequestPasswordResetLink::class)->name('password.request');
// Route::post('/forgot-password', [RequestPasswordResetLink::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
// Route::post('/reset-password', [ResetPassword::class, 'reset'])->name('password.update');
// Überschreibe die Standard-POST-Routen
Route::post('/forgot-password', function () {
    abort(404);
})->name('password.email');

Route::post('/reset-password', function () {
    abort(404);
})->name('password.update');

// Allgemeine Routes für Gäste
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    // Customer Routes
    Route::middleware(['role:guest'])->group(function () {
        Route::get('/', Welcome::class)->name('home');
        Route::get('/contact', Contact::class)->name('contact');
        Route::get('/termsandconditions', TermsAndConditions::class)->name('terms');
        Route::get('/imprint', Imprint::class)->name('imprint');
        Route::get('/privacypolicy', PrivacyPolicy::class)->name('privacypolicy');
        Route::get('/sitemap', Sitemap::class)->name('sitemap');
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/messages', MessageBox::class)->name('messages');
        Route::get('/howto', HowTo::class)->name('howto');
        Route::get('/faqs', Faqs::class)->name('faqs');
        });

    });


    Route::get('/download/invoice/{filename}', function ($filename) {
        // Logge den Dateinamen
        Log::info("Download angefordert für Datei: {$filename}");
    
        $filePath = "private/bills/{$filename}";
    
        // Prüfe, ob die Datei existiert
        if (!Storage::disk('local')->exists($filePath)) {
            Log::warning("Datei nicht gefunden: {$filePath}");
            abort(404, 'Datei nicht gefunden.');
        }
    
        // Zugriffsbeschränkung prüfen
        if (!auth()->check()) {
            Log::warning("Nicht authentifizierter Zugriff auf Datei: {$filePath}");
            abort(403, 'Zugriff verweigert.');
        }
    
        if (!auth()->user()->hasAccessToInvoice($filename)) {
            Log::warning("Benutzer hat keinen Zugriff auf Datei: {$filePath}");
            abort(403, 'Zugriff verweigert.');
        }
    
        Log::info("Benutzer hat Zugriff. Datei wird bereitgestellt: {$filePath}");
    
        // Datei zurückgeben
        return Storage::disk('local')->download($filePath);
    })->name('invoice.download');
