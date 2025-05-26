<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;

class Premium extends Component
{
    public $userSubscriptionPlans;
    public $confirmingSubscriptionPlanModal;
    public $selectedSubscriptionPlan;
    public $subscriptionPlans;

    public function mount()
    {
        $this->confirmingSubscriptionPlanModal = false;
        $this->userSubscriptionPlans = null;
        $this->selectedSubscriptionPlan = null;
        $user = auth()->user();
        if ($user && $user->subscription) {
            $this->userSubscriptionPlans = $user->subscription;
        }
        $this->subscriptionPlans = SubscriptionPlan::query()
            ->where('is_active', true) // Nur aktive Abonnements abfragen
            ->get();
    }

    public function subscribe()
    {
        

        $user = auth()->user();
        if ($user) {
            // Abonnementsdaten speichern
            $userSubscription = UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $this->selectedSubscriptionPlan['id'],
                'started_at' => now(),
                'ends_at' => now()->addMonth(), // Beispiel: 1 Monat gültig
                'is_active' => true,
                'payment_method' => 'credit_card', // Beispiel: Zahlungsmethode
                'interval' => 'monthly', // Beispiel: Abrechnungsintervall
                'payment_data' => [], // Beispiel: Zahlungsdaten
            ]);
            $this->selectedSubscriptionPlan = null;
            $this->confirmingSubscriptionPlanModal = false;
            session()->flash('success', 'Abonnement erfolgreich abgeschlossen.');
        } else {
            $this->selectedSubscriptionPlan = null;
            $this->confirmingSubscriptionPlanModal = false;
            session()->flash('error', 'Bitte melden Sie sich an, um ein Abonnement abzuschließen.');
        }
    }

    public function render()
    {   
        // Abonnementspläne abfragen
        // Hier können Sie die Abonnementspläne abfragen, die Sie anzeigen möchten
        // Zum Beispiel: Alle aktiven Abonnementspläne
        // $subscriptionPlans = SubscriptionPlan::all();
        // Beispiel: Nur aktive Abonnementspläne        
        return view('livewire.pages.premium')->layout('layouts.app');
    }
}
