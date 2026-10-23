<?php

namespace App\Services\ReportBook;

use App\Models\ReportBook;
use App\Models\User;
use App\Support\ParticipantReportBookAccess as Access;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchivedReportBookPdf
{
    public function download(User $user, Collection $books, ?int $entryId = null): StreamedResponse
    {
        abort_if($books->isEmpty(), 403);
        $context = app(ReportBookPdfContext::class);

        foreach ($books as $book) {
            abort_unless($book instanceof ReportBook && Access::canViewArchivedBook($user, $book), 403);
            $book->load(['course' => fn ($q) => $q->withTrashed()]);
            $book->setRelation('entries', Access::archivedEntries($book)
                ->when($entryId !== null, fn ($q) => $q->whereKey($entryId))->get());
            abort_if($book->entries->isEmpty(), 403);
            $context->prepare($book);
        }

        $pdf = Pdf::loadView('pdf.report-book', [
            'mode' => 'all', 'books' => $books, 'user' => $user,
            'title' => 'Berichtsheft – freigegebener Ausschnitt', 'approvedExcerpt' => true,
        ]);

        return response()->streamDownload(fn () => print($pdf->output()), 'berichtsheft-freigegebener-ausschnitt.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
