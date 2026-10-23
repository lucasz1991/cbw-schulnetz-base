<?php

namespace App\Livewire\User;

use App\Models\ReportBook;
use App\Services\ReportBook\ArchivedReportBookPdf;
use App\Support\ParticipantReportBookAccess as Access;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Read-only archive: every request resolves owned books and approved entries anew. */
class ReportBookArchive extends Component
{
    #[Url(as: 'book', history: true)]
    public ?int $bookId = null;

    public function mount(): void
    {
        $books = Access::archiveBooks(Auth::user());
        abort_if($books->isEmpty(), 403);
        if ($this->bookId !== null) {
            $this->resolveBook($this->bookId);
        } else {
            $this->bookId = (int) $books->first()->id;
        }
    }

    public function hydrate(): void
    {
        abort_unless(Access::hasArchive(Auth::user()), 403);
    }

    public function selectBook(int $bookId): void
    {
        $this->resolveBook($bookId);
        $this->bookId = $bookId;
    }

    public function exportBook(): StreamedResponse
    {
        $book = $this->resolveBook((int) $this->bookId);
        return app(ArchivedReportBookPdf::class)->download(Auth::user(), collect([$book]));
    }

    public function exportEntry(int $entryId): StreamedResponse
    {
        $book = $this->resolveBook((int) $this->bookId);
        abort_unless(Access::archivedEntries($book)->whereKey($entryId)->exists(), 403);
        return app(ArchivedReportBookPdf::class)->download(Auth::user(), collect([$book]), $entryId);
    }

    public function exportAll(): StreamedResponse
    {
        return app(ArchivedReportBookPdf::class)->download(Auth::user(), Access::archiveBooks(Auth::user()));
    }

    private function resolveBook(int $id): ReportBook
    {
        $book = ReportBook::query()->where('user_id', Auth::id())->find($id);
        abort_unless($book && Access::canViewArchivedBook(Auth::user(), $book), 403);
        $book->load(['course' => fn ($q) => $q->withTrashed()]);
        return $book;
    }

    public function render()
    {
        $books = Access::archiveBooks(Auth::user());
        abort_if($books->isEmpty(), 403);
        $book = $this->resolveBook((int) $this->bookId);

        return view('livewire.user.report-book-archive', [
            'books' => $books,
            'book' => $book,
            'entries' => Access::archivedEntries($book)->get(),
            'canUseEditor' => Access::canUseEditor(Auth::user()),
        ])->layout('layouts.app');
    }
}
