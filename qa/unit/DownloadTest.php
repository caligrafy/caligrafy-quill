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

    public function testColumnsGiveTheHeaderRowEvenWithoutRows(): void
    {
        // the 'columns' option (2.19.5): the header row and its order, even for an empty result
        $this->assertSame("started_at,signs\r\n", Download::csv(array(), false, true, array('started_at', 'signs')));
        $this->assertSame("b,a\r\n2,1\r\n,3\r\n", Download::csv(array(array('a' => 1, 'b' => 2), array('a' => 3)), false, true, array('b', 'a')));
        $this->assertNull(Download::columns(array()));
        $this->assertSame(array('a', 'b'), Download::columns(array('columns' => array('a', 'b'))));
        $this->expectException(\InvalidArgumentException::class);
        Download::columns(array('columns' => array()));
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

    // 'as' => 'zip' (2.22.0): the files a capability lists, under a folder of the project

    private function zipFolder(): string
    {
        $dir = 'qa/tmp/ziptest-' . bin2hex(random_bytes(4));
        mkdir($dir . '/photos/sub', 0777, true);
        file_put_contents("$dir/photos/a.jpg", str_repeat('j', 2000));
        file_put_contents("$dir/photos/notes.txt", str_repeat('text ', 400));
        file_put_contents("$dir/photos/sub/a.jpg", 'second a');
        file_put_contents("$dir/outside.txt", 'not in the root');
        return $dir;
    }

    public function testZipIsAFormatAndItsRootStaysInTheProject(): void
    {
        Route::download('/photos.zip', 'qa_export_rows', array('as' => 'zip', 'root' => 'public/uploads'));
        $this->assertSame('zip', Download::format(array('as' => 'zip')));
        foreach (array('../etc', 'public/../../etc', '', '/', 'C:/Windows') as $root) {
            try {
                Download::format(array('as' => 'zip', 'root' => $root));
                $this->fail("root '$root' was accepted");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("'root' is a folder of the project", $e->getMessage());
            }
        }
        $this->assertNull(Download::root(array('root' => 'qa/tmp/no-such-folder')), 'a folder that does not exist yet');
    }

    public function testZipFilesStayInTheRootAndGetCleanUniqueNames(): void
    {
        $dir = $this->zipFolder();
        $root = Download::root(array('root' => "$dir/photos"));
        $files = Download::files(array(
            'a.jpg',
            array('file' => 'sub/a.jpg'),                                  // same name: made unique
            array('file' => 'notes.txt', 'name' => '../../evil/./notes.txt'),
            '../outside.txt',                                              // outside the root: left out
            '/etc/passwd',                                                 // read under the root, which has none
            'missing.jpg',
            "a.jpg\0.txt",
            array('title' => 'no file'),
        ), $root);
        $this->assertSame(array('a.jpg', 'a (2).jpg', 'evil/notes.txt'), array_values($files));
        $this->assertSame(realpath("$dir/photos/sub/a.jpg"), array_keys($files)[1]);
        $this->assertSame('pictures/2026/x.jpg', Download::entryName("/pictures//2026/../x.jpg\r"));
        $this->assertSame(array(), Download::files(array('a.jpg'), null), 'no root, no file');
    }

    public function testZipArchiveStoresImagesCompressesTextAndIsNullWhenEmpty(): void
    {
        $dir = $this->zipFolder();
        $page = array('data' => array(array('file' => 'a.jpg', 'name' => 'pictures/a.jpg'), array('file' => 'notes.txt')), 'total' => 2, 'page' => 1, 'per_page' => 20, 'pages' => 1);
        $archive = Download::archive($page, array('root' => "$dir/photos"));
        try {
            $this->assertSame(2, $archive['count']);
            $this->assertSame(array('pictures/a.jpg', 'notes.txt'), $archive['names']);
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($archive['path']));
            $this->assertSame(str_repeat('j', 2000), $zip->getFromName('pictures/a.jpg'));
            $this->assertSame(ZipArchive::CM_STORE, $zip->statName('pictures/a.jpg')['comp_method'], 'a JPEG is stored');
            $this->assertSame(ZipArchive::CM_DEFLATE, $zip->statName('notes.txt')['comp_method'], 'text is compressed');
            $zip->close();
        } finally {
            @unlink($archive['path']);
        }
        $this->assertNull(Download::archive(array('missing.jpg', '../outside.txt'), array('root' => "$dir/photos")));
        $this->assertNull(Download::archive(array(), array('root' => "$dir/photos")));
    }
}
