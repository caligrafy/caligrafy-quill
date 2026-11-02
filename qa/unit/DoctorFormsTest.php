<?php

use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

/**
 * A form handled by a script and sent with no method falls back to GET when the script does not run: doctor names the
 * forms of application/views with an email or a password field and no method
 */
final class DoctorFormsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/quill-forms-' . bin2hex(random_bytes(4));
        mkdir("$this->dir/application/views/Auth", 0755, true);
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->dir);
    }

    private function view(string $name, string $pug): void
    {
        file_put_contents("$this->dir/application/views/$name", $pug);
    }

    public function testAFormWithAPasswordAndNoMethodIsNamed(): void
    {
        $this->view('Auth/signup.pug', "block content\n  main\n    form#signup(novalidate)\n      .cq-field\n        input#email(type='email', name='email')\n        input#password(type='password', name='password')\n");
        $this->assertSame(['application/views/Auth/signup.pug:3'], Doctor::formsWithoutMethod($this->dir));
    }

    public function testAFormWithAMethodIsFine(): void
    {
        $this->view('Auth/login.pug', "block content\n  form#login(method='POST', action=\"{\$home}login\")\n    input(type='password', name='passcode')\n");
        $this->assertSame([], Doctor::formsWithoutMethod($this->dir));
    }

    public function testAFormWithoutAnEmailOrAPasswordIsFine(): void
    {
        $this->view('tasks.pug', "block content\n  form#new-task(novalidate)\n    input(type='text', name='title')\n");
        $this->assertSame([], Doctor::formsWithoutMethod($this->dir));
    }

    public function testOnlyTheFormsOwnFieldsCount(): void
    {
        // the password field after the first form belongs to the second one, which has a method
        $this->view('two.pug', "block content\n  form#search\n    input(type='text', name='q')\n  form#login(method='POST')\n    input(type='password', name='passcode')\n  form#news\n    input(type='email', name='email')\n");
        $this->assertSame(['application/views/two.pug:6'], Doctor::formsWithoutMethod($this->dir));
    }

    public function testAProjectWithoutViewsHasNothingToSay(): void
    {
        $this->assertSame([], Doctor::formsWithoutMethod(sys_get_temp_dir() . '/quill-no-such-project'));
    }
}
