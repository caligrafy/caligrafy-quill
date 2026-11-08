<?php

use Caligrafy\Capabilities;
use Caligrafy\Database;
use Caligrafy\Query;
use Caligrafy\Service;
use Caligrafy\ServiceError;
use Caligrafy\Validator;
use PHPUnit\Framework\TestCase;

/**
 * The rules of the framework (2.19.0): exists and unique (bound queries on an in-memory SQLite), current_year, and a
 * schema that ignores a rule without its argument
 */
final class ValidatorRulesTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE authors (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
        $pdo->exec("INSERT INTO authors VALUES (1, 'Ada')");
        $pdo->exec("INSERT INTO users VALUES (1, 'ann@example.com'), (2, 'bob@example.com')");
        Database::useConnection($pdo);
        Query::watch(false);
    }

    protected function tearDown(): void
    {
        Database::useConnection(null);
        Query::watch(null);
    }

    private function check(array $data, array $rules)
    {
        return (new Validator())->check($data, $rules);
    }

    public function testExists(): void
    {
        $this->assertTrue($this->check(array('author_id' => '1'), array('author_id' => 'required|integer|exists,authors.id')));
        $this->assertSame(array('author_id' => 'There is no such author'), $this->check(array('author_id' => '9'), array('author_id' => 'required|exists,authors.id')));
        $this->assertSame(array('author_id' => 'There is no such author'), $this->check(array('author_id' => "1' OR '1'='1"), array('author_id' => 'exists,authors.id')), 'a bound value');
        $this->assertTrue($this->check(array('author_id' => ''), array('author_id' => 'exists,authors.id')), 'an empty optional input is not checked');
    }

    public function testUniqueWithAndWithoutTheIgnoredRow(): void
    {
        $this->assertTrue($this->check(array('email' => 'new@example.com'), array('email' => 'required|valid_email|unique,users.email')));
        $this->assertSame(array('email' => 'This email is already used'), $this->check(array('email' => 'ann@example.com'), array('email' => 'unique,users.email')));
        // an update: the row of this user is ignored, the others still count
        $rules = array('email' => 'unique,users.email,id,user_id');
        $this->assertTrue($this->check(array('email' => 'ann@example.com', 'user_id' => '1'), $rules));
        $this->assertSame(array('email' => 'This email is already used'), $this->check(array('email' => 'bob@example.com', 'user_id' => '1'), $rules));
        $this->assertTrue($this->check(array('email' => 'ann@example.com', 'id' => '1'), array('email' => 'unique,users.email,id')), 'the field defaults to the column');
        $this->assertTrue($this->check(array('email' => 'ann@example.com', 'user_id' => '1'), array('email' => 'unique,users.email;id;user_id')), "GUMP's own ; form");
    }

    public function testIdentifiersAreCheckedAsTheQueryBuilderChecksThem(): void
    {
        foreach (array('exists,authors', 'exists,authors.i-d', 'unique,users`.email', 'exists,.id', 'exists,authors.') as $rule) {
            try {
                $this->check(array('x' => '1'), array('x' => $rule));
                $this->fail("$rule is refused");
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testTheAppMessageWins(): void
    {
        $validator = new Validator();
        $validator->set_fields_error_messages(array('author_id' => array('exists' => 'Pick an author of the list')));
        $this->assertSame(array('author_id' => 'Pick an author of the list'), $validator->check(array('author_id' => '9'), array('author_id' => 'exists,authors.id')));
    }

    public function testCurrentYear(): void
    {
        $year = (int) date('Y');
        $rules = array('year' => 'required|integer|min_numeric,1450|max_numeric,current_year');
        $this->assertTrue($this->check(array('year' => (string) $year), $rules));
        $errors = $this->check(array('year' => (string) ($year + 1)), $rules);
        $this->assertStringContainsString((string) $year, $errors['year'], 'the message shows the year');
        $this->assertTrue($this->check(array('year' => (string) ($year + 1)), array('year' => 'min_numeric,current_year')));
        $this->assertSame('max_numeric,' . $year . '|unique,users.email;id;user_id', Validator::normalize('max_numeric,current_year|unique,users.email,id,user_id'));
        $this->assertSame('required|max_len,10', Validator::normalize('required|max_len,10'));
    }

    public function testServicesUseTheRules(): void
    {
        $service = new class extends Service {
            public $rules = array('author_id' => 'required|exists,authors.id', 'published_year' => 'max_numeric,current_year');
            public function handle(array $input, Caligrafy\Context $context) { return $input; }
        };
        $this->assertSame(array('author_id' => '1', 'published_year' => '2000'), $service->run(array('author_id' => '1', 'published_year' => '2000')));
        try {
            $service->run(array('author_id' => '5', 'published_year' => (string) (date('Y') + 1)));
            $this->fail('invalid');
        } catch (ServiceError $e) {
            $this->assertSame('There is no such author', $e->fields['author_id']);
            $this->assertArrayHasKey('published_year', $e->fields);
        }
    }

    public function testTheSchemaIgnoresARuleWithoutItsArgument(): void
    {
        $schema = Capabilities::schemaFromRules(array('year' => 'integer|min_numeric,|max_numeric,', 'title' => 'max_len,|min_len,abc', 'n' => 'numeric|min_numeric,1.5'));
        $this->assertSame(array('type' => 'integer'), $schema['properties']['year']);
        $this->assertSame(array('type' => 'string'), $schema['properties']['title']);
        $this->assertSame(array('type' => 'number', 'minimum' => 1.5), $schema['properties']['n']);
        $this->assertSame(array('type' => 'integer', 'maximum' => (int) date('Y')), Capabilities::schemaFromRules(array('y' => 'integer|max_numeric,current_year'))['properties']['y']);
        $this->assertSame(array('type' => 'string', 'format' => 'email'), Capabilities::schemaFromRules(array('e' => 'valid_email|unique,users.email,id,user_id'))['properties']['e']);
    }
}
