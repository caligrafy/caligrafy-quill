<?php

use Caligrafy\Model;
use PHPUnit\Framework\TestCase;

/**
 * A model without a table: tableColumns() returns no columns, so prepareInputs() runs without a database
 */
final class ExposedModel extends Model
{
    public function inputs($inputs) { return $this->prepareInputs($inputs); }
    public function quote($name) { return $this->quoteIdentifier($name); }
}

final class ModelTest extends TestCase
{
    public function testPrepareInputsKeepsFalsyValuesAndDropsNullAndRestricted(): void
    {
        $inputs = (new ExposedModel())->inputs([
            'zero' => 0, 'empty' => '', 'no' => false, 'yes' => true, 'missing' => null,
            'table' => 'users', 'pivot' => 'x', '__restricted' => [], 'tags' => ['a', 'b']
        ]);
        $this->assertSame(['zero' => 0, 'empty' => '', 'no' => 0, 'yes' => 1, 'tags' => '["a","b"]'], $inputs);
    }

    public function testPrepareInputsAcceptsObjects(): void
    {
        $this->assertSame(['name' => 'Ada'], (new ExposedModel())->inputs((object)['name' => 'Ada', 'age' => null]));
    }

    public function testQuoteIdentifierQuotesSafeNames(): void
    {
        $this->assertSame('`user_id`', (new ExposedModel())->quote(' user_id '));
    }

    /**
     * Current behavior: unsafe names are rejected (error 2001), never escaped
     */
    public function testQuoteIdentifierRejectsUnsafeNames(): void
    {
        foreach (['id`; DROP TABLE users', 'a.b', 'na me', ''] as $name) {
            try {
                (new ExposedModel())->quote($name);
                $this->fail("'$name' was accepted");
            } catch (Exception $e) {
                $this->assertSame(2001, $e->getCode());
            }
        }
    }
}
