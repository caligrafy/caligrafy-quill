<?php

use Caligrafy\Download;
use Caligrafy\Request;
use Caligrafy\Route;
use PHPUnit\Framework\TestCase;

/**
 * Route::download (2.19.0): CSV formatting and quoting (RFC 4180), JSON, the file name
 */
final class DownloadTest extends TestCase
{
    protected function setUp(): void
    {
        Route::reset();
    }

    protected function tearDown(): void
    {
        Route::reset();
    }

    public function testRegistersAGetRouteAndRefusesAnUnknownFormat(): void
    {
        Route::download('/books.csv', 'qa_export_rows');
        Route::download('/books.json', 'qa_export_rows', array('as' => 'json'));
        $this->assertSame(array(array('method' => 'GET', 'route' => 'books.csv'), array('method' => 'GET', 'route' => 'books.json')), Route::routes());
        $this->expectException(InvalidArgumentException::class);
        Route::download('/books.xlsx', 'qa_export_rows', array('as' => 'xlsx'));
    }

    public function testOnlyReadOnlyCapabilitiesCanBeDownloaded(): void
    {
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('changes data');
        Download::handle('qa_save_book', array(), $request);
    }

    public function testCsvHeaderRowQuotingAndLineEnds(): void
    {
        $rows = array(
            array('id' => 1, 'title' => 'Dune', 'note' => 'plain'),
            array('id' => 2, 'title' => 'Hello, "world"', 'note' => "two\nlines"),
            array('note' => "cr\rhere", 'id' => 3, 'extra' => 'left out'),
        );
        $this->assertSame("id,title,note\r\n1,Dune,plain\r\n2,\"Hello, \"\"world\"\"\",\"two\nlines\"\r\n3,,\"cr\rhere\"\r\n", Download::csv($rows));
        $this->assertSame("\xEF\xBB\xBFid\r\n1\r\n", Download::csv(array(array('id' => 1)), true), 'the BOM for Excel');
        $this->assertSame('', Download::csv(array()));
        $this->assertSame("\xEF\xBB\xBF", Download::csv(array(), true));
        $this->assertSame("\"a,b\",\"q\"\"\"\r\nx,y\r\n", Download::csv(array(array('a,b' => 'x', 'q"' => 'y'))), 'header cells are quoted too');
    }

    public function testCells(): void
    {
        $this->assertSame('', Download::cell(null));
        $this->assertSame('true', Download::cell(true));
        $this->assertSame('false', Download::cell(false));
        $this->assertSame('3.5', Download::cell(3.5));
        $this->assertSame('"[""a"",""b""]"', Download::cell(array('a', 'b')), 'arrays as JSON, quoted');
        $this->assertSame('"{""k"":""é""}"', Download::cell(array('k' => 'é')), 'UTF-8 kept');
        $this->assertSame('café', Download::cell('café'));
        // formulas are not run by spreadsheets
        $this->assertSame("'=SUM(A1:A9)", Download::cell('=SUM(A1:A9)'));
        $this->assertSame("'+cmd", Download::cell('+cmd'));
        $this->assertSame("'@x", Download::cell('@x'));
        $this->assertSame('"\'=HYPERLINK(""http://x"",""y"")"', Download::cell('=HYPERLINK("http://x","y")'));
        $this->assertSame('-12.5', Download::cell('-12.5'), 'a negative number stays a number');
        $this->assertSame('-3', Download::cell(-3));
        $this->assertSame('=1+1', Download::cell('=1+1', false), "'formulas' => true keeps them");
    }

    public function testRowsOfAResult(): void
    {
        $this->assertSame(array(array('id' => 1)), Download::rows(array('id' => 1)), 'one row');
        $this->assertSame(array(array('value' => 'a'), array('value' => 'b')), Download::rows(array('a', 'b')));
        $this->assertSame(array(array('value' => 5)), Download::rows(5));
        $this->assertSame(array(), Download::rows(null));
        $page = array('data' => array(array('id' => 1), array('id' => 2)), 'total' => 2, 'page' => 1, 'per_page' => 20, 'pages' => 1);
        $this->assertSame(array(array('id' => 1), array('id' => 2)), Download::rows($page), 'a page of paginate(): its rows');
        $this->assertSame(array(array('title' => 'one'), array('title' => 'two')), Download::rows(array((object) array('title' => 'one'), (object) array('title' => 'two'))), 'objects become arrays');
    }

    public function testFileNames(): void
    {
        $this->assertSame('books.csv', Download::filename('books.csv', null, array(), 'x.csv'));
        $this->assertSame('passwd', Download::filename('../../etc/passwd', null, array(), 'x.csv'), 'no path');
        $this->assertSame('a.csv', Download::filename("a\"\r\n.csv", null, array(), 'x.csv'), 'no quotes or line breaks (header injection)');
        $this->assertSame('x.csv', Download::filename('', null, array(), 'x.csv'));
        $this->assertSame('x.csv', Download::filename(null, null, array(), 'x.csv'));
        $this->assertSame('shelf-sf.csv', Download::filename(fn ($result, $input) => "shelf-{$input['shelf']}.csv", array(), array('shelf' => 'sf'), 'x.csv'));
        $this->assertSame('attachment; filename="books.csv"', Download::disposition('books.csv'));
        $this->assertSame("attachment; filename=\"livres_t_.csv\"; filename*=UTF-8''livres%C3%A9t%C3%A9.csv", Download::disposition('livresété.csv'));
    }
}
