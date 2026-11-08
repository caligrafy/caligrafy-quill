<?php

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\ServiceError;
use Caligrafy\Uploads;
use PHPUnit\Framework\TestCase;

/**
 * The `file` rule of capabilities (2.19.0): parsing, GUMP never sees it, web and REST save the file, MCP and the
 * command line send a string
 */
final class UploadsTest extends TestCase
{
    private $tmp = array();

    protected function tearDown(): void
    {
        foreach ($this->tmp as $file) {
            @unlink($file);
        }
    }

    private function upload($name, $bytes = 100, $error = 0): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'qaup');
        file_put_contents($tmp, str_repeat('x', $bytes));
        $this->tmp[] = $tmp;
        return array('name' => $name, 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => $error, 'size' => $bytes);
    }

    private function refusal(callable $call): ServiceError
    {
        try {
            $call();
        } catch (ServiceError $e) {
            return $e;
        }
        $this->fail('a ServiceError was expected');
    }

    public function testParsesTheFileRule(): void
    {
        $this->assertSame(array('extensions' => array('png', 'jpg'), 'max' => 2097152), Uploads::parse('required|file|extension,png;jpg|max_size,2mb'));
        $this->assertSame(array('extensions' => null, 'max' => null), Uploads::parse('file'));
        $this->assertSame(array('extensions' => array('pdf'), 'max' => 512000), Uploads::parse('file|extension,.PDF|max_size,500kb'));
        $this->assertNull(Uploads::parse('required|max_len,10'));
        $this->assertNull(Uploads::parse(''));
        $this->assertSame(array('cover' => array('extensions' => array('png', 'jpg'), 'max' => 1024)), Uploads::fields((new QaSaveBook())->rules));
        $this->assertSame(1536, Uploads::bytes('1.5kb'));
        $this->assertSame(10, Uploads::bytes('10'));
        $this->assertSame(1073741824, Uploads::bytes('1G'));
        $this->expectException(InvalidArgumentException::class);
        Uploads::bytes('lots');
    }

    public function testStripsTheFileRulesForGump(): void
    {
        $this->assertSame(array('cover' => 'required', 'title' => 'required|max_len,5', 'doc' => ''),
            Uploads::strip(array('cover' => 'required|file|extension,png;jpg|max_size,2mb', 'title' => 'required|max_len,5', 'doc' => 'file')));
    }

    public function testChecksAnUploadedFile(): void
    {
        $spec = Uploads::parse('file|extension,png;jpg;php|max_size,1kb');
        $this->assertNull(Uploads::check($this->upload('cover.JPG'), $spec));
        $this->assertStringContainsString('not accepted', Uploads::check($this->upload('cover.gif'), $spec));
        $this->assertStringContainsString('not accepted', Uploads::check($this->upload('shell.php'), $spec), 'never an executable type, even when listed');
        $this->assertStringContainsString('not accepted', Uploads::check($this->upload('cover.png.php'), $spec));
        $this->assertStringContainsString('too large', Uploads::check($this->upload('big.png', 2000), $spec));
        $this->assertStringContainsString('too large', Uploads::check($this->upload('big.png', 10, UPLOAD_ERR_INI_SIZE), $spec));
        $this->assertSame('The file could not be uploaded', Uploads::check($this->upload('a.png', 10, UPLOAD_ERR_PARTIAL), $spec));
        $this->assertSame('Send one file', Uploads::check(array('name' => array('a.png', 'b.png'), 'tmp_name' => array('x', 'y')), $spec));
        $this->assertSame('Send one file', Uploads::check('a.png', $spec));
        // without an allow-list: DEFAULT_UPLOAD_EXTENSIONS
        $this->assertNull(Uploads::check($this->upload('notes.pdf'), Uploads::parse('file')));
        $this->assertNotNull(Uploads::check($this->upload('page.html'), Uploads::parse('file')));
    }

    public function testApplySavesTheFilesUnderANewNameAndDropsStrings(): void
    {
        $stored = array();
        $uploads = new Uploads(array('cover' => $this->upload('../../evil name.png')), function ($file, $allowed) use (&$stored) {
            $stored[] = array($file['name'], $allowed);
            return 'http://localhost/public/uploads/Ab12Cd34Ef56.png';
        });
        $input = $uploads->apply(array('title' => 'Dune', 'cover' => '/etc/passwd'), (new QaSaveBook())->rules);
        $this->assertSame(array('title' => 'Dune', 'cover' => 'http://localhost/public/uploads/Ab12Cd34Ef56.png'), $input);
        $this->assertSame(array(array('../../evil name.png', array('png', 'jpg'))), $stored, 'uploadFile() gets the allow-list and makes the name');
        $this->assertSame(array('http://localhost/public/uploads/Ab12Cd34Ef56.png'), $uploads->saved());

        // no file: a string sent in the field is dropped, `required` would report it
        $none = new Uploads(array('cover' => array('name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0)));
        $this->assertSame(array('title' => 'Dune'), $none->apply(array('title' => 'Dune', 'cover' => 'https://evil.example/x.png'), (new QaSaveBook())->rules));
    }

    public function testApplyRefusesABadFileAndASaveThatFails(): void
    {
        $bad = new Uploads(array('cover' => $this->upload('cover.exe')), function () { $this->fail('a refused file is not saved'); });
        $e = $this->refusal(fn () => $bad->apply(array('title' => 'Dune'), (new QaSaveBook())->rules));
        $this->assertSame('invalid', $e->kind);
        $this->assertArrayHasKey('cover', $e->fields);

        $failing = new Uploads(array('cover' => $this->upload('cover.png')), function () { return ''; });
        $this->assertSame(array('cover' => 'The file could not be saved'), $this->refusal(fn () => $failing->apply(array(), (new QaSaveBook())->rules))->fields);
    }

    public function testCapabilitiesSaveTheFileOnlyForSomeoneAllowedAndDeleteItWhenTheCallFails(): void
    {
        $events = array();
        $make = function () use (&$events) {
            return new Uploads(array('cover' => $this->upload('cover.png')),
                function () use (&$events) { $events[] = 'saved'; return 'http://localhost/public/uploads/new.png'; },
                function ($url) use (&$events) { $events[] = "deleted $url"; return true; });
        };
        $writer = new Context('rest', null, array('id' => 1, 'scopes' => array('books:write'), 'user_id' => null));

        $this->assertSame('unauthenticated', $this->refusal(fn () => Capabilities::call('qa_save_book', array('title' => 'Dune'), Context::guest(), $make()))->kind);
        $this->assertSame(array(), $events, 'a visitor uploads nothing');

        $result = Capabilities::call('qa_save_book', array('title' => 'Dune'), $writer, $make());
        $this->assertSame('http://localhost/public/uploads/new.png', $result['cover']);
        $this->assertSame(array('saved'), $events);

        $events = array();
        $this->assertSame('invalid', $this->refusal(fn () => Capabilities::call('qa_save_book', array('title' => str_repeat('x', 40)), $writer, $make()))->kind);
        $this->assertSame(array('saved', 'deleted http://localhost/public/uploads/new.png'), $events, 'other invalid inputs: the file is deleted');

        $events = array();
        try {
            Capabilities::call('qa_save_book', array('title' => 'crash'), $writer, $make());
            $this->fail('the exception goes through');
        } catch (RuntimeException $e) {
            $this->assertSame(array('saved', 'deleted http://localhost/public/uploads/new.png'), $events);
        }
    }

    public function testMcpAndTheCommandLineSendAStringWhoseExtensionIsChecked(): void
    {
        $this->assertSame('https://cdn.example/covers/dune.jpg?v=2', Capabilities::call('qa_save_book', array('title' => 'Dune', 'cover' => 'https://cdn.example/covers/dune.jpg?v=2'), Context::cli())['cover']);
        $this->assertSame('/public/uploads/a.png', Capabilities::call('qa_save_book', array('title' => 'Dune', 'cover' => '/public/uploads/a.png'), Context::local('mcp'))['cover']);
        $e = $this->refusal(fn () => Capabilities::call('qa_save_book', array('title' => 'Dune', 'cover' => 'run.php'), Context::cli()));
        $this->assertSame(array('cover'), array_keys($e->fields));
        $e = $this->refusal(fn () => Capabilities::call('qa_save_book', array('title' => str_repeat('x', 40), 'cover' => array('name' => 'x.png')), Context::cli()));
        $this->assertSame(array('title', 'cover'), array_keys($e->fields), 'GUMP errors and file errors together');
        // the schema says what agents send
        $this->assertSame(array('type' => 'string', 'description' => 'The URL or the path of a file'), Capabilities::schema('qa_save_book')['properties']['cover']);
    }

    // required_file (and required|file): a missing file is invalid on every channel (2.19.1: required_file was ignored)
    public function testARequiredFileMustBeSent(): void
    {
        $service = new class extends \Caligrafy\Service {
            public $rules = array('title' => 'max_len,20', 'poster' => 'required_file|extension,png', 'scan' => 'required|file');
            public function handle(array $input, Context $context) { return $input; }
        };
        $this->assertTrue(Uploads::required('required_file|extension,png'));
        $this->assertTrue(Uploads::required('required|file'));
        $this->assertFalse(Uploads::required('file|extension,png'));
        $this->assertFalse(Uploads::required('required|max_len,5'));

        // web and REST: no file in $_FILES (or UPLOAD_ERR_NO_FILE)
        $uploads = new Uploads(array('scan' => array('name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE)), function () { return 'http://localhost/public/uploads/x.png'; });
        $e = $this->refusal(fn () => $service->validate($uploads->apply(array('title' => 'Dune', 'poster' => 'sent as text'), $service->rules)));
        $this->assertSame('invalid', $e->kind);
        $this->assertSame(array('The Poster field needs a file', 'The Scan field needs a file'), array($e->fields['poster'], $e->fields['scan']));

        // with the files: accepted
        $uploads = new Uploads(array('poster' => $this->upload('p.png'), 'scan' => $this->upload('s.png')), function ($file) { return 'http://localhost/public/uploads/' . $file['name']; });
        $clean = $service->validate($uploads->apply(array('title' => 'Dune'), $service->rules));
        $this->assertSame('http://localhost/public/uploads/p.png', $clean['poster']);

        // MCP and the command line: a string
        $e = $this->refusal(fn () => $service->validate(array('title' => 'Dune', 'scan' => '/public/uploads/s.png')));
        $this->assertSame(array('poster'), array_keys($e->fields));
        $e = $this->refusal(fn () => $service->validate(array('poster' => '', 'scan' => '/public/uploads/s.png')));
        $this->assertSame(array('poster'), array_keys($e->fields));
        $this->assertSame('https://cdn.example/a.png', $service->validate(array('poster' => 'https://cdn.example/a.png', 'scan' => 's.png'))['poster']);

        // the schema: required, with the file description
        $schema = Capabilities::schemaFromRules($service->rules);
        $this->assertSame(array('poster', 'scan'), $schema['required']);
        $this->assertSame(array('type' => 'string', 'description' => 'The URL or the path of a file'), $schema['properties']['poster']);
        $this->assertSame(array('type' => 'string', 'description' => 'The URL or the path of a file'), $schema['properties']['scan']);
    }
}
