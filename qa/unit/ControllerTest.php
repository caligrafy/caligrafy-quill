<?php

use Caligrafy\Controller;
use Caligrafy\Validator;
use PHPUnit\Framework\TestCase;

/**
 * The constructor of Controller connects to the database: the test builds it without the constructor
 */
final class ControllerTest extends TestCase
{
    private function controller(): Controller
    {
        $controller = (new ReflectionClass(Controller::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Controller::class, 'validator'))->setValue($controller, new Validator());
        return $controller;
    }

    public function testValidate(): void
    {
        $rules = ['email' => 'required|valid_email'];
        $this->assertTrue($this->controller()->validate(['email' => 'ada@example.com'], $rules));
        $errors = $this->controller()->validate(['email' => 'nope'], $rules);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testFilter(): void
    {
        $this->assertSame(['name' => 'Ada'], $this->controller()->filter(['name' => '  Ada '], ['name' => 'trim']));
    }
}
