<div class="bg-gray-50">
    <div class="container mx-auto px-4 py-6 space-y-4">
    
        <div class="bg-white rounded shadow p-6">
            <p class="text-sm text-gray-600 mb-1">
                Versicherung: <strong>{{ $claimRating->insurance->name ?? 'Keine Angabe' }}</strong>
            </p>
            <p class="text-sm text-gray-600 mb-1">
                Versicherungs-art: <strong>{{ $claimRating->insuranceType->name ?? 'Keine Angabe' }}</strong>
            </p>
            <p class="text-sm text-gray-600 mb-1">
                Versicherungs-typ: <strong>{{ $claimRating->insuranceSubtype->name ?? 'Keine Angabe' }}</strong>
            </p>
        </div>
        <div class="bg-white rounded shadow p-6">
            <div><span class="mr-4">Regulierungsart:</span>{{ $claimRating->answers['regulationType'] ?? '–' }}</div>
            <div><span class="mr-4">Abgeschlossen:</span>{{ $claimRating->answers['is_closed'] ? 'Ja' : 'Nein' }}</div>
            <div><span class="mr-4">Beginn:</span>{{ $claimRating->answers['selectedDates']['started_at'] ?? '–' }}</div>
            @if($claimRating->answers['is_closed'])
                <div><span class="mr-4">Beendet:</span>{{ $claimRating->answers['selectedDates']['ended_at'] ?? '–' }}</div>
            @endif
            <hr class="my-4">
            <div><span class="mr-4">Details:</span>{{ $claimRating->answers['regulationDetail']['selected_value'] ?? '–' }}</div>
        </div>
        <div class="bg-gray-200 rounded shadow p-6">
                <h2 class="text-xl md:text-xl  text-gray-800 mb-4">
                Auswertung:
            </h2>
            <div class="bg-white border border-gray-200 rounded-md p-4 mb-4">

                @forelse($claimRating->attachments['scorings']['questions'] as $question)
                    <div class="bg-gray-50 border border-gray-200 rounded-md p-4 mb-4">
                        <p class="text-sm text-gray-600"><strong>Frage:</strong> {{ $question['question_title'] }}</p>
                        <p class="text-sm text-gray-600"><strong>Beschreibung:</strong> {{ $question['question_text'] }}</p>
                        <p class="text-sm text-gray-700 mt-1"><strong>Antwort:</strong> {{ $question['answer'] }}</p>
                        @if($question['type'] == 'calc')
                            <p class="text-sm text-gray-700 mt-1"><x-insurance.insurance-rating-stars :score="$question['score']" /></p>
                        @endif
                        @if($question['type'] == 'ai')
                            <p class="text-sm text-gray-700 mt-1"><x-insurance.insurance-rating-stars :score="$question['ai_score']" /></p>
                            <p class="text-sm text-gray-700 mt-1"><strong>Kommentar:</strong> {{ $question['ai_comment'] }}</p>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-gray-500">Keine variablen Fragen gefunden.</div>
                @endforelse
                <div class=" mt-1 py-4">
                    <p class="text-sm text-gray-700">
                        <strong>Scoring variable Fragen:</strong> 
                        @if($claimRating->attachments['scorings']['variable_questions'] != null) 
                            <x-insurance.insurance-rating-stars :score="$claimRating->attachments['scorings']['variable_questions']" /> 
                        @endif 
                    </p>
                </div>
            </div>
                    <hr class="my-4">
                    <div class="lg:flex gap-4">
                        <div class="bg-primary-50 p-4 rounded w-full lg:w-2/3">
                            <div class="text-sm text-gray-600 mb-4">
                                <strong class="my-4">Gesammt Scoring:</strong> 
                                <x-insurance.insurance-rating-stars :score="$claimRating->score()" />
            
                            </div>
                    
                            <div class="prose max-w-full">
                                <h2 class="text-lg font-semibold mb-2">Kommentar</h2>
                                <p>{{ $claimRating->comment() ?: 'Kein Kommentar vorhanden.' }}</p>
                            </div>

                        </div>    
                        <div class="bg-white p-4 rounded w-full lg:w-1/3  items-center">
                            <strong class="mb-4">Scorings:</strong> 
                            <div label="Regulations Dauer">
                                <div class="flex items-center justify-between space-x-2">
                                    <span class="mr-4">Dauer:</span>
                                    <x-insurance.insurance-rating-stars :score="$claimRating->attachments['scorings']['regulation_speed']" />
                                </div>
                            </div>
                            <div label="Kundenservice">
                                <div class="flex items-center justify-between space-x-2">
                                <span class="mr-4">Kundenservice:</span>
                                    <x-insurance.insurance-rating-stars :score="$claimRating->attachments['scorings']['customer_service']" />
                                </div>
                            </div>
                            <div label="Fairness">
                                <div class="flex items-center justify-between space-x-2">
                                <span class="mr-4">Fairness:</span>
                                    <x-insurance.insurance-rating-stars :score="$claimRating->attachments['scorings']['fairness']" />
                                </div>
                            </div>
                            <div label="Transparency">
                                <div class="flex items-center justify-between space-x-2">
                                <span class="mr-4">Transparenz:</span>
                                    <x-insurance.insurance-rating-stars :score="$claimRating->attachments['scorings']['transparency']" />
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>
