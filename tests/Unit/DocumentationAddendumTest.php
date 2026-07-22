<?php

namespace Tests\Unit;

use App\Models\CourseDay;
use Tests\TestCase;

class DocumentationAddendumTest extends TestCase
{
    public function test_only_non_empty_published_addenda_are_exposed(): void
    {
        $day = new CourseDay();
        $day->documentation_addendum = '<p>Zusatz</p>';
        $day->documentation_addendum_status = CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT;

        $this->assertFalse($day->hasPublishedDocumentationAddendum());
        $this->assertNull($day->publishedDocumentationAddendumHtml());

        $day->documentation_addendum_status = CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED;
        $this->assertTrue($day->hasPublishedDocumentationAddendum());
        $this->assertSame('<p>Zusatz</p>', $day->publishedDocumentationAddendumHtml());

        $day->documentation_addendum = '<p>&nbsp;</p>';
        $this->assertFalse($day->hasPublishedDocumentationAddendum());
    }

    public function test_report_book_documentation_combines_original_and_published_addendum_as_snapshot(): void
    {
        $day = new CourseDay();
        $day->notes = '<p>Original</p>';
        $day->documentation_addendum = '<p>Zusatz</p>';
        $day->documentation_addendum_status = CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED;

        $html = $day->documentationForReportBookHtml();

        $this->assertStringContainsString('<p>Original</p><hr>', $html);
        $this->assertStringContainsString('<strong>Ergänzung zur Dokumentation</strong>', $html);
        $this->assertStringContainsString('<p>Zusatz</p>', $html);

        $day->notes = null;
        $this->assertStringContainsString('<p>Zusatz</p>', $day->documentationForReportBookHtml());

        $day->documentation_addendum_status = CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT;
        $this->assertSame('', $day->documentationForReportBookHtml());
    }

    public function test_saved_by_relation_uses_dedicated_foreign_key(): void
    {
        $relation = (new CourseDay())->documentationAddendumSavedBy();

        $this->assertSame('documentation_addendum_saved_by_user_id', $relation->getForeignKeyName());
    }

    public function test_base_views_and_report_book_contract_include_only_published_addenda(): void
    {
        foreach ([
            'livewire/user/program/course/course-show-doku.blade.php',
            'livewire/user/report-book.blade.php',
        ] as $view) {
            $compiled = app('blade.compiler')->compileString(
                file_get_contents(resource_path('views/'.$view))
            );

            $this->assertNotSame('', trim($compiled), $view);
        }

        $courseDetail = file_get_contents(resource_path('views/livewire/user/program/course/course-show-doku.blade.php'));
        $reportBook = file_get_contents(app_path('Livewire/User/ReportBook.php'));
        $migration = file_get_contents(database_path('migrations/2026_07_22_120000_add_documentation_addendum_to_course_days_table.php'));

        $this->assertStringContainsString('publishedDocumentationAddendumHtml()', $courseDetail);
        $this->assertStringContainsString("'hasDocumentation'", $reportBook);
        $this->assertStringContainsString('documentationForReportBookHtml()', $reportBook);
        $this->assertStringContainsString("longText('documentation_addendum')", $migration);
        $this->assertStringContainsString('nullOnDelete()', $migration);
    }
}
